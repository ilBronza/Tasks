<?php

namespace IlBronza\Tasks\Http\ParametersFiles\Fieldsets;

use IlBronza\Form\Helpers\FieldsetsProvider\FieldsetParametersFile;

class TaskCreateStoreFieldsetsParameters extends FieldsetParametersFile
{
	public function _getFieldsetsParameters() : array
	{
		return [
			'package' => [
				'translationPrefix' => 'tasks::fields',
				'fields' => [
					'title' => ['text' => 'string|required|max:255'],
					'description' => ['textarea' => 'string|nullable'],
					'status' => ['select' => 'string|nullable'],
					'assignee_user_id' => [
						'type' => 'select',
						'multiple' => false,
						'rules' => 'string|nullable|exists:users,id',
						'relation' => 'assignee'
					],
				],
				'width' => ['1-3@l', '1-2@m']
			],
			'tracking' => [
				'translationPrefix' => 'tasks::fields',
						'fields' => [
							'start_date' => ['datetime' => 'date|nullable'],
							'end_date' => ['datetime' => 'date|nullable'],
							'minutes' => ['number' => 'float|nullable|min:0'],
							'commit' => ['text' => 'string|nullable|max:255'],
						],
						'width' => ['1-3@l', '1-2@m']
					]
				];
	}
}
