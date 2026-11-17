<?php

namespace IlBronza\Tasks\Http\Controllers\Tasks;

use IlBronza\CRUD\Traits\CRUDCreateStoreTrait;
use IlBronza\CRUD\Traits\CRUDRelationshipTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use IlBronza\Tasks\Sync\TaskSync;
use IlBronza\Tasks\Http\ParametersFiles\Fieldsets\ClientTaskFieldsetsParameters;

use function config;

class TaskCreateStoreController extends TaskCRUD
{
	use CRUDCreateStoreTrait;
	use CRUDRelationshipTrait;

	public $allowedMethods = [
		'create',
		'store',
	];

	public function userCanPerformCreate()
	{
		$taskClass = $this->getModelClass();
		abort_unless((TaskSync::client() || $taskClass::isTaskStaff(auth()->user())) && $taskClass::userCanCreate(auth()->user()), 403);
	}

	public function checkUserStoringRights()
	{
		$this->userCanPerformCreate();
	}

	public function getModelDefaultParameters() : array
	{
		$defaults = parent::getModelDefaultParameters();
		$defaults['user_id'] = auth()->id();
		if (request()->isMethod('GET') && ($id = request()->query('project_id')) !== null) {
			abort_unless(Str::isUuid($id) && config('tasks.models.project.class')::whereKey($id)->exists(), 404);
			$defaults['project_id'] = $id;
		}

		return $defaults;
	}

	public function store(Request $request, string $model = null)
	{
		// Also protects applications with a custom fieldset containing user_id.
		$request->merge(['user_id' => $request->user()->getAuthIdentifier()]);
		return $this->_store($request);
	}

	public function addCreateExtraViews()
	{
		if (!request()->boolean('from_page'))
			return;

		$form = $this->modelFormHelper->getForm();
		$form->addExtraView('outherBottom', 'tasks::tasks._prefillPageLink', [
			'taskFormId' => $form->getId(),
			'hasTaskOldInput' => request()->session()->hasOldInput(),
		]);
	}

	public function getGenericParametersFile() : ? string
	{
		if (TaskSync::client())
			return ClientTaskFieldsetsParameters::class;
		return config('tasks.models.task.parametersFiles.create');
	}
}
