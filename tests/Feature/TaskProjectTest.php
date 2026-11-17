<?php

namespace IlBronza\Tasks\Tests\Feature;

use IlBronza\AccountManager\Models\User;
use IlBronza\Tasks\Models\Project;
use IlBronza\Tasks\Tests\Fixtures\WorkspaceTask as Task;
use IlBronza\Tasks\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaskProjectTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);
        $app['config']->set('app.name', 'Idealpack');
    }

    public function test_staff_manage_projects_and_assign_tasks_through_the_crud_forms(): void
    {
        $this->actingAsSuperadmin();
        $default = Project::findOrFail(Project::DEFAULT_ID);
        $this->assertSame('Idealpack', $default->name);
        $url = '/tasks-management/projects';
        $this->get($url)->assertOk()->assertViewIs('datatables::table');
        $this->get($url . '/create')->assertOk()->assertSee('Nuovo progetto');
        $this->post($url, ['name' => 'Magazzino'])->assertRedirect($url);
        $other = Project::where('name', 'Magazzino')->firstOrFail();
        $this->post($url, ['name' => 'Magazzino'])->assertSessionHasErrors('name');
        $this->get($other->getEditUrl())->assertOk()->assertSee('Magazzino');
        $this->put($default->getUpdateUrl(), ['name' => 'Gestionale'])->assertRedirect();

        $this->post('/tasks-management', ['title' => 'Importazione'])->assertRedirect();
        $task = Task::sole();
        $this->assertSame('Gestionale', $task->project->name);
        $this->get($task->getEditUrl())->assertOk()->assertSee('name="project_id"', false)->assertSee('Magazzino');
        $this->put($task->getUpdateUrl(), [
            'title' => $task->title,
            'project_id' => $other->id,
        ])->assertRedirect();
        $this->put($task->getUpdateUrl(), ['title' => 'Importazione aggiornata'])->assertRedirect();
        $this->putJson($task->getUpdateUrl(), [
            'title' => $task->title,
            'project_id' => (string) Str::uuid(),
        ])->assertUnprocessable()->assertJsonValidationErrors('project_id');
        $this->assertTrue($other->tasks()->sole()->is($task));

        $this->post('/tasks-management', [
            'title' => 'Inventario',
            'project_id' => $other->id,
        ])->assertRedirect();
        $this->assertSame(2, $other->tasks()->count());
        $this->post('/tasks-management', [
            'title' => 'Senza indicazione',
            'project_id' => null,
        ])->assertRedirect();
        $this->assertSame('Senza indicazione', $default->tasks()->sole()->title);
    }

    public function test_project_filter_applies_to_open_and_completed_views_and_prefills_create(): void
    {
        $this->actingAsSuperadmin();
        $other = Project::createByName('Magazzino');
        foreach (['open', 'closed'] as $status) {
            foreach ([Project::DEFAULT_ID, $other->id] as $id) {
                $task = new Task;
                $task->title = $status . $id;
                $task->status = $status;
                $task->project_id = $id;
                $task->save();
            }
        }
        $filter = '?project_id=' . $other->id;
        foreach (['/tasks-management' => 'open', '/tasks-management/completed' => 'closed'] as $path => $status) {
            $this->get($path . $filter . '&view=board')->assertOk()
                ->assertViewHas('tasks', fn ($tasks) => $tasks->pluck('title')->all() === [$status . $other->id])
                ->assertViewHas('createUrl', url('/tasks-management/create') . $filter)
                ->assertSee('value="' . $other->id . '" selected', false);
            $this->getJson($path . $filter, ['X-Requested-With' => 'XMLHttpRequest'])
                ->assertOk()->assertJsonCount(1, 'data')->assertSee('Magazzino');
        }
        $this->get('/tasks-management?view=list')->assertOk()
            ->assertViewHas('tasks', fn ($tasks) => $tasks->count() === 2);
        $html = $this->get('/tasks-management/create' . $filter)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option\b(?=[^>]*value="' . $other->id . '")(?=[^>]*selected)[^>]*>/i', $html);
    }

    public function test_participants_only_see_projects_of_visible_tasks(): void
    {
        $this->actingAsSuperadmin();
        $private = Project::createByName('Riservato');
        $task = new Task;
        $task->title = 'Condiviso';
        $task->save();
        $privateTask = new Task;
        $privateTask->title = 'Altra attività';
        $privateTask->project_id = $private->id;
        $privateTask->save();
        $client = User::create([
            'name' => 'Cliente',
            'email' => 'client@example.com',
            'password' => 'secret',
        ]);
        $task->participants()->attach($client);
        $this->actingAs($client);
        $this->get('/tasks-management')->assertOk()->assertSee('Idealpack')->assertDontSee('Riservato');
        $this->get('/tasks-management?project_id=' . $private->id)->assertOk()
            ->assertViewHas('tasks', fn ($tasks) => $tasks->isEmpty())->assertDontSee('Riservato');
        $this->get('/tasks-management/projects')->assertForbidden();
        $this->post('/tasks-management/projects', ['name' => 'Non autorizzato'])->assertForbidden();
    }

    public function test_upgrade_associates_legacy_rows_and_future_database_inserts_with_the_named_default(): void
    {
        $association = require __DIR__ . '/../../database/migrations/2026_11_17_000002_add_project_to_tasks.php';
        $projects = require __DIR__ . '/../../database/migrations/2026_11_17_000001_create_task_projects.php';
        $association->down();
        $projects->down();
        $table = DB::table(config('tasks.models.task.table'));
        $id = (string) Str::uuid();
        $table->insert([
            'id' => $id,
            'title' => 'Storico',
            'status' => 'closed',
            'deleted_at' => now(),
        ]);
        $user = $this->actingAsSuperadmin();
        DB::table(config('tasks.conversation.participantsTable'))->insert([
            'task_id' => $id,
            'user_id' => $user->id,
        ]);
        config(['tasks.defaultProjectName' => 'Progetto cliente']);
        $projects->up();
        $association->up();
        $task = Task::withTrashed()->findOrFail($id);
        $this->assertTrue($task->trashed());
        $this->assertSame('closed', $task->status);
        $this->assertSame('Progetto cliente', $task->project->name);
        $this->assertTrue($task->participants()->sole()->is($user));
        $table->insert(['id' => (string) Str::uuid()]);
        $this->assertSame(2, $table->where('project_id', Project::DEFAULT_ID)->count());
    }
}
