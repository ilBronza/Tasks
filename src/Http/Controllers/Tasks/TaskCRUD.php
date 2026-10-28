<?php

namespace IlBronza\Tasks\Http\Controllers\Tasks;

use IlBronza\CRUD\CRUD;

class TaskCRUD extends CRUD
{
	public $configModelClassName = 'task';

	public function getRouteBaseNamePrefix(): ?string
	{
		return rtrim(config('tasks.routePrefix'), '.');
	}

	public function getRouteBaseNamePieces()
	{
		return !empty($this->routeBaseNamePieces)
			? $this->routeBaseNamePieces : [rtrim(config('tasks.routePrefix'), '.')];
	}

	public function setModelClass()
	{
		$this->modelClass = config('tasks.models.' . $this->configModelClassName . '.class');
	}
}
