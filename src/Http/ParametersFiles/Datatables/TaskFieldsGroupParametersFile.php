<?php

namespace IlBronza\Tasks\Http\ParametersFiles\Datatables;

use IlBronza\Datatables\Providers\FieldsGroupParametersFile;

class TaskFieldsGroupParametersFile extends FieldsGroupParametersFile
{
	static function getFieldsGroup() : array
	{
		$task = new (config('tasks.models.task.class'));
		$unassigned = __('tasks::tasks.unassigned');

		return [
			'translationPrefix' => 'tasks::fields',
			'fields' => [
				'mySelfPrimary' => 'primary',
				'title' => 'tasks::conversationTitle',
				'status' => [
					'type' => 'editor.select',
					'possibleValuesMethod' => 'getSelectPossibleStatusValues',
					'nullable' => false,
					'default' => 'open',
					'reloadTable' => true, // Closing or reopening a task moves it to the other index.
					'valueAsRowClass' => true,
				],
				'assignee_user_id' => [
					'type' => 'editor.select',
					// The generic nullable select reindexes numeric keys when adding its empty option.
					'nullable' => false,
					'nullString' => $unassigned,
					'possibleValuesArray' => ['null' => $unassigned] + array_map(fn ($name) => e($name), $task->getPossibleAssigneeUsersArray()),
					'refreshRow' => true,
				],
				'end_date' => 'editor.dates.datetime',
				'mySelfEdit' => 'links.edit',
				'mySelfNotes' => 'notes::notesList',
				'description' => ['type' => 'tasks::plainText', 'visible' => false],
				'start_date' => ['type' => 'editor.dates.datetime', 'visible' => false],
				'user_id' => ['type' => 'tasks::userName', 'visible' => false],
				'minutes' => ['type' => 'editor.numeric', 'visible' => false],
				'commit' => ['type' => 'tasks::editorText', 'visible' => false],
				'created_at' => ['type' => 'dates.datetime', 'visible' => false],
				'updated_at' => ['type' => 'dates.datetime', 'visible' => false],
				'mySelfDelete' => 'links.delete',
			],
		];
	}
}
