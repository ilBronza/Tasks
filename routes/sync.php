<?php

use IlBronza\Tasks\Http\Controllers\TaskSyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/tasks-sync/v1')->name('tasks.sync.')->group(function () {
    Route::put('tasks/{origin}', [TaskSyncController::class, 'open'])->whereUuid('origin')->name('open');
    Route::put('tasks/{origin}/messages/{message}', [TaskSyncController::class, 'message'])
        ->whereUuid(['origin', 'message'])->name('message');
    Route::get('changes', [TaskSyncController::class, 'changes'])->name('changes');
});
