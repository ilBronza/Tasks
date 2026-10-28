<?php

namespace IlBronza\Tasks\Tests\Feature;

use IlBronza\Notes\Models\Note;
use IlBronza\Tasks\Models\Task;
use IlBronza\Tasks\Tests\Fixtures\WorkspaceTask;
use IlBronza\Tasks\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class PackageCompatibilityTest extends TestCase
{
    public function test_legacy_class_names_and_configured_subclasses_still_work(): void
    {
        $task = new WorkspaceTask;
        $this->assertInstanceOf(\IlBronza\Notes\Models\Task::class, $task);
        $this->assertSame(WorkspaceTask::class, Task::getProjectClassName());
        $this->assertSame('notes__tasks', $task->getTable());
        $this->assertSame('Task', $task->getMorphClass());
        $this->assertSame(config('tasks.models.task'), config('notes.models.task'));
        $this->assertInstanceOf(
            \IlBronza\Tasks\Http\Controllers\Tasks\TaskCRUD::class,
            new \IlBronza\Notes\Http\Controllers\Tasks\TaskIndexController
        );
        $this->assertInstanceOf(
            \IlBronza\Tasks\Http\ParametersFiles\Fieldsets\TaskShowFieldsetsParameters::class,
            new \IlBronza\Notes\Http\ParametersFiles\Fieldsets\TaskShowFieldsetsParameters
        );
    }

    public function test_existing_urls_model_links_and_view_names_remain_available(): void
    {
        $task = new WorkspaceTask;
        $task->id = 'example';
        $this->assertSame(url('/tasks-management'), route('notesmanagertasks.index'));
        $this->assertSame(url('/tasks-management/example/edit'), $task->getEditUrl());
        $this->assertSame(url('/tasks-management/example/delete'), $task->getDeleteUrl());
        $controller = new \IlBronza\Notes\Http\Controllers\Tasks\TaskIndexController;
        $this->assertSame(url('/tasks-management'), $controller->getIndexUrl());
        $this->assertTrue(view()->exists('notes::tasks.index'));
        $this->assertTrue(view()->exists('tasks::tasks.index'));
        $this->assertSame(__('notes::tasks.manage'), __('tasks::tasks.manage'));
    }

    public function test_legacy_morph_values_resolve_and_keep_private_notes_private(): void
    {
        $staff = $this->actingAsSuperadmin();
        $task = new WorkspaceTask;
        $task->title = 'Legacy task';
        $task->save();
        $client = \IlBronza\AccountManager\Models\User::create([
            'name' => 'Client', 'email' => 'client@example.com', 'password' => 'secret',
        ]);
        $task->participants()->attach($client);

        foreach (['Task', 'IlBronza\\Notes\\Models\\Task', WorkspaceTask::class] as $type) {
            $note = new Note;
            $note->noteable_type = $type;
            $note->noteable_id = $task->getKey();
            $note->notes = 'Internal';
            $note->save();
            $note = $note->fresh();
            $this->assertInstanceOf(Task::class, $note->noteable);
            $this->assertTrue((bool) $note->userCanSee($staff));
            $this->assertFalse($note->userCanSee($client));
            $this->assertFalse($note->userCanUpdate($client));
            $this->assertFalse($note->userCanDelete($client));
            $note->conversation_visibility = 'shared';
            $this->assertTrue($note->userCanSee($client));
        }
    }

    public function test_upgrade_preserves_existing_tasks_notes_and_participants(): void
    {
        $user = $this->actingAsSuperadmin();
        $task = new WorkspaceTask;
        $task->title = 'Before extraction';
        $task->save();
        $task->participants()->attach($user);
        $note = $task->notes()->make();
        $note->notes = 'Existing conversation';
        $note->save();

        // A pre-extraction database contains this table, but not this new migration record.
        DB::table('migrations')->where('migration', '2026_10_14_160001_create_task_participants_table')->delete();
        $migrator = app('migrator');
        $migrator->run([__DIR__ . '/../../database/migrations']);
        $this->assertSame('Before extraction', $task->fresh()->title);
        $this->assertSame('Existing conversation', $task->notes()->firstOrFail()->notes);
        $this->assertSame([$user->getKey()], $task->participants()->pluck('users.id')->all());
        $this->assertSame([], $migrator->run([__DIR__ . '/../../database/migrations']));

        $migration = require __DIR__ . '/../../database/migrations/2026_10_14_160001_create_task_participants_table.php';
        $migration->down();
        $this->assertSame([$user->getKey()], $task->participants()->pluck('users.id')->all());
    }

    public function test_notes_and_tasks_menu_registration_does_not_duplicate_entries(): void
    {
        $this->actingAsSuperadmin();
        $menu = app('menu');
        app('tasks')->manageMenuButtons();
        $button = $menu->getButtonByName('settings')->getChildren()->first(fn ($item) => $item->getName() === 'tasksManager');
        $this->assertNotNull($button);
        app('notes')->tasksMenu($menu, $menu->getButtonByName('settings'));
        app('tasks')->manageMenuButtons();
        $this->assertCount(2, $button->getChildren());
        $this->assertCount(1, $menu->getButtonByName('settings')->getChildren());
    }
}
