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
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;

class Task extends BaseModel implements HasMedia, AuthorizesNotes
{
	use InteractsWithNotesTrait;
	use CRUDCreatedByUserTrait;
	use InteractsWithMedia;
	use CRUDUseUuidTrait;
	use PackagedModelsTrait;

	static $packageConfigPrefix = 'tasks';
	static $modelConfigPrefix = 'task';
	static $nameField = 'title';
	public ?string $translationFolderPrefix = 'tasks';
	protected $keyType = 'string';
	static $deletingRelationships = ['media'];

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
}
