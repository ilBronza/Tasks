<?php

namespace IlBronza\Tasks\Http\ParametersFiles\Fieldsets;

class ClientTaskFieldsetsParameters extends TaskCreateStoreFieldsetsParameters
{
    public function _getFieldsetsParameters(): array
    {
        $package = parent::_getFieldsetsParameters()['package'];
        $package['fields'] = array_intersect_key($package['fields'], array_flip([
            'title',
            'project_id',
            'description',
            'link',
        ]));
        $package['fields']['description'] = [
            'textarea' => 'nullable|string|max:16000',
        ];

        return [
            'package' => $package,
        ];
    }
}
