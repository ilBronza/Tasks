<?php

use IlBronza\Tasks\Facades\Tasks;
use IlBronza\Tasks\Http\Controllers\Tasks\ClosedTaskIndexController;
use IlBronza\Tasks\Http\Controllers\Tasks\TaskCreateStoreController;
use IlBronza\Tasks\Http\Controllers\Tasks\TaskDestroyController;
use IlBronza\Tasks\Http\Controllers\Tasks\TaskEditUpdateController;
use IlBronza\Tasks\Http\Controllers\Tasks\TaskIndexController;
use IlBronza\Tasks\Http\Controllers\Tasks\TaskStatusController;
use IlBronza\Tasks\Http\Controllers\Tasks\TaskConversationController;

Route::group([
	'middleware' => ['web', 'auth', 'tasks.access'],
	'prefix' => 'tasks-management',
	'as' => config('tasks.routePrefix'),
	'routeTranslationPrefix' => Tasks::getRouteTranslationPrefix()
],
	function()
	{
		Route::get('completed', [ClosedTaskIndexController::class, 'index'])->name('closed');
		Route::get('', [TaskIndexController::class, 'index'])->name('index');
		Route::post('', [TaskCreateStoreController::class, 'store'])->name('store');
		Route::get('create', [TaskCreateStoreController::class, 'create'])->name('create');
		Route::get('{task}/edit', [TaskEditUpdateController::class, 'edit'])->name('edit');
		Route::put('{task}', [TaskEditUpdateController::class, 'update'])->name('update');
		Route::patch('{task}/status', [TaskStatusController::class, 'updateStatus'])->name('status');
		Route::get('{task}/conversation', [TaskConversationController::class, 'conversation'])->name('conversation.index');
		Route::post('{task}/conversation', [TaskConversationController::class, 'reply'])->name('conversation.store');
		Route::get('{task}/conversation/{note}/attachments/{media}', [TaskConversationController::class, 'attachment'])->name('conversation.attachment');
		Route::post('{task}/participants', [TaskConversationController::class, 'addParticipant'])->name('participants.store');
		Route::delete('{task}/participants/{user}', [TaskConversationController::class, 'removeParticipant'])->name('participants.destroy');


		Route::delete('{task}/delete', [TaskDestroyController::class, 'destroy'])->name('destroy');
	}
);
