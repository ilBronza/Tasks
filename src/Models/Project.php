<?php

namespace IlBronza\Tasks\Models;

use IlBronza\AccountManager\Models\User;
use IlBronza\CRUD\Models\BaseModel;
use IlBronza\CRUD\Traits\Model\CRUDUseUuidTrait;
use IlBronza\CRUD\Traits\Model\PackagedModelsTrait;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends BaseModel
{
    use CRUDUseUuidTrait;
    use PackagedModelsTrait;

    // The database default keeps working for legacy inserts and after a rename.
    public const DEFAULT_ID = '2b8b997a-ce66-4a45-9ec9-e8c1cc61d459';

    static $packageConfigPrefix = 'tasks';
    static $modelConfigPrefix = 'project';
    protected $keyType = 'string';
    public $routeClassname = 'project';
    public ?string $translationFolderPrefix = 'tasks';

    public static function userCanCreate(?User $user = null)
    {
        return config('tasks.models.task.class')::isTaskStaff($user);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(config('tasks.models.task.class'), 'project_id');
    }
}
