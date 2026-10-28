# Upgrading applications from Notes 2.x

## Required dependency change

Install Tasks and update Notes together:

```bash
composer require ilbronza/notes:^3.0 ilbronza/tasks:^1.0 --with-all-dependencies
php artisan optimize:clear
php artisan migrate
```

If your project pins development branches, update those root constraints to the
versions above in the same change. Applications using only generic notes can use
Notes 3 without Tasks. Notes 3 is a major release because task functionality now
requires a separate Composer package; Notes does not require Tasks back.

Laravel discovers `TasksServiceProvider` automatically. If your application disables
auto-discovery, register `IlBronza\Tasks\TasksServiceProvider` explicitly. It also
registers Notes. Restart long-running queue workers after deploying the new code.
Rebuild your application's usual configuration, route and view caches afterward.

## Preserved compatibility

- All old Task model, controller, middleware and parameters-file names under
  `IlBronza\Notes` are aliases of their new `IlBronza\Tasks` counterparts.
- Existing application Task subclasses and published `notes.models.task` settings
  still work. New `tasks.models.task` values override matching legacy values.
- `notes.routePrefixTasks`, `notes.conversation`, `notes.defaultRoles` and
  `notes.routeRoles` are accepted. Legacy task settings remain readable after boot.
- `/tasks-management` URLs and `notesmanagertasks.*` route names are unchanged.
- The middleware alias `notes.tasks` remains registered.
- `notes::tasks.*` view names remain available. Published views in
  `resources/views/vendor/notes/tasks` are honored, with native
  `resources/views/vendor/tasks/tasks` overrides taking precedence.
- Existing `notes` menu registration continues to provide the task menu.
- Tables keep their names, UUIDs, participant associations and attachment locations.
- The polymorphic alias `Task` remains stable, including when published configuration
  references the old model class. Old fully qualified model names remain resolvable.

Publishing `tasks.php` or renaming application code is optional. Published Tasks
settings take precedence over Notes settings, so copy intended overrides when
publishing the full default file.

## Database migration behavior

The original `2025_05_06_153933_create_note_tasks_table` migration moves to Tasks with
its name preserved, so already migrated applications do not execute it again.
The existing UUID/visibility/order migration remains in Notes for generic notes.
A new Tasks migration creates the participants table only if it does not exist.
No table is renamed or copied, and no existing tasks or messages are rewritten.

Rolling back the new participant migration retains the table, because it may belong
to the previous Notes installation. A full rollback of the original task-table
migration removes both task tables. Use normal backups before database rollbacks.
Updating code does not require a rollback or `migrate:fresh`.

## Authorization extension

Notes now delegates subject-specific permissions through
`IlBronza\Notes\Contracts\AuthorizesNotes`. Task implements that interface.
Notes no longer imports the Task model for authorization. Subjects whose model class
is unavailable deny access, including when their package has been removed.
