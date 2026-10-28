<?php

namespace IlBronza\Tasks\Http\Controllers\Tasks;

use IlBronza\CRUD\Traits\CRUDEditUpdateTrait;
use Illuminate\Http\Request;

use function config;

class TaskEditUpdateController extends TaskCRUD
{
	use CRUDEditUpdateTrait;

	public $allowedMethods = ['edit', 'update'];

	public function getEditParametersFile() : ? string
	{
		//TaskCreateStoreFieldsetsParameters
		return config('tasks.models.task.parametersFiles.create');
	}

	public function edit(string $task)
	{
		$task = $this->findModel($task);

		return $this->_edit($task);
	}

	public function update(Request $request, $task)
	{
		$task = $this->findModel($task);

		return $this->_update($request, $task);
	}
}
