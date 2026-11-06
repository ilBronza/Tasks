<?php

namespace IlBronza\Tasks\Tests\Feature;

use IlBronza\AccountManager\Models\User;
use IlBronza\Menu\Helpers\MenuPayloadBuilder;
use IlBronza\Tasks\Facades\Tasks;
use IlBronza\Tasks\Tests\Fixtures\CreatorFieldTaskParameters;
use IlBronza\Tasks\Tests\Fixtures\CannotCreateTask;
use IlBronza\Tasks\Tests\Fixtures\WorkspaceTask as Task;
use IlBronza\Tasks\Tests\TestCase;

class TaskCreateFromPageTest extends TestCase
{
    public function test_helper_registers_one_iframe_action_in_traditional_and_ajax_menus(): void
    {
        $this->actingAsSuperadmin();
        $button = Tasks::addCreateMenuButton();
        $this->assertSame($button, Tasks::addCreateMenuButton());
        $this->assertSame(route('notesmanagertasks.create', ['iframed' => 1, 'from_page' => 1]), $button->getHref());
        $this->assertTrue($button->isAjax());
        foreach (['horizontal', 'vertical'] as $orientation) {
            $html = $button->navbarRender($orientation);
            $this->assertStringContainsString('uk-lightbox', $html);
            $this->assertStringContainsString('data-type="iframe"', $html);
            $this->assertStringContainsString('from_page=1', $html);
        }
        $payload = (new MenuPayloadBuilder(app('menu')))->root();
        $items = collect($payload['navbars'])->flatMap(fn ($navbar) => $navbar['items']);
        $item = $items->firstWhere('name', 'tasks.createFromPage');
        $this->assertNotNull($item);
        $this->assertSame($button->getHref(), $item['href']);
        $this->assertTrue($item['lightbox']);
        $this->assertStringContainsString('data-type="iframe"', $item['html']['sidebar']);
    }

    public function test_iframe_opens_a_prefilled_form_without_creating_a_task(): void
    {
        $this->withoutExceptionHandling();
        $this->actingAsSuperadmin();
        $this->get(route('notesmanagertasks.create', ['iframed' => 1, 'from_page' => 1]))
            ->assertOk()->assertSee('data-task-page-prefill', false)
            ->assertSee('window.parent.location.href', false)
            ->assertSee('name="link"', false)
            ->assertSee(route('notesmanagertasks.store', ['iframed' => 1]), false);
        $this->assertSame(0, Task::count());
    }

    public function test_store_keeps_the_authenticated_creator_and_page_link_then_closes_the_iframe(): void
    {
        $this->withoutExceptionHandling();
        $actor = $this->actingAsSuperadmin();
        $other = User::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'secret']);
        config(['tasks.models.task.parametersFiles.create' => CreatorFieldTaskParameters::class]);
        $link = 'https://example.com/client/42?tab=orders#order-7';
        $this->post(route('notesmanagertasks.store', ['iframed' => 1]), [
            'title' => 'Controllare ordine', 'link' => $link, 'user_id' => $other->getKey(),
        ])->assertOk()->assertViewIs('crud::utilities.messages.closeIframe');
        $task = Task::sole();
        $this->assertSame($actor->getKey(), $task->user_id);
        $this->assertSame($link, $task->link);
    }

    public function test_validation_retry_preserves_the_link_and_does_not_prefill_again(): void
    {
        $this->actingAsSuperadmin();
        $url = route('notesmanagertasks.create', ['iframed' => 1, 'from_page' => 1]);
        $this->from($url)->post(route('notesmanagertasks.store', ['iframed' => 1]), [
            'title' => '', 'link' => 'https://example.com/manually-changed',
        ])->assertRedirect($url)->assertSessionHasErrors('title');
        $this->get($url)->assertOk()->assertSee('https://example.com/manually-changed', false)
            ->assertDontSee('data-task-page-prefill', false);
        $this->assertSame(0, Task::count());
    }

    public function test_action_is_hidden_and_routes_are_forbidden_for_client_accounts(): void
    {
        $client = User::create(['name' => 'Client', 'email' => 'client@example.com', 'password' => 'secret']);
        $this->actingAs($client);
        $this->assertNull(Tasks::addCreateMenuButton());
        $this->getJson(route('notesmanagertasks.create', ['iframed' => 1, 'from_page' => 1]))->assertForbidden();
        $this->postJson(route('notesmanagertasks.store'), ['title' => 'Not allowed'])->assertForbidden();
        $this->assertSame(0, Task::count());
    }

    public function test_menu_helper_respects_route_overrides(): void
    {
        $this->actingAsSuperadmin();
        config(['tasks.routeRoles.notesmanagertasks.store' => ['restricted-team']]);
        $this->assertNull(Tasks::addCreateMenuButton());
        $this->assertNull(app('menu')->getButtonByName('tasks.createFromPage'));
    }

    public function test_standard_create_form_does_not_prefill_the_page_link(): void
    {
        $this->actingAsSuperadmin();
        $this->get(route('notesmanagertasks.create'))->assertOk()
            ->assertDontSee('data-task-page-prefill', false)->assertSee('name="link"', false);
        $this->assertSame(0, Task::count());
    }

    public function test_model_creation_permissions_are_enforced_even_when_route_roles_allow_access(): void
    {
        $this->actingAsSuperadmin();
        config(['tasks.models.task.class' => CannotCreateTask::class]);
        $this->assertNull(Tasks::addCreateMenuButton());
        $this->getJson(route('notesmanagertasks.create', ['iframed' => 1, 'from_page' => 1]))->assertForbidden();
        $this->postJson(route('notesmanagertasks.store'), ['title' => 'Not allowed'])->assertForbidden();
        $this->assertSame(0, Task::count());
    }
}
