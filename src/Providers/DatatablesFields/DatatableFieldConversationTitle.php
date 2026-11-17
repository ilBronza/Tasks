<?php

namespace IlBronza\Tasks\Providers\DatatablesFields;

use IlBronza\Datatables\DatatablesFields\Links\DatatableFieldLink;

class DatatableFieldConversationTitle extends DatatableFieldLink
{
	public $requireElement = true;
	public $textParameter = 'title';

	public function transformValue($task)
	{
		$route = config('tasks.routePrefix') . ($task->status === 'closed' ? 'closed' : 'index');
		$query = [
			'view' => 'board',
		];
		if (request()->filled('project_id'))
			$query['project_id'] = $task->project_id;

		return [
			route($route, $query) . '#task-' . rawurlencode($task->getKey()),
			e($task->title ?: __('tasks::tasks.untitled')),
		];
	}
}
