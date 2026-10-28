<?php

use IlBronza\Tasks\Http\ParametersFiles\Datatables\TaskFieldsGroupParametersFile;
use IlBronza\Tasks\Http\ParametersFiles\Fieldsets\TaskCreateStoreFieldsetsParameters;
use IlBronza\Tasks\Http\ParametersFiles\Fieldsets\TaskShowFieldsetsParameters;
use IlBronza\Tasks\Models\Task;

return [
    // Retained for existing applications and saved links.
    'routePrefix' => 'notesmanagertasks.',
    'defaultRoles' => ['superadmin', 'administrator', 'notes'],
    'routeRoles' => [],
    'conversation' => [
        'disk' => 'local',
        'participantsTable' => 'notes__task_participants',
    ],
    'models' => [
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
