# Changelog

## Unreleased

- Open staff task lists in the CRUD DataTable, with links to conversations, the board and completed tasks. Client participants keep their shared workspace.
- Add a reusable menu helper to create tasks in an iframe with the current page link and authenticated creator.
- Use explicit workflow states and eligible assignees when rendering task forms.
- Add an optional HTTP/HTTPS link field to task forms and a clickable link in staff workspace details.
- Change or clear task assignees with an optional comment from the workspace or CRUD forms.
- Save author, previous assignee, recipient, comment and time together in an assignment history row.
- Show assignment history in the staff conversation, preserving existing task IDs and assignments.
- Keep assignment updates and history atomic, including normal Eloquent saves.

## 1.0.0

- Extract task management from ilbronza/notes into its own package.
- Preserve legacy classes, configuration, routes, menu integration and database data.
- Keep task conversation authorization in Tasks through the generic Notes contract.
