<?php

namespace IlBronza\Tasks\Http\ParametersFiles\Datatables;

use IlBronza\Datatables\Providers\FieldsGroupParametersFile;
use IlBronza\Form\Helpers\FieldsetsProvider\FieldsetParametersFile;

class TaskFieldsGroupParametersFile extends FieldsGroupParametersFile
{
	static function getFieldsGroup() : array
	{
		return [
			'translationPrefix' => 'tasks::fields',
			'fields' =>
				[
					'mySelfPrimary' => 'primary',

					'mySelfEdit' => 'links.edit',

					'created_at' => [
						'type' => 'dates.datetime',
						'order' => [
							'type' => 'DESC',
							'priority' => 100
						]
					],
					'user_id' => 'users.name',
					'title' => 'flat',
					'description' => 'text',
					'start_date' => 'editor.dates.datetime',
					'end_date' => 'editor.dates.datetime',
					'status' => [
						'type' => 'editor.select',
						'refreshRow' => true,
						'valueAsRowClass' => true
					],
					'mySelfNotes' => 'notes::notesList',

//					'note_number' => [
//						'type' => 'flat',
//						'fetcher' => [
//							'urlMethod' => 'getNotesPopupUrl',
//							'target' => 'row'
//						],
//						'valueAsRowClass' => true,
//						'width' => '45px'
//					],
//
					'assignee_user_id' => [
						'type' => 'editor.select',
	                    'width' => '250px',
	                    'possibleValuesMethod' => 'getPossibleAssigneeUsersArray',
	                    'refreshRow' => true,
					],
					'minutes' => 'editor.numeric',
					'commit' => 'editor.text',

					'updated_at' => [
						'type' => 'dates.datetime',
						'order' => [
							'type' => 'DESC',
							'priority' => 100
						]
					],

					'mySelfDelete' => 'links.delete'
				]
		];
	}
}
