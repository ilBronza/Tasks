<?php

namespace IlBronza\Tasks;

use IlBronza\Buttons\Button;
use IlBronza\CRUD\Providers\RouterProvider\RoutedObjectInterface;
use IlBronza\CRUD\Traits\IlBronzaPackages\IlBronzaPackagesTrait;
use IlBronza\Tasks\Helpers\TaskCreationHelper;

class Tasks implements RoutedObjectInterface
{
    use IlBronzaPackagesTrait;

    public static $packageConfigPrefix = 'tasks';

    public function addCreateMenuButton(?Button $parent = null, array $parameters = []): ?Button
    {
        return TaskCreationHelper::addMenuButton($parent, $parameters);
    }

    public function tasksMenu($menu, $settingsButton)
    {
        if ($settingsButton->getChildren()->contains(fn ($button) => $button->getName() === 'tasksManager'))
            return;

        $tasksButton = $menu->provideButton([
            'name' => 'tasksManager', 'icon' => 'message', 'text' => 'tasks::tasks.manage',
        ]);
        $settingsButton->addChild($tasksButton);

        foreach (['index' => ['list', 'list'], 'closed' => ['check', 'closed']] as $route => [$icon, $label]) {
            $tasksButton->addChild($menu->provideButton([
                'name' => 'tasks.' . $route,
                'icon' => $icon,
                'href' => route(config('tasks.routePrefix') . $route),
                'text' => 'tasks::tasks.' . $label,
            ]));
        }

        $this->addCreateMenuButton($tasksButton);
    }

    public function manageMenuButtons()
    {
        if (!config('tasks.models.task.enabled') || !$menu = app('menu'))
            return;

        $taskClass = config('tasks.models.task.class');
        if (auth()->check() && !$taskClass::isTaskStaff(auth()->user())) {
            $menu->provideButton([
                'name' => 'tasks.myTasks', 'icon' => 'comments',
                'href' => route(config('tasks.routePrefix') . 'index'),
                'text' => 'tasks::tasks.myTasks',
            ]);
        }

        $settings = $menu->provideButton([
            'text' => 'menu::menu.settings', 'name' => 'settings',
            'icon' => 'gear', 'roles' => ['administrator'],
        ]);
        $settings->setFirst();
        $this->tasksMenu($menu, $settings);
    }
}
