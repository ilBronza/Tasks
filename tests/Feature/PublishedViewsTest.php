<?php

namespace IlBronza\Tasks\Tests\Feature;

use IlBronza\Tasks\Tests\TestCase;

class PublishedViewsTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);
        $app['config']->set('view.paths', [__DIR__ . '/../Fixtures/resources/views']);
    }

    public function test_existing_published_notes_workspace_is_still_used(): void
    {
        $this->actingAsSuperadmin();
        $this->get('/tasks-management')->assertOk()->assertSee('Legacy application task workspace');
    }
}
