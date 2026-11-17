<?php

namespace IlBronza\Tasks\Http\ParametersFiles\Datatables;

use IlBronza\Datatables\Providers\FieldsGroupParametersFile;

class ProjectFieldsGroupParametersFile extends FieldsGroupParametersFile
{
    public static function getFieldsGroup(): array
    {
        return [
            'translationPrefix' => 'tasks::fields',
            'fields' => [
                'mySelfPrimary' => [
                    'type' => 'primary',
                ],
                'name' => [
                    'type' => 'tasks::plainText',
                ],
                'mySelfEdit' => [
                    'type' => 'links.edit',
                ],
            ],
        ];
    }
}
