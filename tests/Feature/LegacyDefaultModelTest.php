<?php

namespace IlBronza\Tasks\Tests\Feature;

use IlBronza\Tasks\Tests\TestCase;

class LegacyDefaultModelTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);
        $app['config']->set('notes.models.task.class', 'IlBronza\\Notes\\Models\\Task');
    }

    public function test_published_old_default_class_keeps_the_task_morph_alias(): void
    {
        $this->actingAsSuperadmin();
        $task = new \IlBronza\Notes\Models\Task;
        $task->title = 'Published legacy configuration';
        $task->save();
        $this->assertSame('Task', $task->getMorphClass());
        $note = $task->notes()->make();
        $note->notes = 'Existing subject alias';
        $note->save();
        $this->assertSame('Task', $note->fresh()->noteable_type);
        $this->get('/tasks-management')->assertOk()->assertViewHas('tasks', fn ($items) => $items->count() === 1);
    }
}
