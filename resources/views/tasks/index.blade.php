@extends('uikittemplate::app')

@section('content')
@include('tasks::tasks._styles')

<section class="nt-workspace" data-task-workspace aria-label="{{ __('tasks::tasks.manage') }}">
    <header class="nt-header">
        <div class="nt-heading">
            <span class="nt-app-icon" data-icon="tasks" aria-hidden="true"></span>
            <div>
                <p class="nt-eyebrow">{{ __('tasks::tasks.eyebrow') }}</p>
                <h1>{{ $showClosed ? __('tasks::tasks.closed') : __('tasks::tasks.workspace') }}</h1>
                <p class="nt-intro">{{ __('tasks::tasks.' . ($showClosed ? 'closedIntro' : 'intro')) }}</p>
            </div>
        </div>
        @if($canCreate)
            <a class="nt-button nt-button-primary" href="{{ $createUrl }}">
                <span data-icon="plus" aria-hidden="true"></span>{{ __('tasks::tasks.new') }}
            </a>
        @endif
    </header>

    <nav class="nt-navigation" aria-label="{{ __('tasks::tasks.manage') }}">
        <div class="nt-view-tabs" role="group" aria-label="{{ __('tasks::tasks.listView') }} / {{ __('tasks::tasks.boardView') }}">
            <button type="button" class="nt-tab is-active" data-view="list" aria-pressed="true"><span data-icon="list" aria-hidden="true"></span>{{ __('tasks::tasks.listView') }}</button>
            <button type="button" class="nt-tab" data-view="board" aria-pressed="false"><span data-icon="board" aria-hidden="true"></span>{{ __('tasks::tasks.boardView') }}</button>
        </div>
        <div class="nt-scope-links">
            <a href="{{ $indexUrl }}" @if(!$showClosed) aria-current="page" @endif>{{ __('tasks::tasks.active') }}</a>
            <a href="{{ $closedUrl }}" @if($showClosed) aria-current="page" @endif><span data-icon="check" aria-hidden="true"></span>{{ __('tasks::tasks.closed') }}</a>
        </div>
    </nav>

    <div class="nt-stats" data-stats></div>

    <div class="nt-toolbar">
        <div class="nt-filter-chips" role="group" aria-label="{{ __('tasks::tasks.all') }}">
            <button class="nt-chip is-active" type="button" data-filter="all" aria-pressed="true">{{ __('tasks::tasks.all') }} <span data-count="all">0</span></button>
            @if($isStaff)<button class="nt-chip" type="button" data-filter="mine" aria-pressed="false">{{ __('tasks::tasks.mine') }} <span data-count="mine">0</span></button>@endif
            @if(!$showClosed)
                <button class="nt-chip" type="button" data-filter="overdue" aria-pressed="false"><span class="nt-dot nt-dot-red"></span>{{ __('tasks::tasks.overdue') }} <span data-count="overdue">0</span></button>
            @endif
        </div>
        <div class="nt-search-controls">
            <label class="nt-search"><span data-icon="search" aria-hidden="true"></span><input type="search" data-search placeholder="{{ __('tasks::tasks.search') }}" aria-label="{{ __('tasks::tasks.search') }}" autocomplete="off"></label>
            <select data-assignee aria-label="{{ __('tasks::tasks.assignee') }}"><option value="all">{{ __('tasks::tasks.allAssignees') }}</option><option value="unassigned">{{ __('tasks::tasks.unassigned') }}</option></select>
        </div>
    </div>

    <div class="nt-content-meta"><span data-result-count aria-live="polite"></span><span class="nt-board-hint" data-board-hint hidden>{{ __('tasks::tasks.boardHint') }}</span>@if($isStaff)<a href="{{ $tableUrl }}">{{ __('tasks::tasks.tableView') }} <span aria-hidden="true">↗</span></a>@endif</div>
    <div data-task-content aria-busy="false"></div>
    <noscript><p class="nt-empty">{{ __('tasks::tasks.javascriptRequired') }} <a href="{{ $tableUrl }}">{{ __('tasks::tasks.tableView') }}</a></p></noscript>

    <div class="nt-toast" data-toast role="status" aria-live="polite" hidden></div>
    <dialog class="nt-drawer" data-detail aria-labelledby="nt-detail-title">
        <div class="nt-drawer-top"><span class="nt-eyebrow">{{ __('tasks::tasks.details') }}</span><button type="button" class="nt-icon-button" data-close-detail aria-label="{{ __('tasks::tasks.closePanel') }}"><span data-icon="close" aria-hidden="true"></span></button></div>
        <div data-detail-content></div>
    </dialog>

    <script type="application/json" data-task-config>{!! json_encode([
        'tasks' => $tasks,
        'statuses' => $statuses,
        'showClosed' => $showClosed,
        'currentUserId' => $currentUserId,
        'today' => $today,
        'canCreate' => $canCreate,
        'isStaff' => $isStaff,
        'createUrl' => $createUrl,
        'initialView' => in_array(request()->query('view'), ['list', 'board']) ? request()->query('view') : null,
        'csrfToken' => csrf_token(),
        'text' => __('tasks::tasks'),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
</section>

@include('tasks::tasks._scripts')
@endsection
