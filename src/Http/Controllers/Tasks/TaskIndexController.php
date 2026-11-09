<?php

namespace IlBronza\Tasks\Http\Controllers\Tasks;

use IlBronza\Buttons\Button;
use IlBronza\CRUD\Traits\CRUDIndexTrait;
use Illuminate\Http\Request;

class TaskIndexController extends TaskCRUD
{
	use CRUDIndexTrait;

	public $allowedMethods = ['index'];
	protected bool $showClosed = false;

	public function index(Request $request)
	{
		$isStaff = $this->getModelClass()::isTaskStaff($request->user());
		$view = $request->query('view');
		if ($request->ajax() || $view === 'table' || ($isStaff && !in_array($view, ['board', 'list'], true)))
		{
			abort_unless($isStaff, 403);
			// A custom model can restrict even staff to a subset of the tasks.
			return $this->_index($request, elementsGetter: fn () => $this->visibleTasks($request));
		}

		$this->setPageTitle(__('tasks::tasks.workspace'));
		$this->beforeIndex();

		$tasks = $this->visibleTasks($request);
		$tasks->loadMissing('assignee');
		$tasks->loadCount(['notes' => function ($query) use ($isStaff) {
			if (!$isStaff)
				$query->where('conversation_visibility', 'shared');
		}]);
		if ($isStaff)
			$tasks->loadCount('assignments');
		$prefix = config('tasks.routePrefix');

		return view('tasks::tasks.index', [
			'assignees' => $isStaff ? (new ($this->getModelClass()))->getPossibleAssignees()
				->map(fn ($user) => ['id' => (string) $user->getKey(), 'name' => $user->name])->values() : [],
			'tasks' => $tasks->map(function ($task) use ($prefix, $request, $isStaff) {
				return [
					'id' => (string) $task->getKey(),
					'title' => $task->title ?: __('tasks::tasks.untitled'),
					'description' => $isStaff ? trim(strip_tags($task->description ?? '')) : '',
					'link' => $isStaff ? $task->link : null,
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
					'assignmentUrl' => $isStaff && $task->userCanUpdate($request->user())
						? route($prefix . 'assignment', $task->getKey()) : null,
					'notesUrl' => $task->getNotesListUrl(),
					'conversationUrl' => route($prefix . 'conversation.index', $task->getKey()),
					'canComment' => $task->userCanComment($request->user()),
					'messageCount' => $task->notes_count + ($isStaff ? $task->assignments_count : 0),
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

	protected function visibleTasks(Request $request)
	{
		return $this->getIndexElements()->filter(fn ($task) => $task->userCanSee($request->user()))->values();
	}

	public function getCaption(): string
	{
		return __($this->showClosed ? 'tasks::tasks.closed' : 'tasks::tasks.list');
	}

	public function addIndexButtons()
	{
		$prefix = config('tasks.routePrefix');
		$this->getTable()->addButton(Button::create([
			'text' => 'tasks::tasks.boardView', 'icon' => 'table-columns',
			'href' => route($prefix . ($this->showClosed ? 'closed' : 'index'), ['view' => 'board']),
		]));
		$this->getTable()->addButton(Button::create([
			'text' => $this->showClosed ? 'tasks::tasks.active' : 'tasks::tasks.closed',
			'icon' => $this->showClosed ? 'list' : 'check',
			'href' => route($prefix . ($this->showClosed ? 'index' : 'closed')),
		]));
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
