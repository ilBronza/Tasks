<?php

namespace IlBronza\Tasks\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class TaskConversationController extends Controller
{
	protected function visibleTask(Request $request, string $task)
	{
		$model = config('tasks.models.task.class')::query()->findOrFail($task);
		abort_unless($model->userCanSee($request->user()), 403);

		return $model;
	}

	protected function serializeNote($note, Request $request, $task) : array
	{
		return [
			'id' => (string) $note->getKey(),
			'body' => $note->notes ?? '',
			'author' => $note->user?->name ?? __('notes::notes.unknownUser'),
			'isOwn' => (string) $note->user_id === (string) $request->user()->getAuthIdentifier(),
			'createdAt' => $note->created_at?->toIso8601String(),
			'dateLabel' => $note->created_at?->format('d/m/Y H:i'),
			'visibility' => $note->conversation_visibility ?? 'internal',
			'attachments' => $note->media->map(fn ($media) => [
				'name' => $media->file_name,
				'size' => (int) $media->size,
				'url' => route(config('tasks.routePrefix') . 'conversation.attachment', [
					'task' => $task->getKey(), 'note' => $note->getKey(), 'media' => $media->getKey(),
				]),
			])->values(),
		];
	}

	public function conversation(Request $request, string $task)
	{
		$model = $this->visibleTask($request, $task);
		$isStaff = $model::isTaskStaff($request->user());
		$query = $model->notes()->with(['user', 'media']);
		if (!$isStaff)
			$query->where('conversation_visibility', 'shared');
		$notes = $query->orderBy('created_at')->orderBy('conversation_order')->orderBy('id')->get();
		$notes->each(fn ($note) => $note->setRelation('noteable', $model));

		return response()->json([
			'messages' => $notes->filter(fn ($note) => !$isStaff || $note->userCanSee($request->user()))
				->map(fn ($note) => $this->serializeNote($note, $request, $model))->values(),
			'participants' => $model->participants()->get()->map(fn ($user) => [
				'id' => (string) $user->getKey(), 'name' => $user->name,
				'removeUrl' => $isStaff && $model->userCanUpdate($request->user())
					? route(config('tasks.routePrefix') . 'participants.destroy', [$model->getKey(), $user->getKey()]) : null,
			])->values(),
			'shareUrl' => $isStaff && $model->userCanUpdate($request->user())
				? route(config('tasks.routePrefix') . 'participants.store', $model->getKey()) : null,
		]);
	}

	public function reply(Request $request, string $task)
	{
		$model = $this->visibleTask($request, $task);
		abort_unless($model->userCanComment($request->user()), 403);

		$validated = $request->validate([
			'body' => ['nullable', 'string', 'max:10240'],
			'visibility' => ['required', Rule::in($model::isTaskStaff($request->user()) ? ['internal', 'shared'] : ['shared'])],
			'files' => ['nullable', 'array', 'max:5'],
			'files.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,gif,webp,txt,csv,doc,docx,xls,xlsx,zip'],
		]);
		$body = trim($validated['body'] ?? '');
		$files = $request->file('files', []);
		if ($body === '' && !count($files))
			throw ValidationException::withMessages(['body' => __('tasks::tasks.messageRequired')]);

		$note = $model->getConnection()->transaction(function () use ($model, $body, $request, $validated) {
			$model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();
			$note = $model->notes()->make();
			$note->notes = $body;
			$note->user_id = $request->user()->getAuthIdentifier();
			$note->conversation_visibility = $validated['visibility'];
			$note->conversation_order = ($model->notes()->withArchived()->withTrashed()->max('conversation_order') ?? 0) + 1;
			$note->save();

			return $note;
		});

		try {
			foreach ($files as $file)
				$note->addMedia($file)->toMediaCollection('files', config('tasks.conversation.disk'));
		} catch (\Throwable $exception) {
			// Media files are outside database transactions: remove partial uploads too.
			$note->clearMediaCollection('files');
			$note->forceDelete();
			throw $exception;
		}

		$model->touch();
		$note->load(['user', 'media']);

		return response()->json(['message' => $this->serializeNote($note, $request, $model)], 201);
	}

	public function attachment(Request $request, string $task, string $note, string $media)
	{
		$model = $this->visibleTask($request, $task);
		$note = $model->notes()->findOrFail($note);
		$note->setRelation('noteable', $model);
		abort_unless($model::isTaskStaff($request->user())
			? $note->userCanSee($request->user()) : $note->conversation_visibility === 'shared', 403);
		$media = $note->media()->findOrFail($media);
		$disk = Storage::disk($media->disk);
		$path = $media->getPathRelativeToRoot();
		abort_unless($disk->exists($path), 404);

		return $disk->download($path, $media->file_name, ['X-Content-Type-Options' => 'nosniff']);
	}

	public function addParticipant(Request $request, string $task)
	{
		$model = $this->visibleTask($request, $task);
		abort_unless($model::isTaskStaff($request->user()) && $model->userCanUpdate($request->user()), 403);
		$validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
		$user = config('auth.providers.users.model')::where('email', $validated['email'])->first();
		if (!$user)
			throw ValidationException::withMessages(['email' => __('tasks::tasks.accountNotFound')]);

		$model->participants()->syncWithoutDetaching([$user->getKey()]);

		return response()->json(['success' => true]);
	}

	public function removeParticipant(Request $request, string $task, string $user)
	{
		$model = $this->visibleTask($request, $task);
		abort_unless($model::isTaskStaff($request->user()) && $model->userCanUpdate($request->user()), 403);
		$model->participants()->detach($user);

		return response()->json(['success' => true]);
	}
}
