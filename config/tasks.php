<?php

use IlBronza\Tasks\Http\ParametersFiles\Datatables\TaskFieldsGroupParametersFile;
use IlBronza\Tasks\Http\ParametersFiles\Fieldsets\TaskCreateStoreFieldsetsParameters;
use IlBronza\Tasks\Http\ParametersFiles\Fieldsets\TaskShowFieldsetsParameters;
use IlBronza\Tasks\Models\Task;
use IlBronza\Tasks\Models\Project;
use IlBronza\Tasks\Http\ParametersFiles\Fieldsets\ProjectFieldsetsParameters;
use IlBronza\Tasks\Http\ParametersFiles\Datatables\ProjectFieldsGroupParametersFile;

return [
    // Retained for existing applications and saved links.
    'routePrefix' => 'notesmanagertasks.',
    'defaultRoles' => ['superadmin', 'administrator', 'notes'],
    'routeRoles' => [],
    'defaultProjectName' => null,
    'sync' => [
        'mode' => env('TASKS_SYNC_MODE', 'standalone'),
        'installation' => env('TASKS_SYNC_INSTALLATION'),
        'hub_url' => env('TASKS_SYNC_HUB_URL'),
        'token' => env('TASKS_SYNC_TOKEN'),
        'reporter_roles' => [
            'taskReporter',
            'notes',
            'administrator',
            'superadmin',
        ],
    ],
    'assignments' => [
        'table' => 'tasks__assignments',
    ],
    'conversation' => [
        'disk' => 'local',
        'participantsTable' => 'notes__task_participants',
    ],
    'models' => [
        'project' => [
            'class' => Project::class,
            'table' => 'tasks__projects',
            'parametersFiles' => [
                'create' => ProjectFieldsetsParameters::class,
                'edit' => ProjectFieldsetsParameters::class,
            ],
            'fieldsGroupsFiles' => [
                'index' => ProjectFieldsGroupParametersFile::class,
            ],
        ],
        'task' => [
            'enabled' => false,
            'class' => Task::class,
            'table' => 'notes__tasks',
            'parametersFiles' => [
                'create' => TaskCreateStoreFieldsetsParameters::class,
                'edit' => TaskCreateStoreFieldsetsParameters::class,
                'show' => TaskShowFieldsetsParameters::class,
            ],
            'fieldsGroupsFiles' => [
                'index' => TaskFieldsGroupParametersFile::class,
            ],
        ],
    ],
];
