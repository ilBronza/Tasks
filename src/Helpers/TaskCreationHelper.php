<?php

namespace IlBronza\Tasks\Helpers;

use IlBronza\Buttons\Button;
use IlBronza\Tasks\Sync\TaskSync;

class TaskCreationHelper
{
    public static function canCreate(): bool
    {
        $user = auth()->user();
        $taskClass = config('tasks.models.task.class');
        if (!$user || (!TaskSync::client() && !$taskClass::isTaskStaff($user)) || !$taskClass::userCanCreate($user))
            return false;

        foreach (['create', 'store'] as $action) {
            $route = config('tasks.routePrefix') . $action;
            $roles = config('tasks.routeRoles.' . $route, config('notes.routeRoles.' . $route))
                ?: (TaskSync::client() ? config('tasks.sync.reporter_roles') : config('tasks.defaultRoles', []));
            $roles = is_array($roles) ? $roles : explode('|', $roles);
            $roles = array_values(array_filter(array_map('trim', $roles)));
            if (!$user->hasAnyRole($roles))
                return false;
        }

        return true;
    }

    public static function addMenuButton(?Button $parent = null, array $parameters = []): ?Button
    {
        if (!static::canCreate())
            return null;

        $button = app('menu')->provideButton(array_replace([
            'name' => 'tasks.createFromPage',
            'text' => 'tasks::tasks.createFromPage',
            'icon' => 'plus',
        ], $parameters, [
            // Keep cached/AJAX menu payloads independent of the originating page.
            'href' => route(config('tasks.routePrefix') . 'create', ['iframed' => 1, 'from_page' => 1]),
            'ajax' => true,
            // The lightbox wrapper renders data-type="iframe" on desktop, sidebar
            // and mobile. iframe=true alone only wraps the horizontal navbar.
            'lightbox' => true,
            'iframe' => false,
        ]));

        if ($parent && !$parent->getChildren()->contains($button))
            $parent->addChild($button);

        return $button;
    }
}
