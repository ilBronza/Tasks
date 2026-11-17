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

## Projects

Run `php artisan migrate` when installing or upgrading. The package creates a
default project named after `config('app.name')` (Laravel's `APP_NAME`). Set
`tasks.defaultProjectName` before migration to choose a different initial name.
Existing tasks, including completed and soft-deleted ones, join this project.
New tasks use it whenever their creator leaves the project unspecified. Renaming
the default project keeps these associations and the default for future tasks.

Staff can open **Progetti** from the board or the task management menu to create
or rename projects. Select **Progetto** when creating or editing a task. The
board and list start on **Tutti i progetti**. Choosing a project filters the
tasks and counters, including completed tasks. **Nuova attività** on a filtered
board preselects that project. Participants can filter their invited tasks and
only see project names associated with visible tasks.

## Client installations and central portal

`TASKS_SYNC_MODE` defaults to `standalone`. Install the same package version
and run `php artisan migrate` on the portal and on each participating client.
Refresh Laravel's configuration cache after changing environment settings on
installations that cache their configuration.
Enable the task menu with `tasks.models.task.enabled` on the applications that
should show it. If the host also uses AccountManager's route authorization,
grant reporters the existing task creation, workspace and conversation routes.

### Portal

Set `TASKS_SYNC_MODE=hub`. Register one installation per customer using a
recognizable name. For example, from the portal run

```sh
php artisan tasks:register-client Idealpack
```

The command prints an installation UUID and a secret token. Give this pair to
that installation. The portal stores the token hash. To replace a credential,
run `php artisan tasks:register-client Idealpack --rotate` and update the client
token. The installation keeps its projects, tasks and sync revisions.

### Client

Configure the customer application with the HTTPS base URL of the portal and
the values printed during registration.

```dotenv
TASKS_SYNC_MODE=client
TASKS_SYNC_HUB_URL=https://portal.example.com
TASKS_SYNC_INSTALLATION=<installation UUID>
TASKS_SYNC_TOKEN=<installation token>
```

Grant reporting users the `taskReporter` role on the web guard. You can create
it with `php artisan permission:create-role taskReporter web` and assign it
through AccountManager. The allowed roles are configurable in
`tasks.sync.reporter_roles` and include `notes`, `administrator` and
`superadmin` by default. Reporters can create a task, read their own or
invited tasks and add shared messages. The portal manages status, due date
and assignee. The client displays the assignee's name independently of its
local user accounts.

The package schedules `php artisan tasks:sync` every minute in client mode.
Enable the usual Laravel scheduler on the client host. You can also run the
command directly to send pending reports, pull updates and check the counts.
Only the client needs outgoing HTTPS access. The sync command itself processes
the database outbox.

### Data exchanged

- New client tasks are queued in the same transaction as the task. The opening
  includes the title, description, optional page link, requester name, creation
  time and local project. The project is identified by installation UUID and
  local project UUID, including the app-named default project.
- A shared text message posted through the task conversation is sent with
  its author label and timestamp. Shared attachments are outside this version
  of the protocol. Staff can continue to attach files to internal portal notes.
- Portal CRUD saves publish the current title, workflow status, due date,
  assignee display name and deletion/restoration state. Staff notes, assignment
  history, tracked time, commits and participant lists stay on their installation.
- Synchronization begins with tasks created in client mode. Enabling the mode
  leaves historical tasks and conversations in their original database.

The stateless API lives at `/api/tasks-sync/v1` on the hub. All three operations
require an `Authorization: Bearer` token and an `X-Tasks-Installation` UUID.

| Method | Path | Operation |
| --- | --- | --- |
| PUT | `tasks/{localTaskUuid}` | Accept an opening once for the authenticated installation. |
| PUT | `tasks/{localTaskUuid}/messages/{localNoteUuid}` | Append one shared text message, with safe retries. |
| GET | `changes?after={revision}` | Read up to 100 consecutive changes belonging to that installation. |

The client acknowledges an outbound item after a successful hub response.
It saves downloaded changes and their revision together. An interrupted
command can resume on the next scheduler tick. Keep the sync tables with the
task database in backups. Use normal Eloquent/CRUD saves for portal changes
so the task and its outgoing revision share a transaction.

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
