<?php

namespace IlBronza\Tasks\Http\ParametersFiles\Fieldsets;

use IlBronza\Form\Helpers\FieldsetsProvider\FieldsetParametersFile;

class ProjectFieldsetsParameters extends FieldsetParametersFile
{
    public function _getFieldsetsParameters(): array
    {
        return [
            'general' => [
                'translationPrefix' => 'tasks::fields',
                'fields' => [
                    'name' => [
                        'text' => 'required|string|max:255|unique:' . config('tasks.models.project.table'),
                    ],
                ],
            ],
        ];
    }
}
