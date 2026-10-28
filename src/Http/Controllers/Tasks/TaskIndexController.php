<?php

namespace IlBronza\Tasks\Http\Controllers\Tasks;

use IlBronza\CRUD\Traits\CRUDIndexTrait;
use Illuminate\Http\Request;

class TaskIndexController extends TaskCRUD
{
	use CRUDIndexTrait;

	public $allowedMethods = ['index'];
	protected bool $showClosed = false;

	public function index(Request $request)
	{
		if ($request->query('view') === 'table' || $request->ajax())
		{
			abort_unless($this->getModelClass()::isTaskStaff($request->user()), 403);
			return $this->_index($request);
		}

		$this->setPageTitle(__('tasks::tasks.workspace'));
		$this->beforeIndex();

		$isStaff = $this->getModelClass()::isTaskStaff($request->user());
		$tasks = $this->getIndexElements();
		$tasks->loadMissing('assignee');
		$tasks->loadCount(['notes' => function ($query) use ($isStaff) {
			if (!$isStaff)
				$query->where('conversation_visibility', 'shared');
		}]);
		$tasks = $tasks->filter(fn ($task) => $task->userCanSee($request->user()));
		$prefix = config('tasks.routePrefix');

		return view('tasks::tasks.index', [
			'tasks' => $tasks->map(function ($task) use ($prefix, $request, $isStaff) {
				return [
					'id' => (string) $task->getKey(),
					'title' => $task->title ?: __('tasks::tasks.untitled'),
					'description' => $isStaff ? trim(strip_tags($task->description ?? '')) : '',
					'status' => $task->status ?: 'open',
					'assigneeId' => (string) ($task->assignee_user_id ?? ''),
					'assignee' => $task->assignee?->name,
					'startDate' => $task->start_date?->format('d/m/Y H:i'),
					'dueDate' => $task->end_date?->format('Y-m-d'),
					'dueLabel' => $task->end_date?->format('d/m/Y'),
					'minutes' => $isStaff ? $task->minutes : null,
					'commit' => $isStaff ? $task->commit : null,
					'canUpdate' => (bool) $task->userCanUpdate($request->user()),
					'editUrl' => route($prefix . 'edit', $task->getKey()),
					'statusUrl' => route($prefix . 'status', $task->getKey()),
					'notesUrl' => $task->getNotesListUrl(),
					'conversationUrl' => route($prefix . 'conversation.index', $task->getKey()),
					'canComment' => $task->userCanComment($request->user()),
					'messageCount' => $task->notes_count,
				];
			})->values(),
			'statuses' => $this->getModelClass()::getWorkflowStatuses(),
			'showClosed' => $this->showClosed,
			'currentUserId' => (string) $request->user()->getAuthIdentifier(),
			'today' => today()->format('Y-m-d'),
			'canCreate' => $this->getModelClass()::userCanCreate($request->user()),
			'isStaff' => $isStaff,
			'indexUrl' => route($prefix . 'index'),
			'closedUrl' => route($prefix . 'closed'),
			'createUrl' => route($prefix . 'create'),
			'tableUrl' => $request->fullUrlWithQuery(['view' => 'table']),
		]);
	}

	public function getIndexFieldsArray()
	{
		//TaskFieldsGroupParametersFile
		return config('tasks.models.task.fieldsGroupsFiles.index')::getTracedFieldsGroup();
	}

	public function getIndexElements()
	{
		return $this->getModelClass()::notClosed()
			->orderByRaw('end_date IS NULL')->orderBy('end_date')->latest('created_at')->get();
	}

}
