<?php

namespace IlBronza\Tasks;

use IlBronza\Notes\NotesServiceProvider;
use IlBronza\Tasks\Http\Middleware\TasksMiddlewareAccess;
use IlBronza\Tasks\Models\Task;
use IlBronza\Tasks\Support\LegacyConfiguration;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class TasksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(NotesServiceProvider::class);
        $this->app->singleton('tasks', fn () => new Tasks);
        $this->app->booting(fn () => LegacyConfiguration::register());
    }

    public function boot(): void
    {
        // Reflection normalizes the old class_alias name from published notes.php.
        Relation::morphMap(['Task' => (new \ReflectionClass(Task::getProjectClassName()))->getName()]);
        // Make legacy model type hints work even when only the new class was instantiated.
        class_exists(\IlBronza\Notes\Models\Task::class);
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'tasks');
        $legacyViews = array_map(fn ($path) => $path . '/vendor/notes', config('view.paths', []));
        $this->loadViewsFrom([...$legacyViews, __DIR__ . '/../resources/views'], 'tasks');
        $this->app['view']->addNamespace('notes', __DIR__ . '/../resources/views');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->app['router']->aliasMiddleware('tasks.access', TasksMiddlewareAccess::class);
        $this->app['router']->aliasMiddleware('notes.tasks', TasksMiddlewareAccess::class);
        $this->loadRoutesFrom(__DIR__ . '/../routes/tasks.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../config/tasks.php' => config_path('tasks.php')], 'tasks.config');
            $this->publishes([__DIR__ . '/../resources/views' => resource_path('views/vendor/tasks')], 'tasks.views');
        }
    }
}
