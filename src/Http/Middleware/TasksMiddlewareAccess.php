<?php

namespace IlBronza\Tasks\Http\Middleware;

use Closure;
use IlBronza\CRUD\Middleware\CRUDBasePackageMiddlewareRolesPermissions;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
		if ($this->userHasAnyConfiguredRole($user, $this->getRolesForCurrentRoute($request)))
			return $next($request);
		abort_if(config('tasks.models.task.class')::isTaskStaff($user), 403);

		$prefix = config('tasks.routePrefix');
		$participantRoutes = array_map(fn ($name) => $prefix . $name, [
			'index', 'closed', 'conversation.index', 'conversation.store', 'conversation.attachment',
		]);
		abort_unless(in_array($request->route()?->getName(), $participantRoutes, true), 403);

		// The controller checks access to the requested task. Lists are filtered per user.
		return $next($request);
	}
}
