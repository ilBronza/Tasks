<?php

namespace IlBronza\Tasks\Http\Controllers\Tasks;

use Illuminate\Http\Request;

class TaskAssignmentController extends TaskEditUpdateController
{
    public $allowedMethods = ['updateAssignment'];

    public function updateAssignment(Request $request, string $task)
    {
        $query = $this->getFindModelQuery($task);
        $model = $query->getModel()->getConnection()->transaction(function () use ($query, $request, $task) {
            $model = $query->lockForUpdate()->findOrFail($task);
            abort_unless($model::isTaskStaff($request->user()) && $model->userCanSee($request->user()), 403);
            $this->setModel($model);
            $this->checkIfUserCanUpdate();
            $validated = $request->validate([
                'assignee_user_id' => ['present', 'nullable', 'integer'],
                'comment' => ['nullable', 'string', 'max:10240'],
            ]);
            $model->assignee_user_id = $validated['assignee_user_id'];
            $model->setAssignmentComment($validated['comment'] ?? null);
            if (!$model->saveOrFail())
                abort(409);

            return $model;
        });
        $this->broadcastCrudModel($model, 'updated');

        return response()->json([
            'id' => (string) $model->getKey(),
            'assigneeId' => (string) ($model->assignee_user_id ?? ''),
            'assignee' => $model->assignee?->name,
        ]);
    }
}
