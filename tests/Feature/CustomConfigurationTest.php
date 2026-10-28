<?php

namespace IlBronza\Tasks\Tests\Feature;

use IlBronza\Tasks\Tests\Fixtures\WorkspaceTask;
use IlBronza\Tasks\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class CustomConfigurationTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);
        $app['config']->set('notes.routePrefixTasks', 'legacyTasks.');
        $app['config']->set('notes.models.task.table', 'application_tasks');
        $app['config']->set('notes.conversation.participantsTable', 'application_task_participants');
        $app['config']->set('notes.conversation.disk', 'private');
        $app['config']->set('tasks.defaultRoles', ['superadmin']);
        $app['config']->set('tasks.routeRoles.legacyTasks.closed', ['other-team']);
    }

    public function test_published_legacy_settings_and_new_overrides_work_together(): void
    {
        $this->actingAsSuperadmin();
        $this->assertTrue(Schema::hasTable('application_tasks'));
        $this->assertTrue(Schema::hasTable('application_task_participants'));
        $this->assertFalse(Schema::hasTable('notes__tasks'));
        $this->assertSame(['superadmin'], config('tasks.defaultRoles'));
        $this->assertSame('private', config('tasks.conversation.disk'));
        $this->assertSame(WorkspaceTask::class, config('tasks.models.task.class'));
        $this->assertSame(url('/tasks-management'), route('legacyTasks.index'));
        $this->get('/tasks-management')->assertOk();
        $this->get('/tasks-management/completed')->assertForbidden();
    }
}
