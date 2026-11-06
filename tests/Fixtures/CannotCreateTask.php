<?php

namespace IlBronza\Tasks\Tests\Fixtures;

use IlBronza\AccountManager\Models\User;

class CannotCreateTask extends WorkspaceTask
{
    public static function userCanCreate(?User $user = null)
    {
        return false;
    }
}
