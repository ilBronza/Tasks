<script>
(() => {
    'use strict';
    const root = document.querySelector('[data-task-workspace]');
    if (!root || root.dataset.initialized) return;
    root.dataset.initialized = 'true';
    const config = JSON.parse(root.querySelector('[data-task-config]').textContent);
    const t = config.text;
    const content = root.querySelector('[data-task-content]');
    const dialog = root.querySelector('[data-detail]');
    const search = root.querySelector('[data-search]');
    const assigneeSelect = root.querySelector('[data-assignee]');
    const storageKey = 'notes.tasks.view.' + config.currentUserId;
    const pending = new Set();
    const collapsed = new Set();
    const conversations = new Map();
    const drafts = new Map();
    const replying = new Set();
    const conversationVersions = new Map();
    let tasks = config.tasks;
    let view = config.initialView || 'list';
    let filter = 'all';
    let activeTaskId = null;
    let draggedId = null;
    let lastDetailTrigger = null;
    let detailTab = 'conversation';
    let toastTimer;
    try { if (!config.initialView) view = localStorage.getItem(storageKey) || 'list'; } catch (_) {}
    if (!['list', 'board'].includes(view)) view = 'list';

    const paths = {
        tasks: '<rect x="4" y="3" width="16" height="18" rx="3"/><path d="m8 9 1.5 1.5L12 8m2 2h3M8 15h1m4 0h4"/>',
        plus: '<path d="M12 5v14M5 12h14"/>',
        list: '<path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/>',
        board: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16M15 4v16"/>',
        search: '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/>',
        check: '<path d="m5 12 4 4L19 6"/>',
        close: '<path d="m6 6 12 12M6 18 18 6"/>',
        user: '<circle cx="12" cy="8" r="3"/><path d="M5 21v-3a7 7 0 0 1 14 0v3"/>',
        calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
        arrow: '<path d="M7 17 17 7M7 7h10v10"/>',
        message: '<path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H4l-2 2V11.5A8.5 8.5 0 0 1 10.5 3h2A8.5 8.5 0 0 1 21 11.5Z"/><path d="M7 9h9M7 13h6"/>',
        paperclip: '<path d="m8 13 7-7a3 3 0 0 1 4 4l-9 9a5 5 0 0 1-7-7l9-9M6 15l8-8"/>',
        refresh: '<path d="M20 7v5h-5M4 17v-5h5M6 7a7 7 0 0 1 12-2l2 3M4 16l2 3a7 7 0 0 0 12-2"/>',
    };
    function node(tag, className, text) {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== undefined && text !== null) element.textContent = text;
        return element;
    }
    function icon(name) {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '1.7');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.setAttribute('aria-hidden', 'true');
        svg.innerHTML = paths[name] || paths.tasks;
        return svg;
    }
    root.querySelectorAll('[data-icon]').forEach(element => element.append(icon(element.dataset.icon)));
    const dotColors = { open: '', in_progress: 'purple', not_manageable: 'amber', closed: 'green' };
    function dot(status) { return node('span', 'nt-dot' + (dotColors[status] ? ' nt-dot-' + dotColors[status] : '')); }
    function isOverdue(task) { return task.status !== 'closed' && task.dueDate && task.dueDate < config.today; }
    function isToday(task) { return task.status !== 'closed' && task.dueDate === config.today; }
    function isMine(task) { return task.assigneeId === config.currentUserId; }
    function badge(task) {
        const element = node('span', 'nt-status', config.statuses[task.status] || task.status);
        element.dataset.status = task.status;
        return element;
    }
    function person(task) {
        const element = node('span', 'nt-person');
        const avatar = node('span', 'nt-avatar' + (!task.assignee ? ' is-unassigned' : ''));
        avatar.setAttribute('aria-hidden', 'true');
        if (task.assignee) {
            const parts = task.assignee.trim().split(/\s+/);
            avatar.textContent = ((parts[0]?.[0] || '') + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toLocaleUpperCase();
            avatar.dataset.tone = String(Array.from(task.assigneeId).reduce((sum, char) => sum + char.charCodeAt(0), 0) % 4);
        } else avatar.append(icon('user'));
        element.append(avatar, node('span', 'nt-person-name', task.assignee || t.unassigned));
        element.title = task.assignee || t.unassigned;
        return element;
    }
    function due(task) {
        const element = node('span', 'nt-due' + (isOverdue(task) ? ' is-overdue' : isToday(task) ? ' is-today' : ''));
        element.append(icon('calendar'), document.createTextNode(isToday(task) ? t.today : task.dueLabel || t.noDueDate));
        element.title = isOverdue(task) ? t.overdue + ' · ' + task.dueLabel : t.due;
        return element;
    }
    function completeButton(task) {
        const button = node('button', 'nt-complete' + (task.status === 'closed' ? ' is-completed' : ''));
        button.type = 'button';
        button.dataset.completeId = task.id;
        button.title = task.status === 'closed' ? t.reopen : t.complete;
        if (!task.canUpdate) button.title = config.statuses[task.status] || task.status;
        button.setAttribute('aria-label', button.title + ': ' + task.title);
        button.disabled = !task.canUpdate || pending.has(task.id);
        button.append(icon('check'));
        button.addEventListener('click', () => updateStatus(task, task.status === 'closed' ? 'open' : 'closed'));
        return button;
    }
    function taskMain(task, showDescription) {
        const main = node('div', 'nt-task-main');
        const text = node('div');
        text.style.minWidth = '0';
        const title = node('button', 'nt-task-title', task.title);
        title.type = 'button';
        title.dataset.detailId = task.id;
        title.setAttribute('aria-haspopup', 'dialog');
        title.addEventListener('click', () => openDetail(task, title));
        text.append(title);
        if (task.messageCount) {
            const count = node('span', 'nt-message-count');
            count.append(icon('message'), document.createTextNode(task.messageCount));
            count.setAttribute('aria-label', t.messages.replace(':count', task.messageCount));
            text.append(count);
        }
        if (showDescription && task.description) text.append(node('span', 'nt-task-subtitle', task.description));
        main.append(completeButton(task), text);
        return main;
    }
    function visibleTasks() {
        const query = search.value.trim().toLocaleLowerCase();
        return tasks.filter(task => {
            if (filter === 'mine' && !isMine(task)) return false;
            if (filter === 'overdue' && !isOverdue(task)) return false;
            if (assigneeSelect.value === 'unassigned' && task.assigneeId) return false;
            if (!['all', 'unassigned'].includes(assigneeSelect.value) && task.assigneeId !== assigneeSelect.value) return false;
            return !query || [task.title, task.description, task.assignee || ''].join(' ').toLocaleLowerCase().includes(query);
        });
    }
    function hasFilters() { return filter !== 'all' || search.value.trim() || assigneeSelect.value !== 'all'; }
    function refreshStats() {
        const stats = root.querySelector('[data-stats]');
        const definitions = [
            [config.showClosed ? t.closedCount : t.openCount, tasks.length, config.showClosed ? 'green' : ''],
            [config.showClosed ? t.mine : t.progressCount, tasks.filter(config.showClosed ? isMine : task => task.status === 'in_progress').length, 'purple'],
            [config.showClosed ? t.unassigned : t.overdue, tasks.filter(config.showClosed ? task => !task.assigneeId : isOverdue).length, config.showClosed ? '' : 'red'],
            [config.showClosed ? t.noDueDate : t.dueToday, tasks.filter(config.showClosed ? task => !task.dueDate : isToday).length, 'amber'],
        ];
        stats.replaceChildren(...definitions.map(([label, count, color]) => {
            const stat = node('div', 'nt-stat');
            const caption = node('span', 'nt-stat-label');
            caption.append(node('span', 'nt-dot' + (color ? ' nt-dot-' + color : '')), document.createTextNode(label));
            stat.append(caption, node('strong', '', count));
            return stat;
        }));
        root.querySelector('[data-count="all"]').textContent = tasks.length;
        const mine = root.querySelector('[data-count="mine"]');
        if (mine) mine.textContent = tasks.filter(isMine).length;
        const overdue = root.querySelector('[data-count="overdue"]');
        if (overdue) overdue.textContent = tasks.filter(isOverdue).length;
    }
    function emptyState() {
        const empty = node('div', 'nt-empty');
        const image = node('span', 'nt-empty-icon');
        image.append(icon(hasFilters() ? 'search' : 'tasks'));
        empty.append(image, node('h2', '', hasFilters() ? t.noResults : config.showClosed ? t.emptyClosedTitle : t.emptyTitle));
        empty.append(node('p', '', hasFilters() ? t.noResultsDescription : config.showClosed ? t.emptyClosedDescription : t.emptyDescription));
        if (hasFilters()) {
            const reset = node('button', 'nt-button', t.reset);
            reset.type = 'button';
            reset.addEventListener('click', () => { filter = 'all'; search.value = ''; assigneeSelect.value = 'all'; render(); search.focus(); });
            empty.append(reset);
        } else if (config.canCreate && !config.showClosed) {
            const create = node('a', 'nt-button nt-button-primary', t.new);
            create.href = config.createUrl;
            empty.append(create);
        }
        return empty;
    }
    function renderList(filtered) {
        const list = node('div', 'nt-list');
        const head = node('div', 'nt-list-head');
        [t.activity, t.assignee, t.due, t.status].forEach(label => head.append(node('span', '', label)));
        list.append(head);
        Object.entries(config.statuses).forEach(([status, label]) => {
            const groupTasks = filtered.filter(task => task.status === status);
            if (!groupTasks.length) return;
            const group = node('details', 'nt-list-group');
            group.open = !collapsed.has(status);
            group.addEventListener('toggle', () => { if (group.isConnected) group.open ? collapsed.delete(status) : collapsed.add(status); });
            const heading = node('summary', 'nt-group-heading');
            heading.append(dot(status), document.createTextNode(label), node('span', 'nt-group-count', groupTasks.length));
            group.append(heading);
            groupTasks.forEach(task => {
                const row = node('div', 'nt-task-row');
                row.append(taskMain(task, true), person(task), due(task), badge(task));
                group.append(row);
            });
            list.append(group);
        });
        return list;
    }
    function renderBoard(filtered) {
        const board = node('div', 'nt-board');
        Object.entries(config.statuses).forEach(([status, label]) => {
            const column = node('section', 'nt-column');
            column.dataset.status = status;
            column.setAttribute('aria-label', label);
            const columnTasks = filtered.filter(task => task.status === status);
            const heading = node('div', 'nt-column-title');
            heading.append(dot(status), document.createTextNode(label), node('span', 'nt-group-count', columnTasks.length));
            column.append(heading);
            columnTasks.forEach(task => {
                const card = node('article', 'nt-card');
                card.draggable = task.canUpdate && !pending.has(task.id);
                card.append(taskMain(task, false));
                if (task.description) card.append(node('p', 'nt-card-description', task.description));
                const footer = node('div', 'nt-card-footer');
                footer.append(due(task), person(task));
                card.append(footer);
                card.addEventListener('dragstart', event => {
                    if (!card.draggable) { event.preventDefault(); return; }
                    draggedId = task.id;
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', task.id);
                    card.classList.add('is-dragging');
                });
                card.addEventListener('dragend', clearDrag);
                column.append(card);
            });
            if (!columnTasks.length) column.append(node('div', 'nt-column-empty', !config.isStaff ? t.emptyGroup : status === 'closed' && !config.showClosed ? t.completeHint : config.showClosed && status !== 'closed' ? t.reopenHint : t.dropHint));
            column.addEventListener('dragover', event => {
                if (!draggedId) return;
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                column.classList.add('is-drop-target');
            });
            column.addEventListener('dragleave', event => { if (!column.contains(event.relatedTarget)) column.classList.remove('is-drop-target'); });
            column.addEventListener('drop', event => {
                if (!draggedId) return;
                event.preventDefault();
                const task = tasks.find(task => task.id === draggedId);
                clearDrag();
                if (task) updateStatus(task, status);
            });
            board.append(column);
        });
        return board;
    }
    function clearDrag() {
        draggedId = null;
        root.querySelectorAll('.is-drop-target, .is-dragging').forEach(element => element.classList.remove('is-drop-target', 'is-dragging'));
    }
    function render() {
        const filtered = visibleTasks();
        refreshStats();
        root.querySelectorAll('[data-view]').forEach(button => {
            button.classList.toggle('is-active', button.dataset.view === view);
            button.setAttribute('aria-pressed', String(button.dataset.view === view));
        });
        root.querySelectorAll('[data-filter]').forEach(button => {
            button.classList.toggle('is-active', button.dataset.filter === filter);
            button.setAttribute('aria-pressed', String(button.dataset.filter === filter));
        });
        root.querySelector('[data-result-count]').textContent = t.results.replace(':count', filtered.length);
        root.querySelector('[data-board-hint]').hidden = !config.isStaff || view !== 'board' || !filtered.length;
        content.replaceChildren(!filtered.length ? emptyState() : view === 'board' ? renderBoard(filtered) : renderList(filtered));
    }
    function toast(message, error = false) {
        const element = root.querySelector('[data-toast]');
        (dialog.open ? dialog : root).append(element);
        clearTimeout(toastTimer);
        element.textContent = message;
        element.classList.toggle('is-error', error);
        element.setAttribute('role', error ? 'alert' : 'status');
        element.hidden = false;
        toastTimer = setTimeout(() => { element.hidden = true; }, error ? 8000 : 3500);
    }
    async function updateStatus(task, status) {
        if (!task.canUpdate || pending.has(task.id) || task.status === status) return;
        pending.add(task.id);
        const focusWasInDialog = dialog.open;
        render();
        if (activeTaskId === task.id) renderDetail(task);
        try {
            const response = await fetch(task.statusUrl, {
                method: 'PATCH', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrfToken },
                body: JSON.stringify({ status }),
            });
            if (!response.ok) throw new Error([401, 419].includes(response.status) ? t.sessionExpired : response.status === 403 ? t.forbidden : t.saveError);
            const result = await response.json();
            if (result.id !== task.id || !Object.prototype.hasOwnProperty.call(config.statuses, result.status)) throw new Error(t.saveError);
            task.status = result.status;
            if ((task.status === 'closed') !== config.showClosed) {
                tasks = tasks.filter(item => item.id !== task.id);
                if (activeTaskId === task.id) dialog.close();
            }
            toast(task.status === 'closed' ? t.completed : t.saved);
        } catch (error) {
            toast(error.message || t.saveError, true);
        } finally {
            pending.delete(task.id);
            render();
            if (activeTaskId === task.id && dialog.open) renderDetail(task);
            if (!focusWasInDialog || !dialog.open) {
                const target = Array.from(root.querySelectorAll('[data-complete-id]')).find(element => element.dataset.completeId === task.id);
                (target || root.querySelector('[data-view="' + view + '"]')).focus();
            }
        }
    }
    function property(label, value) {
        const row = node('div', 'nt-detail-property');
        row.append(node('span', 'nt-detail-label', label), typeof value === 'string' ? node('span', '', value) : value);
        return row;
    }
    function renderDetail(task) {
        const panel = root.querySelector('[data-detail-content]');
        const heading = node('h2', '', task.title);
        heading.id = 'nt-detail-title';
        const status = node('select');
        status.setAttribute('aria-label', t.status);
        status.disabled = !task.canUpdate || pending.has(task.id);
        Object.entries(config.statuses).forEach(([key, label]) => { const option = node('option', '', label); option.value = key; status.append(option); });
        status.value = task.status;
        status.addEventListener('change', () => updateStatus(task, status.value));
        const description = node('section', 'nt-detail-section');
        description.append(node('h3', '', t.description), node('p', 'nt-detail-description', task.description || t.noDescription));
        const actions = node('div', 'nt-detail-actions');
        if (task.canUpdate) {
            const edit = node('a', 'nt-button', t.edit);
            edit.href = task.editUrl;
            actions.append(edit);
        }
        if (config.isStaff) {
            const notes = node('a', 'nt-button', t.notes);
            notes.href = task.notesUrl;
            actions.append(notes);
        }
        const tabs = node('div', 'nt-detail-tabs');
        tabs.setAttribute('role', 'group');
        tabs.setAttribute('aria-label', t.details);
        ['conversation', 'details'].forEach(key => {
            const tab = node('button', 'nt-tab' + (detailTab === key ? ' is-active' : ''), t[key]);
            tab.type = 'button';
            tab.setAttribute('aria-pressed', String(detailTab === key));
            tab.addEventListener('click', () => { detailTab = key; renderDetail(task); });
            tabs.append(tab);
        });
        panel.replaceChildren(badge(task), heading, tabs);
        if (detailTab === 'conversation') {
            renderConversation(task, panel);
            return;
        }
        panel.append(property(t.status, status), property(t.assignee, person(task)), property(t.due, due(task)));
        if (task.startDate) panel.append(property(t.startDate, task.startDate));
        panel.append(description);
        if (task.minutes !== null || task.commit) {
            const tracking = node('section', 'nt-detail-section');
            tracking.append(node('h3', '', t.tracking));
            if (task.minutes !== null) tracking.append(property(t.minutes, String(task.minutes)));
            if (task.commit) tracking.append(property(t.commit, node('code', '', task.commit)));
            panel.append(tracking);
        }
        panel.append(actions);
    }
    function openDetail(task, trigger) {
        activeTaskId = task.id;
        lastDetailTrigger = trigger;
        detailTab = 'conversation';
        renderDetail(task);
        dialog.showModal();
        loadConversation(task);
    }
    function getDraft(task) {
        if (!drafts.has(task.id)) drafts.set(task.id, { body: '', files: [], visibility: 'shared' });
        return drafts.get(task.id);
    }
    function renderConversation(task, panel) {
        const participants = node('div');
        participants.dataset.participants = task.id;
        panel.append(participants);
        renderParticipants(task);
        const toolbar = node('div', 'nt-conversation-heading');
        toolbar.append(node('p', '', t.conversationIntro));
        const refresh = node('button', 'nt-icon-button');
        refresh.type = 'button';
        refresh.title = t.refreshConversation;
        refresh.setAttribute('aria-label', t.refreshConversation);
        refresh.append(icon('refresh'));
        refresh.addEventListener('click', () => loadConversation(task));
        toolbar.append(refresh);
        const messages = node('div', 'nt-messages');
        messages.dataset.messages = task.id;
        messages.setAttribute('aria-live', 'polite');
        panel.append(toolbar, messages);
        renderMessages(task);
        if (!task.canComment) return;
        const draft = getDraft(task);
        const form = node('form', 'nt-composer');
        const visibility = node('select', 'nt-visibility');
        visibility.setAttribute('aria-label', t.sharedMessage + ' / ' + t.internalNote);
        ['shared', 'internal'].forEach(key => {
            const option = node('option', '', key === 'shared' ? t.sharedMessage : t.internalNote);
            option.value = key;
            visibility.append(option);
        });
        visibility.value = draft.visibility;
        visibility.disabled = replying.has(task.id);
        const hint = node('p', 'nt-visibility-hint', draft.visibility === 'internal' ? t.internalHint : t.sharedHint);
        visibility.addEventListener('change', () => { draft.visibility = visibility.value; hint.textContent = draft.visibility === 'internal' ? t.internalHint : t.sharedHint; });
        const textarea = node('textarea');
        textarea.placeholder = t.messagePlaceholder;
        textarea.setAttribute('aria-label', t.messagePlaceholder);
        textarea.maxLength = 10240;
        textarea.rows = 4;
        textarea.value = draft.body;
        textarea.disabled = replying.has(task.id);
        textarea.addEventListener('input', () => { draft.body = textarea.value; });
        const filesInput = node('input');
        filesInput.type = 'file';
        filesInput.multiple = true;
        filesInput.hidden = true;
        filesInput.accept = '.pdf,.jpg,.jpeg,.png,.gif,.webp,.txt,.csv,.doc,.docx,.xls,.xlsx,.zip';
        const filesList = node('div', 'nt-composer-files');
        const renderFiles = () => {
            filesList.replaceChildren(...draft.files.map((file, index) => {
                const item = node('span', 'nt-pending-file');
                item.append(document.createTextNode(file.name));
                const remove = node('button', 'nt-icon-button');
                remove.type = 'button';
                remove.setAttribute('aria-label', t.removeFile + ': ' + file.name);
                remove.disabled = replying.has(task.id);
                remove.append(icon('close'));
                remove.addEventListener('click', () => { draft.files.splice(index, 1); renderFiles(); });
                item.append(remove);
                return item;
            }));
        };
        renderFiles();
        filesInput.addEventListener('change', () => {
            const nextFiles = [...draft.files, ...Array.from(filesInput.files)];
            if (nextFiles.length > 5 || nextFiles.some(file => file.size > 10 * 1024 * 1024)) toast(t.attachmentHint, true);
            else draft.files = nextFiles;
            filesInput.value = '';
            renderFiles();
        });
        const footer = node('div', 'nt-composer-footer');
        const attach = node('button', 'nt-icon-button');
        attach.type = 'button';
        attach.title = t.attachFiles + ' · ' + t.attachmentHint;
        attach.setAttribute('aria-label', t.attachFiles);
        attach.disabled = replying.has(task.id);
        attach.append(icon('paperclip'));
        attach.addEventListener('click', () => filesInput.click());
        const send = node('button', 'nt-button nt-button-primary', replying.has(task.id) ? t.sendingMessage : t.sendMessage);
        send.type = 'submit';
        send.disabled = replying.has(task.id);
        footer.append(attach, send);
        const error = node('p', 'nt-composer-error');
        error.dataset.replyError = task.id;
        error.setAttribute('role', 'alert');
        if (config.isStaff) form.append(visibility);
        form.append(textarea, filesInput, filesList, footer, error);
        form.addEventListener('submit', event => { event.preventDefault(); sendReply(task); });
        textarea.addEventListener('keydown', event => { if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) { event.preventDefault(); form.requestSubmit(); } });
        panel.append(form, hint, node('p', 'nt-attachment-hint', t.attachmentHint));
    }
    function renderMessages(task) {
        const container = root.querySelector('[data-messages]');
        if (!container || container.dataset.messages !== task.id) return;
        const conversation = conversations.get(task.id);
        if (!conversation || (conversation.loading && !conversation.messages)) {
            container.replaceChildren(node('p', 'nt-conversation-state', t.loadingConversation));
            return;
        }
        if (conversation.error) {
            const retry = node('button', 'nt-button', t.refreshConversation);
            retry.type = 'button';
            retry.addEventListener('click', () => loadConversation(task));
            container.replaceChildren(node('p', 'nt-conversation-state', t.conversationError), retry);
            return;
        }
        if (!conversation.messages.length) {
            const empty = node('div', 'nt-conversation-empty');
            empty.append(icon('message'), node('strong', '', t.conversationEmpty), node('p', '', t.conversationEmptyDescription));
            container.replaceChildren(empty);
            return;
        }
        container.replaceChildren(...conversation.messages.map(message => {
            const item = node('article', 'nt-message' + (message.isOwn ? ' is-own' : '') + (message.visibility === 'internal' ? ' is-internal' : ''));
            const header = node('div', 'nt-message-header');
            const author = node('strong', '', message.author);
            header.append(author);
            if (message.isOwn) header.append(node('span', 'nt-own-label', t.you));
            if (message.visibility === 'internal') header.append(node('span', 'nt-internal-label', t.internalNote));
            const date = node('time', '', message.dateLabel);
            if (message.createdAt) date.dateTime = message.createdAt;
            header.append(date);
            item.append(header);
            if (message.body) item.append(node('p', 'nt-message-body', message.body));
            if (message.attachments.length) {
                const files = node('div', 'nt-message-attachments');
                message.attachments.forEach(file => {
                    const link = node('a', 'nt-file-link');
                    link.href = file.url;
                    link.append(icon('paperclip'), document.createTextNode(file.name));
                    const size = file.size < 1048576 ? Math.max(1, Math.round(file.size / 1024)) + ' KB' : (file.size / 1048576).toFixed(1) + ' MB';
                    link.append(node('span', '', size));
                    files.append(link);
                });
                item.append(files);
            }
            return item;
        }));
    }
    async function loadConversation(task) {
        const previous = conversations.get(task.id);
        if (previous?.loading || replying.has(task.id)) return;
        conversations.set(task.id, { ...previous, loading: true, error: false });
        const version = (conversationVersions.get(task.id) || 0) + 1;
        conversationVersions.set(task.id, version);
        renderMessages(task);
        try {
            const response = await fetch(task.conversationUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
            if (!response.ok) throw new Error(t.conversationError);
            const result = await response.json();
            if (conversationVersions.get(task.id) !== version) return;
            conversations.set(task.id, { ...result, loading: false });
            task.messageCount = result.messages.length;
            render();
        } catch (_) {
            if (conversationVersions.get(task.id) !== version) return;
            conversations.set(task.id, { ...previous, loading: false, error: true });
        }
        renderMessages(task);
        renderParticipants(task);
    }
    async function sendReply(task) {
        if (replying.has(task.id)) return;
        const draft = getDraft(task);
        if (!draft.body.trim() && !draft.files.length) {
            const error = root.querySelector('[data-reply-error]');
            if (error) error.textContent = t.messageRequired;
            return;
        }
        replying.add(task.id);
        delete draft.error;
        conversationVersions.set(task.id, (conversationVersions.get(task.id) || 0) + 1);
        const previous = conversations.get(task.id);
        if (previous) conversations.set(task.id, { ...previous, loading: false });
        renderDetail(task);
        const body = new FormData();
        body.append('body', draft.body);
        body.append('visibility', config.isStaff ? draft.visibility : 'shared');
        draft.files.forEach(file => body.append('files[]', file));
        try {
            const response = await fetch(task.conversationUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrfToken }, body });
            if (!response.ok) {
                let message = [401, 419].includes(response.status) ? t.sessionExpired : response.status === 403 ? t.forbidden : t.messageError;
                if (response.status === 422) {
                    const result = await response.json();
                    message = Object.values(result.errors || {}).flat().join(' ') || message;
                }
                throw new Error(message);
            }
            const result = await response.json();
            const cached = conversations.get(task.id);
            if (cached?.messages && !cached.error) {
                conversations.set(task.id, { ...cached, messages: [...cached.messages, result.message], loading: false });
                task.messageCount = cached.messages.length + 1;
            } else conversations.delete(task.id);
            drafts.delete(task.id);
            toast(t.messageSaved);
            render();
        } catch (error) {
            draft.error = error.message || t.messageError;
        } finally {
            replying.delete(task.id);
            if (activeTaskId === task.id && dialog.open) {
                renderDetail(task);
                const error = root.querySelector('[data-reply-error]');
                if (error && draft.error) error.textContent = draft.error;
                root.querySelector('.nt-composer textarea')?.focus();
            }
            if (!conversations.get(task.id)?.messages) loadConversation(task);
        }
    }
    function renderParticipants(task) {
        const container = root.querySelector('[data-participants]');
        const data = conversations.get(task.id);
        if (!container || container.dataset.participants !== task.id || !data?.participants) return;
        if (container.contains(document.activeElement)) return;
        const wasOpen = container.querySelector('details')?.open || false;
        const details = node('details', 'nt-participants');
        details.open = wasOpen;
        const summary = node('summary', '', t.participants + ' · ' + data.participants.length);
        const people = node('div', 'nt-participant-list');
        if (!data.participants.length) people.append(node('span', 'nt-attachment-hint', t.noParticipants));
        data.participants.forEach(person => {
            const item = node('span', 'nt-participant');
            item.append(document.createTextNode(person.name));
            if (person.removeUrl) {
                const remove = node('button', 'nt-icon-button');
                remove.type = 'button';
                remove.setAttribute('aria-label', t.removeParticipant + ': ' + person.name);
                remove.append(icon('close'));
                remove.addEventListener('click', () => changeParticipant(task, person.removeUrl, 'DELETE', {}, remove));
                item.append(remove);
            }
            people.append(item);
        });
        details.append(summary, people);
        if (data.shareUrl) {
            const form = node('form', 'nt-share-form');
            const label = node('label', '', t.shareWithClient);
            const email = node('input');
            email.type = 'email';
            email.required = true;
            email.placeholder = t.clientEmail;
            email.autocomplete = 'off';
            label.append(email);
            const share = node('button', 'nt-button', t.share);
            share.type = 'submit';
            const error = node('p', 'nt-composer-error');
            error.setAttribute('role', 'alert');
            form.append(label, share, node('p', 'nt-attachment-hint', t.shareHint), error);
            form.addEventListener('submit', async event => {
                event.preventDefault();
                error.textContent = '';
                const success = await changeParticipant(task, data.shareUrl, 'POST', { email: email.value }, share, error);
                if (success) email.value = '';
            });
            details.append(form);
        }
        container.replaceChildren(details);
    }
    async function changeParticipant(task, url, method, body, button, error) {
        button.disabled = true;
        try {
            const response = await fetch(url, { method, credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrfToken }, body: JSON.stringify(body) });
            if (!response.ok) {
                let message = t.participantError;
                if (response.status === 422) { const data = await response.json(); message = Object.values(data.errors || {}).flat().join(' ') || message; }
                throw new Error(message);
            }
            button.blur();
            await loadConversation(task);
            toast(t.participantSaved);
            return true;
        } catch (exception) {
            if (error) error.textContent = exception.message || t.participantError;
            else toast(t.participantError, true);
            return false;
        } finally { button.disabled = false; }
    }
    dialog.addEventListener('close', () => {
        root.append(root.querySelector('[data-toast]'));
        const id = activeTaskId;
        activeTaskId = null;
        const target = lastDetailTrigger?.isConnected ? lastDetailTrigger : Array.from(root.querySelectorAll('[data-detail-id]')).find(element => element.dataset.detailId === id);
        (target || root.querySelector('[data-view="' + view + '"]')).focus();
    });
    dialog.addEventListener('click', event => { if (event.target === dialog && event.clientX < dialog.getBoundingClientRect().left) dialog.close(); });
    root.querySelector('[data-close-detail]').addEventListener('click', () => dialog.close());
    root.querySelectorAll('[data-view]').forEach(button => button.addEventListener('click', () => {
        view = button.dataset.view;
        try { localStorage.setItem(storageKey, view); } catch (_) {}
        render();
    }));
    root.querySelectorAll('[data-filter]').forEach(button => button.addEventListener('click', () => { filter = button.dataset.filter; render(); }));
    const assignees = new Map(tasks.filter(task => task.assigneeId).map(task => [task.assigneeId, task.assignee || t.unassigned]));
    Array.from(assignees).sort((a, b) => a[1].localeCompare(b[1])).forEach(([id, name]) => { const option = node('option', '', name); option.value = id; assigneeSelect.append(option); });
    search.addEventListener('input', render);
    assigneeSelect.addEventListener('change', render);
    setInterval(() => {
        if (!dialog.open || document.hidden) return;
        const task = tasks.find(task => task.id === activeTaskId);
        if (task && detailTab === 'conversation') loadConversation(task);
    }, 30000);
    render();
})();
</script>
