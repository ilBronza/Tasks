<?php

namespace IlBronza\Tasks\Tests\Feature;

use IlBronza\Tasks\Tests\Fixtures\WorkspaceTask as Task;
use IlBronza\Tasks\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TaskWorkspaceTest extends TestCase
{
	protected function setUp() : void
	{
		parent::setUp();
		$this->actingAsSuperadmin();
	}

	private function task(string $title = 'Verifica con il cliente', ?string $status = 'open') : Task
	{
		$task = new Task;
		$task->title = $title;
		$task->status = $status;
		$task->save();

		return $task;
	}

	private function url(Task $task) : string
	{
		return '/tasks-management/' . $task->getKey() . '/conversation';
	}

	private function reply(Task $task, array $data)
	{
		return $this->postJson($this->url($task), array_merge(['visibility' => 'shared'], $data));
	}

	private function upload(string $name, string $contents = 'Confermo la proposta') : UploadedFile
	{
		$directory = $this->app->storagePath('framework/testing/uploads');
		if (!is_dir($directory))
			mkdir($directory, 0777, true);
		$path = $directory . '/' . uniqid('notes-', true);
		file_put_contents($path, $contents);

		return new UploadedFile($path, $name, null, null, true);
	}

	public function test_workspace_separates_active_and_completed_tasks_and_escapes_data() : void
	{
		$this->withoutExceptionHandling();
		$active = $this->task('Cliente </script><script>alert(1)</script>');
		$closed = $this->task('Consegnato', 'closed');
		$this->task('Hidden');

		$this->get('/tasks-management')->assertOk()->assertViewIs('tasks::tasks.index')
			->assertViewHas('tasks', fn ($tasks) => $tasks->pluck('id')->all() === [$active->getKey()])
			->assertDontSee('</script><script>alert(1)</script>', false);
		$this->get('/tasks-management/completed')->assertOk()
			->assertViewHas('tasks', fn ($tasks) => $tasks->pluck('id')->all() === [$closed->getKey()]);
	}

	public function test_open_scope_keeps_null_status_inside_other_constraints() : void
	{
		$visible = $this->task('Visible', null);
		$deleted = $this->task('Deleted', null);
		$deleted->delete();
		$this->task('Another', null);

		$this->assertSame([$visible->getKey()], Task::where('title', 'Visible')->notClosed()->pluck('id')->all());
		$this->assertNotContains($deleted->getKey(), Task::notClosed()->pluck('id')->all());
	}

	public function test_status_change_validates_and_preserves_other_fields() : void
	{
		$task = $this->task();
		$this->patchJson('/tasks-management/' . $task->getKey() . '/status', ['status' => 'closed', 'title' => 'Forged'])
			->assertOk()->assertJson(['id' => $task->getKey(), 'status' => 'closed']);
		$this->assertSame('closed', $task->fresh()->status);
		$this->assertSame($task->title, $task->fresh()->title);
		$this->patchJson('/tasks-management/' . $task->getKey() . '/status', ['status' => 'invalid'])->assertUnprocessable();
		$this->patchJson('/tasks-management/missing/status', ['status' => 'open'])->assertNotFound();
		$readOnly = $this->task('Read only');
		$this->patchJson('/tasks-management/' . $readOnly->getKey() . '/status', ['status' => 'closed'])->assertForbidden();
	}

	public function test_messages_keep_authenticated_authorship_and_task_context() : void
	{
		$task = $this->task();
		$other = $this->task('Altro cliente');
		$this->reply($task, ['body' => 'Ci risentiamo domani', 'user_id' => 999, 'noteable_id' => $other->getKey()])
			->assertCreated()->assertJsonPath('message.author', 'Tester')->assertJsonPath('message.isOwn', true);
		$this->reply($task, ['body' => 'true'])->assertCreated()->assertJsonPath('message.body', 'true');
		$this->getJson($this->url($task))->assertOk()->assertJsonCount(2, 'messages')->assertJsonPath('messages.0.body', 'Ci risentiamo domani');
		$this->getJson($this->url($other))->assertOk()->assertJsonCount(0, 'messages');
		$this->assertDatabaseHas('ibnotes', ['noteable_id' => $task->getKey(), 'user_id' => auth()->id()]);
		$this->reply($task, ['body' => '   '])->assertUnprocessable();
		$this->reply($task, ['body' => str_repeat('x', 10241)])->assertUnprocessable();
	}

	public function test_task_permissions_guard_both_reading_and_replies() : void
	{
		$hidden = $this->task('Hidden');
		$this->getJson($this->url($hidden))->assertForbidden();
		$this->postJson($this->url($hidden), ['body' => 'Non autorizzato'])->assertForbidden();
		$readOnly = $this->task('No replies');
		$this->getJson($this->url($readOnly))->assertOk();
		$this->postJson($this->url($readOnly), ['body' => 'Non autorizzato'])->assertForbidden();
		$this->getJson('/tasks-management/missing/conversation')->assertNotFound();
		$this->app['auth']->forgetGuards();
		$this->getJson($this->url($readOnly))->assertUnauthorized();
	}

	public function test_attachments_are_saved_and_downloaded_only_under_their_own_task() : void
	{
		Storage::fake('local');
		$task = $this->task();
		$response = $this->post($this->url($task), ['visibility' => 'shared', 'files' => [$this->upload('riscontro.txt')]], ['Accept' => 'application/json'])
			->assertCreated()->assertJsonPath('message.attachments.0.name', 'riscontro.txt');
		$url = $response->json('message.attachments.0.url');
		$this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertDownload('riscontro.txt');
		$other = $this->task('Altro cliente');
		$this->get(str_replace($task->getKey(), $other->getKey(), $url))->assertNotFound();
		$this->post($this->url($task), ['visibility' => 'shared', 'files' => [$this->upload('script.php', '<?php echo 1;')]], ['Accept' => 'application/json'])->assertUnprocessable();
	}

	public function test_clients_only_see_invited_tasks_and_shared_messages() : void
	{
		$task = $this->task();
		$task->description = 'Internal description';
		$task->commit = 'private-commit';
		$task->save();
		$this->reply($task, ['body' => 'Proposta da valutare'])->assertCreated();
		$this->reply($task, ['body' => 'Nota riservata', 'visibility' => 'internal'])->assertCreated();
		$legacy = $task->notes()->make();
		$legacy->notes = 'Nota precedente alla condivisione';
		$legacy->save();
		$other = $this->task('Altro cliente');
		$client = \IlBronza\AccountManager\Models\User::create(['name' => 'Cliente', 'email' => 'cliente@example.com', 'password' => 'secret']);
		$this->postJson('/tasks-management/' . $task->getKey() . '/participants', ['email' => $client->email])->assertOk();
		$this->actingAs($client);

		$this->get('/tasks-management')->assertOk()->assertViewHas('tasks', function ($tasks) use ($task) {
			return $tasks->pluck('id')->all() === [$task->getKey()] && $tasks[0]['description'] === '' && $tasks[0]['commit'] === null && $tasks[0]['messageCount'] === 1;
		});
		$this->getJson($this->url($task))->assertOk()->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.body', 'Proposta da valutare');
		$this->reply($task, ['body' => 'Confermo'])->assertCreated()->assertJsonPath('message.author', 'Cliente');
		$this->reply($task, ['body' => 'Nota cliente', 'visibility' => 'internal'])->assertUnprocessable();
		$this->getJson($this->url($other))->assertForbidden();
		$this->reply($other, ['body' => 'Tentativo'])->assertForbidden();
		$this->get('/tasks-management?view=table')->assertForbidden();
		$this->get('/tasks-management/create')->assertForbidden();
		$this->patchJson('/tasks-management/' . $task->getKey() . '/status', ['status' => 'closed'])->assertForbidden();
		$this->postJson('/tasks-management/' . $task->getKey() . '/participants', ['email' => 'someone@example.com'])->assertForbidden();
	}

	public function test_revoked_participation_blocks_messages_and_internal_attachments_are_private() : void
	{
		Storage::fake('local');
		$task = $this->task();
		$client = \IlBronza\AccountManager\Models\User::create(['name' => 'Cliente', 'email' => 'cliente@example.com', 'password' => 'secret']);
		$task->participants()->attach($client);
		$internal = $this->post($this->url($task), ['visibility' => 'internal', 'files' => [$this->upload('riservato.txt', 'Riservato')]], ['Accept' => 'application/json'])->assertCreated();
		$this->actingAs($client);
		$this->get($internal->json('message.attachments.0.url'))->assertForbidden();
		$note = $task->notes()->firstOrFail();
		$this->assertFalse($note->userCanSee($client));
		$this->assertFalse($note->userCanUpdate($client));
		$this->assertFalse($note->userCanDelete($client));
		$this->get('/test-media/' . $note->media()->firstOrFail()->getKey())->assertForbidden();
		$task->participants()->detach($client);
		$this->getJson($this->url($task))->assertForbidden();
		$this->reply($task, ['body' => 'Non più autorizzato'])->assertForbidden();
	}

	public function test_staff_route_role_overrides_are_not_bypassed_by_participant_access() : void
	{
		config(['notes.routeRoles.notesmanagertasks.index' => ['restricted-team']]);
		$this->get('/tasks-management')->assertForbidden();
	}
}
