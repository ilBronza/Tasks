<?php

namespace IlBronza\Tasks\Tests\Feature;

use IlBronza\AccountManager\Models\User;
use IlBronza\CRUD\Helpers\ModelManagers\CrudModelStorer;
use IlBronza\CRUD\Helpers\ModelManagers\CrudModelUpdater;
use IlBronza\Tasks\Http\ParametersFiles\Fieldsets\TaskCreateStoreFieldsetsParameters;
use IlBronza\Tasks\Models\TaskAssignment;
use IlBronza\Tasks\Tests\Fixtures\WorkspaceTask as Task;
use IlBronza\Tasks\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskAssignmentTest extends TestCase
{
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = $this->actingAsSuperadmin();
    }

    private function assignee(string $name = 'Incaricato'): User
    {
        $user = User::create(['name' => $name, 'email' => uniqid() . '@example.com', 'password' => 'secret']);
        $user->assignRole('taskAssignee');

        return $user;
    }

    private function task(string $title = 'Consegna'): Task
    {
        $task = new Task;
        $task->title = $title;
        $task->save();

        return $task;
    }

    private function url(Task $task): string
    {
        return '/tasks-management/' . $task->getKey() . '/assignment';
    }

    public function test_assignment_and_optional_comment_share_one_history_record_with_authenticated_author(): void
    {
        $task = $this->task();
        $recipient = $this->assignee();
        $this->patchJson($this->url($task), [
            'assignee_user_id' => $recipient->getKey(), 'comment' => '  Segui la consegna  ',
            'actor_user_id' => $recipient->getKey(), 'user_id' => $recipient->getKey(), 'title' => 'Forged',
        ])->assertOk()->assertJsonPath('assigneeId', (string) $recipient->getKey());

        $this->assertSame($recipient->getKey(), $task->fresh()->assignee_user_id);
        $this->assertSame('Consegna', $task->fresh()->title);
        $this->assertSame($this->actor->getKey(), $task->fresh()->user_id);
        $entry = $task->assignments()->sole();
        $this->assertSame($this->actor->getKey(), $entry->actor_user_id);
        $this->assertSame('Tester', $entry->actor_name);
        $this->assertNull($entry->from_user_id);
        $this->assertSame($recipient->getKey(), $entry->to_user_id);
        $this->assertSame('Segui la consegna', $entry->comment);
        $this->assertNotNull($entry->created_at);
        $this->assertSame(0, $task->notes()->count());
        $this->assertSame(1, Task::count());

        $this->getJson('/tasks-management/' . $task->getKey() . '/conversation')->assertOk()
            ->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.body', 'Segui la consegna')
            ->assertJsonPath('messages.0.author', 'Tester')->assertJsonPath('messages.0.visibility', 'internal')
            ->assertJsonPath('messages.0.assignment.toId', (string) $recipient->getKey());
        $this->get('/tasks-management')->assertOk()->assertViewHas('tasks', fn ($tasks) =>
            $tasks[0]['messageCount'] === 1 && $tasks[0]['assignmentUrl'] === url($this->url($task)));
    }

    public function test_reassigning_and_unassigning_append_history_without_changing_previous_entries(): void
    {
        $task = $this->task();
        $first = $this->assignee('Primo');
        $second = $this->assignee('Secondo');
        $this->patchJson($this->url($task), ['assignee_user_id' => $first->getKey()])->assertOk();
        $this->patchJson($this->url($task), ['assignee_user_id' => $second->getKey(), 'comment' => 'Passaggio'])->assertOk();
        $this->patchJson($this->url($task), ['assignee_user_id' => null, 'comment' => '0'])->assertOk();
        $history = $task->assignments()->orderBy('id')->get();
        $this->assertCount(3, $history);
        $this->assertNull($history[0]->comment);
        $this->assertSame($first->getKey(), $history[1]->from_user_id);
        $this->assertSame($second->getKey(), $history[1]->to_user_id);
        $this->assertSame('Passaggio', $history[1]->comment);
        $this->assertSame($second->getKey(), $history[2]->from_user_id);
        $this->assertNull($history[2]->to_user_id);
        $this->assertSame('0', $history[2]->comment);
        $this->assertNull($task->fresh()->assignee_user_id);
    }

    public function test_unchanged_assignments_do_not_duplicate_history_or_silently_discard_comments(): void
    {
        $task = $this->task();
        $recipient = $this->assignee();
        $this->patchJson($this->url($task), ['assignee_user_id' => $recipient->getKey()])->assertOk();
        $this->patchJson($this->url($task), ['assignee_user_id' => (string) $recipient->getKey()])->assertOk();
        $this->patchJson($this->url($task), ['assignee_user_id' => $recipient->getKey(), 'comment' => 'Nessun cambio'])
            ->assertUnprocessable()->assertJsonValidationErrors('assignment_comment');
        $this->assertSame(1, $task->assignments()->count());
    }

    public function test_invalid_recipients_and_comments_cannot_modify_the_task(): void
    {
        $task = $this->task();
        foreach ([[], ['assignee_user_id' => 999999], ['assignee_user_id' => $this->actor->getKey()],
            ['assignee_user_id' => ['invalid']], ['assignee_user_id' => null, 'comment' => str_repeat('x', 10241)]] as $payload)
            $this->patchJson($this->url($task), $payload)->assertUnprocessable();
        $deleted = $this->assignee();
        $deleted->delete();
        $this->patchJson($this->url($task), ['assignee_user_id' => $deleted->getKey()])->assertUnprocessable();
        $this->assertNull($task->fresh()->assignee_user_id);
        $this->assertSame(0, $task->assignments()->count());
    }

    public function test_permissions_protect_assignment_changes_and_hide_internal_history_from_clients(): void
    {
        $recipient = $this->assignee();
        $task = $this->task();
        $this->patchJson($this->url($task), ['assignee_user_id' => $recipient->getKey(), 'comment' => 'Solo team'])->assertOk();
        foreach (['Hidden', 'Read only'] as $title)
            $this->patchJson($this->url($this->task($title)), ['assignee_user_id' => null])->assertForbidden();
        $this->patchJson('/tasks-management/missing/assignment', ['assignee_user_id' => null])->assertNotFound();
        $client = User::create(['name' => 'Cliente', 'email' => 'client@example.com', 'password' => 'secret']);
        $task->participants()->attach($client);
        $this->actingAs($client);
        $this->patchJson($this->url($task), ['assignee_user_id' => null])->assertForbidden();
        $this->getJson('/tasks-management/' . $task->getKey() . '/conversation')->assertOk()->assertJsonCount(0, 'messages');
        $this->get('/tasks-management')->assertOk()->assertViewHas('assignees', [])
            ->assertViewHas('tasks', fn ($tasks) => $tasks[0]['messageCount'] === 0 && $tasks[0]['assignmentUrl'] === null);
        $this->assertSame(1, $task->assignments()->count());
        $this->app['auth']->forgetGuards();
        $this->patchJson($this->url($task), ['assignee_user_id' => null])->assertUnauthorized();
    }

    public function test_model_saves_track_initial_assignment_and_real_previous_value_from_a_stale_instance(): void
    {
        $first = $this->assignee('Primo');
        $second = $this->assignee('Secondo');
        $third = $this->assignee('Terzo');
        $task = new Task;
        $task->title = 'Iniziale';
        $task->assignee_user_id = $first->getKey();
        $task->assignment_comment = 'Inizio';
        $task->save();
        $stale = $task->fresh();
        $task->assignee_user_id = $second->getKey();
        $task->save();
        $stale->assignee_user_id = $third->getKey();
        $stale->save();
        $stale->title = 'Nuovo titolo';
        $stale->save();
        $history = $task->assignments()->orderBy('id')->get();
        $this->assertCount(3, $history);
        $this->assertSame('Inizio', $history[0]->comment);
        $this->assertNull($history[1]->comment);
        $this->assertSame($second->getKey(), $history[2]->from_user_id);
        $this->assertSame($third->getKey(), $history[2]->to_user_id);
        $this->assertNull($task->assignment_comment);
    }

    public function test_history_failure_rolls_back_the_assignment(): void
    {
        $task = $this->task();
        $recipient = $this->assignee();
        TaskAssignment::creating(fn () => false);
        try {
            $task->assignee_user_id = $recipient->getKey();
            $task->save();
            $this->fail('A refused history write must abort the assignment.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Unable to save the task assignment history.', $exception->getMessage());
        } finally {
            TaskAssignment::flushEventListeners();
        }
        $this->assertNull($task->fresh()->assignee_user_id);
        $this->assertSame(0, $task->assignments()->count());
    }

    public function test_history_preserves_names_after_users_are_renamed_and_soft_deleted(): void
    {
        $task = $this->task();
        $recipient = $this->assignee('Nome originale');
        $this->patchJson($this->url($task), ['assignee_user_id' => $recipient->getKey()])->assertOk();
        $recipient->name = 'Nuovo nome';
        $recipient->save();
        $recipient->delete();
        $this->actor->name = 'Nuovo autore';
        $this->actor->save();
        $entry = $task->assignments()->sole();
        $this->assertSame('Nome originale', $entry->to_name);
        $this->assertSame('Tester', $entry->actor_name);
        $this->getJson('/tasks-management/' . $task->getKey() . '/conversation')->assertOk()
            ->assertJsonPath('messages.0.assignment.to', 'Nome originale')->assertJsonPath('messages.0.author', 'Tester');
    }

    public function test_existing_crud_helpers_store_the_assignment_comment_without_a_task_column(): void
    {
        $recipient = $this->assignee();
        $parameters = new TaskCreateStoreFieldsetsParameters;
        $task = CrudModelStorer::saveByRequest(new Task, $parameters, Request::create('/', 'POST', [
            'title' => 'Dal modulo', 'assignee_user_id' => (string) $recipient->getKey(), 'assignment_comment' => 'Prima assegnazione',
        ]));
        $this->assertSame('Prima assegnazione', $task->assignments()->sole()->comment);
        $task = CrudModelUpdater::saveByRequest($task, $parameters, Request::create('/', 'PUT', [
            'title' => 'Dal modulo', 'assignee_user_id' => null, 'assignment_comment' => 'Rimosso dal modulo',
        ]));
        $this->assertSame(2, $task->assignments()->count());
        $this->assertSame('Rimosso dal modulo', $task->assignments()->latest('id')->first()->comment);
        $this->assertNull($task->fresh()->assignee_user_id);
    }

    public function test_upgrade_preserves_existing_assignment_and_starts_history_at_the_first_new_change(): void
    {
        $task = $this->task();
        $first = $this->assignee('Preesistente');
        $second = $this->assignee('Nuovo');
        // Represent an assignment made before the history table existed.
        DB::table($task->getTable())->where('id', $task->getKey())->update(['assignee_user_id' => $first->getKey()]);
        $migration = require __DIR__ . '/../../database/migrations/2026_11_04_200000_create_task_assignments_table.php';
        $migration->down();
        $migration->up();
        $this->assertSame($first->getKey(), $task->fresh()->assignee_user_id);
        $this->assertSame(0, $task->assignments()->count());
        $this->patchJson($this->url($task), ['assignee_user_id' => $second->getKey()])->assertOk();
        $this->assertSame($first->getKey(), $task->assignments()->sole()->from_user_id);
    }

    public function test_assignment_route_respects_specific_staff_role_overrides(): void
    {
        $task = $this->task();
        config(['tasks.routeRoles.notesmanagertasks.assignment' => ['restricted-team']]);
        $this->patchJson($this->url($task), ['assignee_user_id' => null])->assertForbidden();
    }

    public function test_messages_and_assignments_keep_their_order_even_within_the_same_second(): void
    {
        $this->freezeTime();
        $task = $this->task();
        $recipient = $this->assignee();
        $conversation = '/tasks-management/' . $task->getKey() . '/conversation';
        $this->postJson($conversation, ['body' => 'Prima', 'visibility' => 'internal'])->assertCreated();
        $this->patchJson($this->url($task), ['assignee_user_id' => $recipient->getKey(), 'comment' => 'Passaggio'])->assertOk();
        $this->postJson($conversation, ['body' => 'Dopo', 'visibility' => 'internal'])->assertCreated();
        $this->getJson($conversation)->assertOk()->assertJsonCount(3, 'messages')
            ->assertJsonPath('messages.0.body', 'Prima')->assertJsonPath('messages.1.body', 'Passaggio')
            ->assertJsonPath('messages.2.body', 'Dopo');
    }
}
