<?php

namespace IlBronza\Tasks\Tests\Fixtures;

use IlBronza\Tasks\Http\ParametersFiles\Fieldsets\TaskCreateStoreFieldsetsParameters;

class CreatorFieldTaskParameters extends TaskCreateStoreFieldsetsParameters
{
    public function _getFieldsetsParameters(): array
    {
        $parameters = parent::_getFieldsetsParameters();
        $parameters['package']['fields']['user_id'] = ['number' => 'required|integer|exists:users,id'];

        return $parameters;
    }
}
