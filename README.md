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
Staff land on the CRUD DataTable for both open and completed tasks. Click a title
to open its conversation and attachments, or choose **Bacheca** in the toolbar
for the board. Existing links with `?view=table`, `?view=board` or `?view=list`
still work. Applications can override the table columns via
`tasks.models.task.fieldsGroupsFiles.index`.

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

## Task link

The create/edit form includes an optional `link` field next to the description.
It accepts HTTP/HTTPS URLs up to 2048 characters and can be changed or cleared
later. Staff see a clickable link in the task's details, opening in a new tab.
Like the internal description, the link is not shared with client participants.
Run `php artisan migrate` to add the column; existing tasks start with no link.

## Create a task from the current page

The Tasks menu includes **Crea attività da questa pagina** for users allowed to
create tasks. It opens the usual create form in an iframe. The current page URL,
including query parameters and fragment, is copied into the editable Link field.
The creator is always the authenticated user. Saving creates the task and closes
the popup; opening or cancelling the form does not create a record.

To add the same action to an application menu, use the helper via the facade in
`App\Providers\Helpers\MenuItems::loadProjectMenuItems()`:

```php
use IlBronza\Tasks\Facades\Tasks;

Tasks::addCreateMenuButton();

// Or place a separately named action under an existing parent button:
Tasks::addCreateMenuButton($parentButton, [
    'name' => 'project.createTask',
    'text' => 'tasks::tasks.createFromPage',
    'icon' => 'plus',
]);
```

The helper returns the registered `Button`, or `null` when creation is not
permitted, and reuses existing menu entries by name. It supports the traditional
and progressive/AJAX menu, including the mobile sidebar. The URL is read by the
iframe when opened, so cached menu items never retain another page's link.
No additional JavaScript build or parent-page include is required. Validation
errors preserve the submitted link; application-provided defaults are also kept.

## Assignments and history

Each task keeps one current assignee in `assignee_user_id`. Staff who can update
the task can change or clear that assignee in the workspace's details panel,
with an optional comment. The usual create/edit form also accepts
`assignment_comment`. Eligible recipients come from `getPossibleAssignees()`
(the existing `taskAssignee` role by default).

Every actual change appends one row to `tasks__assignments`, containing the task,
authenticated author, previous assignee, recipient, comment and timestamp.
The task itself retains its ID. Names are also captured at the time of the change,
so later account renames or deletions do not rewrite history. Changes and comments
appear together in the internal conversation; clients cannot see them.

Run `php artisan migrate` when upgrading. Existing assignments are preserved;
history starts with subsequent changes, without inventing past authors or dates.
The history table name can be overridden with `tasks.assignments.table`.

Normal Eloquent saves (including the existing CRUD forms) save the assignment and
history in one transaction. Application code can set `assignment_comment` before
calling `save()`. Without an authenticated user, the author is recorded as `Sistema`.
Bulk query-builder updates and direct SQL bypass model tracking and must not be
used for assignment changes. Re-saving the same assignee creates no history;
a comment without a change is rejected so it cannot be silently discarded.

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
