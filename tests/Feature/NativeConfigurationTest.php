<?php

namespace IlBronza\Tasks\Tests\Feature;

use IlBronza\Tasks\Models\Task;
use IlBronza\Tasks\Tests\TestCase;

class NativeConfigurationTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);
        $app['config']->set('tasks.routePrefix', 'work.');
        $app['config']->set('tasks.models.task.class', Task::class);
        $app['config']->set('tasks.models.task.enabled', true);
    }

    public function test_native_configuration_takes_precedence_over_legacy_settings(): void
    {
        $this->actingAsSuperadmin();
        $task = new Task;
        $task->title = 'Native task';
        $task->save();
        $this->assertSame(Task::class, config('notes.models.task.class'));
        $this->assertSame('work.', config('notes.routePrefixTasks'));
        $this->assertSame('Task', $task->getMorphClass());
        $this->assertSame(route('work.edit', $task), $task->getEditUrl());
        $this->get('/tasks-management')->assertOk()->assertViewHas('tasks', fn ($items) => $items->count() === 1);
    }
}
