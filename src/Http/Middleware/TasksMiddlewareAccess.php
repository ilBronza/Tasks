<?php

namespace IlBronza\Tasks\Http\Middleware;

use Closure;
use IlBronza\CRUD\Middleware\CRUDBasePackageMiddlewareRolesPermissions;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use IlBronza\Tasks\Sync\TaskSync;
use IlBronza\Tasks\Helpers\TaskCreationHelper;

class TasksMiddlewareAccess extends CRUDBasePackageMiddlewareRolesPermissions
{
	protected string $configPackageName = 'tasks';

	protected function getRolesForCurrentRoute(Request $request): array
	{
		$name = $request->route()?->getName();
		$roles = config('tasks.routeRoles.' . $name, config('notes.routeRoles.' . $name));

		return $this->normalizeRoles($roles ?: config('tasks.defaultRoles', []));
	}

	public function handle(Request $request, Closure $next) : Response
	{
		$user = $request->user();
		abort_unless($user, 403);
		$prefix = config('tasks.routePrefix');
		if (TaskSync::client()) {
			$name = $request->route()->getName();
			$roles = config('tasks.routeRoles.' . $name, config('notes.routeRoles.' . $name));
			abort_if($roles && !$this->userHasAnyConfiguredRole($user, $this->normalizeRoles($roles)), 403);
			if ($request->routeIs($prefix . 'create', $prefix . 'store'))
				abort_unless(TaskCreationHelper::canCreate(), 403);
			else
				abort_unless($request->routeIs($prefix . 'index', $prefix . 'closed', $prefix . 'conversation.*'), 403);

			return $next($request);
		}
		if ($request->routeIs(config('tasks.routePrefix') . 'projects.*'))
			abort_unless(config('tasks.models.task.class')::isTaskStaff($user), 403);
		if ($this->userHasAnyConfiguredRole($user, $this->getRolesForCurrentRoute($request)))
			return $next($request);
		abort_if(config('tasks.models.task.class')::isTaskStaff($user), 403);

		$participantRoutes = array_map(fn ($name) => $prefix . $name, [
			'index', 'closed', 'conversation.index', 'conversation.store', 'conversation.attachment',
		]);
		abort_unless(in_array($request->route()?->getName(), $participantRoutes, true), 403);

		// The controller checks access to the requested task. Lists are filtered per user.
		return $next($request);
	}
}
