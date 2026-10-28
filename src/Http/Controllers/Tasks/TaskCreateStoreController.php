<?php

namespace IlBronza\Tasks\Http\Controllers\Tasks;

use IlBronza\CRUD\Traits\CRUDCreateStoreTrait;
use IlBronza\CRUD\Traits\CRUDRelationshipTrait;

use function config;

class TaskCreateStoreController extends TaskCRUD
{
	use CRUDCreateStoreTrait;
	use CRUDRelationshipTrait;

	public $allowedMethods = [
		'create',
		'store',
	];

	public function getGenericParametersFile() : ? string
	{
		return config('tasks.models.task.parametersFiles.create');
	}
}
