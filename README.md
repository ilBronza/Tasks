# Tasks

Laravel task management for the IlBronza packages: workflow states, assignees,
deadlines, conversations, private attachments and client participation.

## Installation

```bash
composer require ilbronza/tasks:^1.0
php artisan migrate
```

The package uses Laravel auto-discovery and depends on `ilbronza/notes:^3.0`.
It is designed for an existing IlBronza Laravel application (CRUD, AccountManager,
Menu and UikitTemplate), including the application's base controller and User model.

Enable the menu with `tasks.models.task.enabled = true` (or the existing
`notes.models.task.enabled` setting). The workspace remains at `/tasks-management`.
Staff can manage tasks and invite existing user accounts as participants. Clients
see only invited tasks and shared messages. Internal messages and their files remain
private. Removing a participant revokes conversation and attachment access.

## Upgrading from Notes

See [UPGRADING.md](UPGRADING.md) before updating Notes 2.x. Applications using tasks
must install **both Notes 3 and Tasks 1** in the same deployment. Existing application
classes, configuration, URLs and data continue to work after that dependency change.

## Configuration

Publishing configuration is optional:

```bash
php artisan vendor:publish --tag=tasks.config
```

- `tasks.models.task.class`: application-specific Task subclass.
- `tasks.models.task.enabled`: show task menu entries (defaults to false, as in Notes).
- `tasks.models.task.table`: defaults to the existing `notes__tasks` table.
- `tasks.routePrefix`: defaults to `notesmanagertasks.`; URL paths remain unchanged.
- `tasks.defaultRoles` / `tasks.routeRoles`: staff route access.
- `tasks.conversation.staffRoles`: optional staff-role override.
- `tasks.conversation.disk`: private attachment disk; defaults to `local`.
- `tasks.conversation.participantsTable`: defaults to `notes__task_participants`.

Unspecified settings fall back to the old Notes settings, then package defaults.
Explicit Tasks settings take precedence. Role lists are replaced, not combined.
Keep client roles out of the staff-role lists.

Use `IlBronza\Tasks\Models\Task` for new code. Existing subclasses of
`IlBronza\Notes\Models\Task` remain valid. `userCanSee`, `userCanUpdate`,
`userCanDelete` and `userCanComment` retain their existing extension points.

Add `tasks` to `menu.serviceProviders` for task-only menu integration. Existing
`notes` registration still includes tasks; registering both does not duplicate them.

## Testing

From the parent package workspace:

```bash
python3 .ibtest/bin/ibtest.py Tasks
python3 .ibtest/bin/ibtest.py Notes
```

The suites cover workspace behavior, staff/client permissions, attachment access,
legacy aliases, native and legacy configuration, model URLs, menu integration,
existing databases and Notes without Tasks. The shared test environment is Laravel
11 / Testbench 9 / SQLite. It emits two upstream PDO deprecations on PHP 8.5.

## License

MIT. Extracted from [ilBronza/Notes](https://github.com/ilBronza/Notes), retaining
its original copyright and license.
