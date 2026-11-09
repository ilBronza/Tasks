<?php

namespace IlBronza\Tasks\Tests\Feature;

use IlBronza\CRUD\Helpers\ModelManagers\CrudModelStorer;
use IlBronza\CRUD\Helpers\ModelManagers\CrudModelUpdater;
use IlBronza\Tasks\Http\ParametersFiles\Fieldsets\TaskCreateStoreFieldsetsParameters;
use IlBronza\Tasks\Tests\Fixtures\WorkspaceTask as Task;
use IlBronza\Tasks\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TaskLinkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsSuperadmin();
    }

    public function test_crud_forms_create_update_and_clear_the_link_without_changing_the_task(): void
    {
        $parameters = new TaskCreateStoreFieldsetsParameters;
        $link = 'https://example.com/document?id=42&section=brief#details';
        $task = CrudModelStorer::saveByRequest(new Task, $parameters, Request::create('/', 'POST', [
            'title' => 'Verifica documento', 'description' => 'Descrizione separata', 'link' => $link,
        ]));
        $id = $task->getKey();
        $this->assertSame($link, $task->fresh()->link);
        $this->assertSame('Descrizione separata', $task->fresh()->description);
        $this->get('/tasks-management?view=list')->assertOk()->assertViewHas('tasks', fn ($tasks) => $tasks[0]['link'] === $link);

        $task = CrudModelUpdater::saveByRequest($task, $parameters, Request::create('/', 'PUT', [
            'title' => 'Verifica documento', 'link' => 'http://example.com/revised',
        ]));
        $this->assertSame('http://example.com/revised', $task->fresh()->link);
        $task = CrudModelUpdater::saveByRequest($task, $parameters, Request::create('/', 'PUT', [
            'title' => 'Verifica documento', 'link' => null,
        ]));
        $this->assertNull($task->fresh()->link);
        $this->assertSame($id, $task->getKey());
        $this->assertSame(1, Task::count());
    }

    public function test_form_rejects_invalid_links_without_overwriting_the_existing_link(): void
    {
        $task = new Task;
        $task->title = 'Link esistente';
        $task->link = 'https://example.com/original';
        $task->save();
        foreach (['not a url', 'javascript:alert(1)', 'data:text/html,<script>alert(1)</script>',
            'ftp://example.com/file', '//example.com/path', 'https://example.com/' . str_repeat('a', 2048), ['invalid']] as $link) {
            try {
                CrudModelUpdater::saveByRequest($task, new TaskCreateStoreFieldsetsParameters, Request::create('/', 'PUT', [
                    'title' => 'Link esistente', 'link' => $link,
                ]));
                $this->fail('Invalid links must fail validation.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('link', $exception->errors());
            }
            $this->assertSame('https://example.com/original', $task->fresh()->link);
        }
    }

    public function test_link_migration_preserves_existing_tasks_and_does_not_require_a_link(): void
    {
        $task = CrudModelStorer::saveByRequest(new Task, new TaskCreateStoreFieldsetsParameters, Request::create('/', 'POST', [
            'title' => 'Preesistente', 'description' => 'Dati da conservare',
        ]));
        $migration = require __DIR__ . '/../../database/migrations/2026_11_04_210000_add_link_to_tasks_table.php';
        $migration->down();
        $migration->up();
        $this->assertSame('Preesistente', $task->fresh()->title);
        $this->assertSame('Dati da conservare', $task->fresh()->description);
        $this->assertNull($task->fresh()->link);
    }
}
