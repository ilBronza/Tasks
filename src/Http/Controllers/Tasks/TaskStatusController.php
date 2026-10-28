<?php

namespace IlBronza\Tasks\Http\Controllers\Tasks;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskStatusController extends TaskEditUpdateController
{
	public $allowedMethods = ['updateStatus'];

	public function updateStatus(Request $request, string $task)
	{
		$model = $this->getFindModelQuery($task)->findOrFail($task);
		abort_unless($model->userCanSee($request->user()), 403);
		$this->setModel($model);
		$this->checkIfUserCanUpdate();

		$validated = $request->validate([
			'status' => ['required', Rule::in(array_keys($this->getModelClass()::getWorkflowStatuses()))],
		]);

		$model->status = $validated['status'];
		$model->save();
		$this->broadcastCrudModel($model, 'updated');

		return response()->json([
			'id' => (string) $model->getKey(),
			'status' => $model->status,
		]);
	}
}
