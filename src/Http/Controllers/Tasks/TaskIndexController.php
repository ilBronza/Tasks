<?php

namespace IlBronza\Tasks\Http\Controllers\Tasks;

use IlBronza\Buttons\Button;
use IlBronza\CRUD\Traits\CRUDIndexTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use IlBronza\Tasks\Sync\TaskSync;
use IlBronza\Tasks\Helpers\TaskCreationHelper;

class TaskIndexController extends TaskCRUD
{
	use CRUDIndexTrait;

	public $allowedMethods = ['index'];
	protected bool $showClosed = false;
	protected ?string $projectFilter = null;

	public function index(Request $request)
	{
		$isStaff = $this->getModelClass()::isTaskStaff($request->user());
		$projectId = $request->query('project_id');
		abort_unless($projectId === null || Str::isUuid($projectId), 404);
		// Participants' filter options come from the tasks they can see in this workspace.
		$this->projectFilter = $isStaff ? $projectId : null;
		$projectQuery = array_filter([
			'project_id' => $projectId,
		]);
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
		$projects = $isStaff
			? (new ($this->getModelClass()))->getPossibleProjectsValuesArray()
			: $tasks->pluck('project.name', 'project_id')->sort()->all();
		if (!$isStaff && $projectId)
			$tasks = $tasks->where('project_id', $projectId)->values();
		$tasks->loadMissing('assignee');
		$tasks->loadCount(['notes' => function ($query) use ($isStaff) {
			if (!$isStaff)
				$query->where('conversation_visibility', 'shared');
		}]);
		if ($isStaff)
			$tasks->loadCount('assignments');
		$prefix = config('tasks.routePrefix');

		return view('tasks::tasks.index', [
			'projects' => $projects,
			'selectedProject' => $projectId,
			'assignees' => $isStaff ? (new ($this->getModelClass()))->getPossibleAssignees()
				->map(fn ($user) => ['id' => (string) $user->getKey(), 'name' => $user->name])->values() : [],
			'tasks' => $tasks->map(function ($task) use ($prefix, $request, $isStaff) {
				return [
					'id' => (string) $task->getKey(),
					'title' => $task->title ?: __('tasks::tasks.untitled'),
					'project' => $task->project?->name,
					'description' => $isStaff || (TaskSync::client() && $task->sync_installation_id)
						? trim(strip_tags($task->description ?? '')) : '',
					'synchronized' => (bool) $task->sync_installation_id,
					'requester' => $isStaff ? $task->sync_requester : null,
					'link' => $isStaff ? $task->link : null,
					'status' => $task->status ?: 'open',
					'assigneeId' => $task->workspaceAssigneeId(),
					'assignee' => $task->workspaceAssignee(),
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
			'canCreate' => TaskCreationHelper::canCreate(),
			'isStaff' => $isStaff,
			'indexUrl' => route($prefix . 'index', $projectQuery),
			'closedUrl' => route($prefix . 'closed', $projectQuery),
			'createUrl' => route($prefix . 'create', $projectQuery),
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
		$projectQuery = array_filter([
			'project_id' => $this->projectFilter,
		]);
		if ($this->projectFilter)
			$this->getTable()->setUrl(route($prefix . ($this->showClosed ? 'closed' : 'index'), $projectQuery));
		$this->getTable()->addButton(Button::create([
			'text' => 'tasks::tasks.boardView', 'icon' => 'table-columns',
			'href' => route($prefix . ($this->showClosed ? 'closed' : 'index'), ['view' => 'board'] + $projectQuery),
		]));
		$this->getTable()->addButton(Button::create([
			'text' => $this->showClosed ? 'tasks::tasks.active' : 'tasks::tasks.closed',
			'icon' => $this->showClosed ? 'list' : 'check',
			'href' => route($prefix . ($this->showClosed ? 'index' : 'closed'), $projectQuery),
		]));
	}

	public function getCreateNewModelButton(): Button
	{
		$button = parent::getCreateNewModelButton();
		if ($this->projectFilter)
			$button->setHref(route(config('tasks.routePrefix') . 'create', [
				'project_id' => $this->projectFilter,
			]));

		return $button;
	}

	public function getIndexFieldsArray()
	{
		//TaskFieldsGroupParametersFile
		return config('tasks.models.task.fieldsGroupsFiles.index')::getTracedFieldsGroup();
	}

	public function getIndexElements()
	{
		return $this->taskQuery()->notClosed()
			->orderByRaw('end_date IS NULL')->orderBy('end_date')->latest('created_at')->get();
	}

	protected function taskQuery()
	{
		return $this->getModelClass()::with('project')
			->when($this->projectFilter, fn ($query, $id) => $query->where('project_id', $id));
	}

}
