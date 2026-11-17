<?php

namespace IlBronza\Tasks\Tests\Feature;

use IlBronza\AccountManager\Models\User;
use IlBronza\Datatables\DatatablesServiceProvider;
use IlBronza\Notes\NotesServiceProvider;
use IlBronza\Tasks\Models\Project;
use IlBronza\Tasks\Sync\ClientSynchronizer;
use IlBronza\Tasks\Tests\Fixtures\WorkspaceTask as Task;
use IlBronza\Tasks\Tests\TestCase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class TaskSynchronizationTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);
        $app['config']->set('tasks.sync.mode', 'client');
    }

    public function test_client_registers_the_sync_command_with_the_laravel_scheduler(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->sole(fn ($event) => str_contains($event->command ?? '', 'tasks:sync'));
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    private function registerPeer(string $name): array
    {
        $id = (string) Str::uuid();
        $token = Str::random(48);
        DB::table('tasks__sync_installations')->insert([
            'id' => $id,
            'name' => $name,
            'token_hash' => hash('sha256', $token),
        ]);

        return [
            'X-Tasks-Installation' => $id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    private function opening(): array
    {
        return [
            'title' => 'Prima segnalazione',
            'project_id' => Project::DEFAULT_ID,
            'project_name' => 'Magazzino',
            'created_at' => now()->toISOString(),
        ];
    }

    public function test_registration_and_rotation_preserve_the_installation_and_its_history(): void
    {
        config(['tasks.sync.mode' => 'hub']);
        $this->assertSame(0, Artisan::call('tasks:register-client', ['name' => 'Idealpack']));
        preg_match('/TASKS_SYNC_TOKEN=([a-f0-9]+)/', Artisan::output(), $matches);
        $table = DB::table('tasks__sync_installations');
        $peer = $table->sole();
        $this->assertSame(hash('sha256', $matches[1]), $peer->token_hash);
        $headers = [
            'X-Tasks-Installation' => $peer->id,
            'Authorization' => 'Bearer ' . $matches[1],
        ];
        $this->getJson('/api/tasks-sync/v1/changes?after=0', $headers)->assertOk();
        $this->assertSame(1, Artisan::call('tasks:register-client', ['name' => 'Idealpack']));
        $this->assertSame(0, Artisan::call('tasks:register-client', [
            'name' => 'Idealpack',
            '--rotate' => true,
        ]));
        $this->assertSame($peer->id, $table->sole()->id);
        $this->getJson('/api/tasks-sync/v1/changes?after=0', $headers)->assertUnauthorized();
    }

    public function test_a_broken_feed_rolls_back_the_whole_page_and_keeps_the_cursor(): void
    {
        $installation = (string) Str::uuid();
        config([
            'tasks.sync.mode' => 'client',
            'tasks.sync.installation' => $installation,
            'tasks.sync.hub_url' => 'https://portal.test',
            'tasks.sync.token' => 'fixture-token',
        ]);
        $task = new Task;
        $task->title = 'Attività';
        $task->save();
        $update = [
            'revision' => 1,
            'task_id' => $task->id,
            'type' => 'state',
            'payload' => [
                'title' => $task->title,
                'status' => 'closed',
                'end_date' => null,
                'assignee' => null,
                'assignee_key' => null,
                'deleted_at' => null,
            ],
        ];
        $skipped = $update;
        $skipped['revision'] = 3;
        Http::fake([
            '*/tasks/*' => Http::response([
                'accepted' => true,
                'installation' => $installation,
                'task_id' => $task->id,
                'event_id' => $task->id,
            ]),
            '*/changes*' => Http::response([
                'installation' => $installation,
                'changes' => [
                    $update,
                    $skipped,
                ],
            ]),
        ]);
        try {
            app(ClientSynchronizer::class)->run();
            $this->fail('Expected a non-consecutive revision to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Non-consecutive hub revisions.', $exception->getMessage());
        }
        $this->assertSame('open', $task->fresh()->status);
        $this->assertEquals(0, DB::table('tasks__sync_cursors')->value('revision'));
    }

    public function test_hub_derives_task_and_project_ownership_from_credentials(): void
    {
        $origin = (string) Str::uuid();
        $url = '/api/tasks-sync/v1/tasks/' . $origin;
        $a = $this->registerPeer('Alfa');
        $b = $this->registerPeer('Beta');
        $this->putJson($url, $this->opening(), $a)->assertNotFound();
        config(['tasks.sync.mode' => 'hub']);
        $this->putJson($url, $this->opening())->assertUnauthorized();
        $this->putJson($url, $this->opening(), [
            'Authorization' => $a['Authorization'],
            'X-Tasks-Installation' => $b['X-Tasks-Installation'],
        ])->assertUnauthorized();
        $this->putJson($url, $this->opening(), $a)->assertOk();
        $this->putJson($url . '/messages/' . Str::uuid(), [
            'author' => 'Utente',
            'body' => 'Privato di Beta',
            'created_at' => now()->toISOString(),
        ], $b)->assertNotFound();
        $feed = '/api/tasks-sync/v1/changes?after=0';
        $this->getJson($feed, $b)->assertOk()->assertJsonCount(0, 'changes');
        $this->putJson($url, array_merge($this->opening(), [
            'status' => 'closed',
            'assignee_user_id' => 99,
            'sync_installation_id' => $a['X-Tasks-Installation'],
        ]), $b)->assertOk();
        $this->assertSame(2, Task::where('sync_origin_id', $origin)->distinct()->count('project_id'));
        $this->assertSame(2, Task::where('status', 'open')->whereNull('assignee_user_id')->count());
        $this->getJson($feed, $b)->assertOk()->assertJsonCount(1, 'changes');
        $messageUrl = $url . '/messages/' . Str::uuid();
        $message = [
            'author' => 'Alfa',
            'body' => 'Commento Alfa',
            'created_at' => now()->toISOString(),
        ];
        $this->putJson($messageUrl, $message, $a)->assertOk();
        $this->putJson($messageUrl, $message, $a)->assertOk();
        $this->assertSame(1, Task::where('sync_installation_id', $a['X-Tasks-Installation'])->sole()->notes()->count());
        $this->assertSame(0, Task::where('sync_installation_id', $b['X-Tasks-Installation'])->sole()->notes()->count());
    }

    public function test_client_reports_and_follows_portal_updates_across_two_databases(): void
    {
        $clientDb = DB::getDefaultConnection();
        config(['database.connections.portal' => config('database.connections.' . $clientDb)]);
        $migrator = app('migrator');
        $migrator->setConnection('portal');
        app('migration.repository')->createRepository();
        $migrator->run([
            __DIR__ . '/../Fixtures/migrations',
            dirname((new \ReflectionClass(NotesServiceProvider::class))->getFileName()) . '/../database/migrations',
            dirname((new \ReflectionClass(DatatablesServiceProvider::class))->getFileName()) . '/../database/migrations',
            __DIR__ . '/../../database/migrations',
        ]);
        $headers = $this->registerPeer('Idealpack');
        $staff = $this->actingAsSuperadmin();
        auth()->forgetGuards();

        $migrator->setConnection($clientDb);
        config([
            'tasks.sync.mode' => 'client',
            'tasks.sync.installation' => $headers['X-Tasks-Installation'],
            'tasks.sync.token' => substr($headers['Authorization'], 7),
            'tasks.sync.hub_url' => 'https://portal.test',
        ]);
        $reporter = User::create([
            'name' => 'Cliente Idealpack',
            'email' => 'idealpack@example.com',
            'password' => 'secret',
        ]);
        $this->actingAs($reporter);
        $this->get('/tasks-management/create')->assertForbidden();
        $reporter->assignRole(Role::create(['name' => 'taskReporter', 'guard_name' => 'web']));
        $this->get('/tasks-management/create')->assertOk()->assertDontSee('name="status"', false);
        $this->post('/tasks-management', [
            'title' => 'Verificare etichetta',
            'description' => 'Formato 10x20',
            'status' => 'closed',
            'end_date' => '2030-12-01',
            'assignee_user_id' => $reporter->id,
        ])->assertRedirect();
        $local = Task::sole();
        $this->assertSame('open', $local->status);
        $this->assertNull($local->end_date);
        $this->assertNull($local->assignee_user_id);
        $this->assertTrue($local->userCanSee($reporter));
        $localUrl = '/tasks-management/' . $local->id;
        $this->patchJson($localUrl . '/status', ['status' => 'closed'])->assertForbidden();
        $this->putJson($localUrl, ['title' => 'Modificato'])->assertForbidden();
        $this->patchJson($localUrl . '/assignment', ['assignee_user_id' => null])->assertForbidden();
        $this->postJson($localUrl . '/conversation', [
            'visibility' => 'shared',
            'body' => 'Succede al mattino',
        ])->assertCreated();

        $loseAcknowledgement = true;
        Http::fake(function (Request $request) use ($clientDb, $reporter, &$loseAcknowledgement) {
            DB::setDefaultConnection('portal');
            config(['tasks.sync.mode' => 'hub']);
            auth()->forgetGuards();
            try {
                $response = $this->json($request->method(), $request->url(), $request->data(), [
                    'Authorization' => $request->header('Authorization')[0],
                    'X-Tasks-Installation' => $request->header('X-Tasks-Installation')[0],
                ]);
                $response->assertOk();
                if ($loseAcknowledgement) {
                    $loseAcknowledgement = false;
                    return Http::response([], 503);
                }
                return Http::response($response->json(), 200);
            } finally {
                DB::setDefaultConnection($clientDb);
                config(['tasks.sync.mode' => 'client']);
                auth()->forgetGuards();
                $this->actingAs($reporter);
            }
        });
        $sync = app(ClientSynchronizer::class);
        $this->assertNotEmpty($sync->run()['errors']);
        $this->assertSame(2, DB::table('tasks__sync_outbox')->count());
        $this->assertSame(2, $sync->run()['sent']);
        DB::setDefaultConnection('portal');
        config(['tasks.sync.mode' => 'hub']);
        $this->actingAs($staff);
        $central = Task::sole();
        $this->assertSame($local->id, $central->sync_origin_id);
        $this->assertSame('Cliente Idealpack', $central->sync_requester);
        $this->assertSame('Cliente Idealpack', $central->notes()->sole()->task_sync_author);
        $staff->assignRole('taskAssignee');
        $centralUrl = '/tasks-management/' . $central->id;
        $this->patchJson($centralUrl . '/status', ['status' => 'in_progress'])->assertOk();
        $this->put($centralUrl, [
            'title' => $central->title,
            'end_date' => '2030-12-17 14:30:00',
        ])->assertRedirect();
        $this->patchJson($centralUrl . '/assignment', ['assignee_user_id' => $staff->id])->assertOk();
        $hubUrl = '/tasks-management/' . $central->id . '/conversation';
        foreach (['shared' => 'Ricevuto', 'internal' => 'Preventivo riservato'] as $visibility => $body) {
            $this->postJson($hubUrl, [
                'visibility' => $visibility,
                'body' => $body,
            ])->assertCreated();
        }

        DB::setDefaultConnection($clientDb);
        config(['tasks.sync.mode' => 'client']);
        $this->actingAs($reporter);
        $this->assertSame(4, $sync->run()['received']);
        $local->refresh();
        $this->assertSame('in_progress', $local->status);
        $this->assertSame('2030-12-17 14:30:00', $local->end_date->toDateTimeString());
        $this->assertSame($staff->name, $local->workspaceAssignee());
        $this->assertStringStartsWith('hub:', $local->workspaceAssigneeId());
        $this->assertNull($local->assignee_user_id);
        $this->assertNull($local->notes()->where('notes', 'Ricevuto')->sole()->user_id);
        $this->getJson($localUrl . '/conversation')->assertOk()->assertJsonCount(2, 'messages')
            ->assertSee('Ricevuto')->assertSee($staff->name)->assertDontSee('Preventivo riservato');
        $this->assertSame(0, $sync->run()['received']);
        $this->assertSame(0, DB::table('tasks__sync_outbox')->count());
    }
}
