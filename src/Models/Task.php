<?php

namespace IlBronza\Tasks\Models;

use IlBronza\AccountManager\Models\User;
use IlBronza\CRUD\Models\BaseModel;
use IlBronza\CRUD\Traits\Media\InteractsWithMedia;
use IlBronza\CRUD\Traits\Model\CRUDCreatedByUserTrait;
use IlBronza\CRUD\Traits\Model\CRUDUseUuidTrait;
use IlBronza\CRUD\Traits\Model\PackagedModelsTrait;
use IlBronza\Notes\Traits\InteractsWithNotesTrait;
use IlBronza\Notes\Contracts\AuthorizesNotes;
use IlBronza\Notes\Models\Note;
use IlBronza\Tasks\Models\Traits\TracksTaskAssignments;
use IlBronza\Tasks\Sync\TaskSync;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;

class Task extends BaseModel implements HasMedia, AuthorizesNotes
{
	use InteractsWithNotesTrait;
	use CRUDCreatedByUserTrait;
	use InteractsWithMedia;
	use CRUDUseUuidTrait;
	use PackagedModelsTrait;
	use TracksTaskAssignments {
		save as private saveWithAssignments;
	}

	static $packageConfigPrefix = 'tasks';
	static $modelConfigPrefix = 'task';
	static $nameField = 'title';
	public ?string $translationFolderPrefix = 'tasks';
	protected $keyType = 'string';
	static $deletingRelationships = ['media'];

	protected $attributes = [
		'project_id' => Project::DEFAULT_ID,
	];

	public function save(array $options = [])
	{
		$opening = TaskSync::client() && !$this->exists;
		$publish = TaskSync::hub() && $this->sync_installation_id && (!$this->exists ||
			$this->isDirty(['title', 'status', 'end_date', 'assignee_user_id', 'deleted_at']));
		if (!$opening && !$publish)
			return $this->saveWithAssignments($options);

		return $this->getConnection()->transaction(function () use ($opening, $options) {
			if ($opening) {
				$this->sync_installation_id = TaskSync::installationId();
				$this->sync_requester = auth()->user()?->name;
				$this->status = 'open';
				$this->end_date = null;
				$this->assignee_user_id = null;
			}
			if (!$this->saveWithAssignments($options))
				return false;
			if ($opening)
				TaskSync::enqueueOpening($this);
			else
				TaskSync::publishState($this->fresh(['assignee']));
			return true;
		});
	}

	public function delete()
	{
		if (!TaskSync::hub() || !$this->sync_installation_id)
			return parent::delete();

		return $this->getConnection()->transaction(function () {
			$result = parent::delete();
			if ($result) {
				$this->deleted_at ??= now();
				TaskSync::publishState($this);
			}
			return $result;
		});
	}

	public static function userCanCreate(?User $user = null)
	{
		if (TaskSync::client())
			return ($user ?? auth()->user())?->hasAnyRole(config('tasks.sync.reporter_roles')) ?? false;

		return parent::userCanCreate($user);
	}

	public function workspaceAssignee(): ?string
	{
		return TaskSync::client() && $this->sync_installation_id ? $this->sync_assignee : $this->assignee?->name;
	}

	public function workspaceAssigneeId(): string
	{
		if (TaskSync::client() && $this->sync_installation_id)
			return $this->sync_assignee_key === null ? '' : 'hub:' . $this->sync_assignee_key;

		return (string) $this->assignee_user_id;
	}

	public function project(): BelongsTo
	{
		return $this->belongsTo(config('tasks.models.project.class'), 'project_id');
	}

	public function setProjectIdAttribute($value): void
	{
		$this->attributes['project_id'] = $value ?? Project::DEFAULT_ID;
	}

	public function getPossibleProjectsValuesArray(): array
	{
		return $this->project()->getRelated()->newQuery()->orderBy('name')->pluck('name', 'id')->all();
	}

	protected $casts = [
		'start_date' => 'datetime',
		'end_date' => 'datetime',
		'deleted_at' => 'datetime',
	];

	public function getRouteBasename()
	{
		return ($this->routeBasename ?? null) ?: rtrim(config('tasks.routePrefix'), '.');
	}

	public function getRouteClassname()
	{
		return $this->routeClassname ?? 'task';
	}

	public function scopeClosed($query)
	{
		$query->where('status', 'closed');
	}

	public function scopeNotClosed($query)
	{
		return $query->where(function ($query) {
			$query->where('status', '!=', 'closed')->orWhereNull('status');
		});
	}

	public static function getWorkflowStatuses() : array
	{
		return [
			'open' => __('tasks::tasks.statuses.open'),
			'in_progress' => __('tasks::tasks.statuses.in_progress'),
			'not_manageable' => __('tasks::tasks.statuses.not_manageable'),
			'closed' => __('tasks::tasks.statuses.closed'),
		];
	}

	public function getSelectPossibleStatusValues() : array
	{
		return static::getWorkflowStatuses();
	}

	public function userCanComment($user = null) : bool
	{
		return (bool) $this->userCanSee($user);
	}

	public function userCanSeeNote(Note $note, ?User $user, bool $defaultAllowed): bool
	{
		return $this->userCanSee($user)
			&& (static::isTaskStaff($user) ? $defaultAllowed : $note->conversation_visibility === 'shared');
	}

	public function userCanUpdateNote(Note $note, ?User $user, bool $defaultAllowed): bool
	{
		return $this->userCanUpdate($user) && $defaultAllowed;
	}

	public function userCanDeleteNote(Note $note, ?User $user, bool $defaultAllowed): bool
	{
		return $this->userCanUpdateNote($note, $user, $defaultAllowed);
	}

	public static function isTaskStaff($user = null) : bool
	{
		if (TaskSync::client())
			return false;
		$user = $user ?? auth()->user();
		$roles = config('tasks.conversation.staffRoles', config('tasks.defaultRoles', []));

		return $user && $user->hasAnyRole(is_array($roles) ? $roles : explode('|', $roles));
	}

	public function participants()
	{
		return $this->belongsToMany(
			config('auth.providers.users.model'), config('tasks.conversation.participantsTable'), 'task_id', 'user_id'
		)->withTimestamps();
	}

	public function userCanSee(?User $user = null)
	{
		$user = $user ?? auth()->user();
		if (!$user)
			return false;

		if (TaskSync::client() && (string) $this->user_id === (string) $user->getKey())
			return true;
		if (static::isTaskStaff($user))
			return parent::userCanSee($user);

		return $this->participants()->whereKey($user->getKey())->exists();
	}

	public function userCanUpdate(?User $user = null)
	{
		$user = $user ?? auth()->user();

		return static::isTaskStaff($user) && parent::userCanUpdate($user);
	}

	public function userCanDelete(?User $user = null)
	{
		$user = $user ?? auth()->user();

		return static::isTaskStaff($user) && parent::userCanDelete($user);
	}

	public function assignee()
	{
		return $this->belongsTo(config('auth.providers.users.model'), 'assignee_user_id');
	}

	public function getPossibleAssignees() : Collection
	{
		return User::gpc()::role('taskAssignee')->get();
	}

	public function getPossibleAssigneeUsersArray() : array
	{
		return $this->getPossibleAssignees()->pluck('name', 'id')->toArray();
	}

	public function getPossibleAssigneesValuesArray() : array
	{
		return $this->getPossibleAssigneeUsersArray();
	}
}
