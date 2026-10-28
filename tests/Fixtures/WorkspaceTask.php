<?php

namespace IlBronza\Tasks\Tests\Fixtures;

use IlBronza\AccountManager\Models\User;
use IlBronza\Notes\Models\Task;

class WorkspaceTask extends Task
{
	public function userCanSee(?User $user = null)
	{
		return $this->title !== 'Hidden' && parent::userCanSee($user);
	}

	public function userCanUpdate(?User $user = null)
	{
		return $this->title !== 'Read only' && parent::userCanUpdate($user);
	}

	public function userCanComment($user = null) : bool
	{
		return $this->title !== 'No replies' && parent::userCanComment($user);
	}
}
