<style>
.nt-workspace { --nt-ink:#292d36; --nt-muted:#7a7d87; --nt-line:#e9e8ed; --nt-coral:#e76558; --nt-purple:#8070c7; color:var(--nt-ink); background:#faf9f7; border:1px solid var(--nt-line); border-radius:16px; min-height:75vh; padding:34px 36px 40px; font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; font-size:14px; }
.nt-workspace *, .nt-workspace *::before, .nt-workspace *::after { box-sizing:border-box; }
.nt-workspace button, .nt-workspace input, .nt-workspace select { font:inherit; }
.nt-workspace button, .nt-workspace a { -webkit-tap-highlight-color:transparent; }
.nt-workspace button { cursor:pointer; }
.nt-workspace button:disabled { cursor:wait; opacity:.5; }
.nt-workspace a { color:inherit; text-decoration:none; }
.nt-workspace a:hover { color:var(--nt-coral); }
.nt-workspace button:focus-visible, .nt-workspace a:focus-visible, .nt-workspace input:focus-visible, .nt-workspace select:focus-visible { outline:3px solid #afa1ea; outline-offset:3px; }
.nt-workspace [hidden] { display:none !important; }
.nt-workspace svg { width:18px; height:18px; flex-shrink:0; vertical-align:middle; }
.nt-header, .nt-heading, .nt-navigation, .nt-view-tabs, .nt-scope-links, .nt-toolbar, .nt-search-controls, .nt-filter-chips, .nt-content-meta { display:flex; align-items:center; }
.nt-header { justify-content:space-between; gap:24px; margin-bottom:28px; }
.nt-heading { gap:18px; }
.nt-app-icon { background:#fbe5de; color:var(--nt-coral); width:60px; height:60px; display:grid; place-items:center; border-radius:17px; transform:rotate(-5deg); }
.nt-app-icon svg { width:29px; height:29px; transform:rotate(5deg); }
.nt-workspace .nt-eyebrow { color:var(--nt-muted); font-size:10px; font-weight:700; letter-spacing:1.6px; margin:0 0 7px; }
.nt-workspace h1 { font:650 28px/1.25 Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; letter-spacing:-.8px; color:var(--nt-ink); margin:0; }
.nt-intro { color:var(--nt-muted); margin:8px 0 0; font-size:13px; }
.nt-button { display:inline-flex; align-items:center; justify-content:center; gap:8px; border:1px solid var(--nt-line); border-radius:8px; padding:10px 15px; background:#fff; font-weight:600; font-size:13px; line-height:20px; white-space:nowrap; }
.nt-workspace .nt-button-primary { background:var(--nt-coral); color:#fff; border-color:var(--nt-coral); box-shadow:0 3px 6px #dc62541a; }
.nt-workspace .nt-button-primary:hover { background:#cf554a; color:#fff; }
.nt-navigation { justify-content:space-between; border-bottom:1px solid var(--nt-line); gap:16px; }
.nt-view-tabs { gap:27px; }
.nt-tab { display:flex; align-items:center; gap:8px; padding:0 0 15px; border:0; border-bottom:2px solid transparent; background:none; color:var(--nt-muted); font-weight:600 !important; margin-bottom:-1px; }
.nt-tab.is-active { color:var(--nt-coral); border-bottom-color:var(--nt-coral); }
.nt-scope-links { gap:20px; padding-bottom:15px; font-size:12px; }
.nt-scope-links a { display:flex; align-items:center; gap:5px; color:var(--nt-muted); }
.nt-scope-links a[aria-current] { color:var(--nt-ink); font-weight:650; }
.nt-scope-links svg { width:14px; height:14px; }
.nt-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; margin:25px 0 28px; }
.nt-stat { display:flex; flex-direction:column; gap:13px; background:#fff; border:1px solid var(--nt-line); border-radius:10px; padding:17px 20px; }
.nt-stat-label { display:flex; align-items:center; gap:8px; color:#666a75; font-size:12px; }
.nt-stat strong { font-size:27px; font-weight:600; line-height:1; letter-spacing:-1px; }
.nt-dot { display:inline-block; flex-shrink:0; width:7px; height:7px; border-radius:50%; background:#a3a4b1; }
.nt-dot-purple { background:var(--nt-purple); }.nt-dot-red { background:#e07569; }.nt-dot-green { background:#70a68c; }.nt-dot-amber { background:#d8ac5a; }
.nt-toolbar { justify-content:space-between; flex-wrap:wrap; gap:16px; }
.nt-filter-chips { gap:6px; flex-wrap:wrap; }
.nt-chip { display:flex; align-items:center; gap:7px; background:transparent; border:1px solid transparent; border-radius:7px; padding:8px 10px; color:var(--nt-muted); font-size:12px !important; }
.nt-chip.is-active { background:#fff; color:var(--nt-ink); border-color:var(--nt-line); box-shadow:0 1px 2px #24272c05; }
.nt-chip > [data-count] { background:#ecebf0; color:#7b7c88; border-radius:4px; padding:0 5px; font-size:10px; line-height:17px; }
.nt-search-controls { gap:8px; }
.nt-search { display:flex; align-items:center; gap:8px; border:1px solid var(--nt-line); padding:8px 11px; background:#fff; border-radius:7px; color:#90919b; }
.nt-search input { border:0; outline:none; background:transparent; width:170px; min-width:0; color:var(--nt-ink); font-size:12px; }
.nt-search:focus-within { border-color:#afa1ea; }
.nt-search-controls select { max-width:190px; min-height:35px; padding:7px 26px 7px 10px; border:1px solid var(--nt-line); border-radius:7px; color:#666a75; background:#fff; font-size:12px; }
.nt-content-meta { gap:12px; font-size:11px; color:var(--nt-muted); padding:20px 1px 12px; }
.nt-content-meta a { margin-left:auto; }.nt-board-hint { font-size:11px; opacity:.85; }
.nt-list { background:#fff; border:1px solid var(--nt-line); border-radius:10px; overflow:hidden; }
.nt-list-head, .nt-task-row { display:grid; grid-template-columns:minmax(200px,1fr) 175px 125px 135px; align-items:center; gap:18px; padding:0 22px; }
.nt-list-head { min-height:43px; color:var(--nt-muted); font-size:11px; border-bottom:1px solid var(--nt-line); }
.nt-list-group + .nt-list-group { border-top:1px solid var(--nt-line); }
.nt-group-heading { display:flex; gap:9px; align-items:center; padding:17px 22px 13px; font-weight:650; font-size:12px; cursor:pointer; list-style:none; }
.nt-group-heading::-webkit-details-marker { display:none; }
.nt-group-heading::before { content:"›"; font-size:17px; font-weight:400; color:#a1a2ab; transform:rotate(90deg); transition:transform .15s; }
details:not([open]) > .nt-group-heading::before { transform:rotate(0); }
.nt-group-count { color:#9899a3; font-weight:400; font-size:11px; margin-left:2px; }
.nt-task-row { min-height:67px; border-top:1px solid #f2f1f5; font-size:12px; transition:background .15s; }
.nt-task-row:hover { background:#fcfbff; }
.nt-task-main { display:flex; align-items:center; gap:11px; min-width:0; }
.nt-complete { width:19px; height:19px; border:1.5px solid #d8d9df; border-radius:50%; padding:0; background:#fff; color:transparent; flex-shrink:0; display:grid; place-items:center; }
.nt-complete svg { width:12px; height:12px; }
.nt-complete:disabled { cursor:default; }
.nt-complete:not(:disabled):hover, .nt-complete.is-completed { color:#579f7c; border-color:#77b596; background:#edf8f1; }
.nt-task-title { padding:0; background:none; border:0; text-align:left; color:var(--nt-ink); font-weight:550 !important; line-height:1.5; overflow-wrap:anywhere; }
.nt-task-subtitle { color:#91939c; display:block; margin-top:3px; font-size:11px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:370px; }
.nt-person { display:flex; align-items:center; gap:8px; min-width:0; color:#696d77; font-size:12px; }
.nt-person-name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.nt-avatar { width:25px; height:25px; border-radius:50%; background:#eeebfa; color:#8c79af; display:grid; place-items:center; font-size:9px; font-weight:650; flex-shrink:0; }
.nt-avatar[data-tone="1"] { background:#e5f1ed; color:#658979; }.nt-avatar[data-tone="2"] { background:#fae9df; color:#b48a70; }.nt-avatar[data-tone="3"] { background:#e6eef9; color:#718aae; }
.nt-avatar.is-unassigned { background:#f4f3f6; color:#a6a6af; border:1px dashed #d8d7df; }.nt-avatar svg { width:12px; height:12px; }
.nt-due { font-size:11px; color:#7e818b; display:flex; align-items:center; gap:5px; white-space:nowrap; }
.nt-due svg { width:13px; height:13px; }.nt-due.is-overdue { color:#d8675c; }.nt-due.is-today { color:#b08b42; }
.nt-status { font-size:10px; font-weight:550; line-height:20px; padding:1px 9px; border-radius:5px; display:inline-flex; align-items:center; gap:6px; width:max-content; background:#f0edf9; color:#8875b3; }
.nt-status[data-status="in_progress"] { background:#edf1fd; color:#6e83bf; }.nt-status[data-status="not_manageable"] { background:#fbf3e1; color:#b19151; }.nt-status[data-status="closed"] { background:#e9f4ed; color:#6a9a7c; }
.nt-group-empty { padding:14px 52px 24px; color:#a2a2ac; font-size:12px; }
.nt-board { display:grid; grid-template-columns:repeat(4,minmax(225px,1fr)); gap:16px; overflow-x:auto; padding-bottom:18px; align-items:start; }
.nt-column { background:#f1f0f3; border:1px solid transparent; border-radius:10px; padding:12px; min-height:230px; transition:background .15s,border-color .15s; }
.nt-column[data-status="in_progress"] { background:#eef0f6; }.nt-column[data-status="not_manageable"] { background:#f5f1e9; }.nt-column[data-status="closed"] { background:#edf3ee; }
.nt-column.is-drop-target { border-color:#a599d5; background:#e8e2f8; }.nt-column-title { display:flex; align-items:center; gap:8px; margin:2px 2px 17px; font-size:12px; font-weight:650; }.nt-column-title .nt-group-count { margin-left:auto; }
.nt-card { background:#fff; border:1px solid #e8e6ed; border-radius:8px; padding:15px; margin-bottom:10px; box-shadow:0 2px 3px #22242b03; }.nt-card[draggable="true"] { cursor:grab; }.nt-card[draggable="true"]:active { cursor:grabbing; }.nt-card.is-dragging { opacity:.4; }
.nt-card .nt-task-main { align-items:flex-start; gap:9px; }.nt-card .nt-complete { margin-top:2px; }.nt-card .nt-task-title { font-size:13px; }.nt-card-description { color:#93949d; font-size:11px; line-height:1.65; margin:10px 0 14px; display:-webkit-box; -webkit-box-orient:vertical; -webkit-line-clamp:2; overflow:hidden; overflow-wrap:anywhere; }
.nt-card-footer { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:16px; }.nt-card-footer .nt-person-name { max-width:100px; font-size:10px; }.nt-card-footer .nt-avatar { width:22px; height:22px; font-size:8px; }
.nt-column-empty { display:grid; place-items:center; text-align:center; border:1px dashed #d9d7e1; border-radius:7px; padding:25px 10px; color:#96939f; font-size:11px; line-height:1.7; min-height:96px; }
.nt-empty { padding:70px 24px; border:1px dashed #dedce6; border-radius:10px; text-align:center; background:#ffffff80; }.nt-empty-icon { display:grid; place-items:center; width:56px; height:56px; margin:0 auto 18px; border-radius:17px; color:var(--nt-purple); background:#efebf8; }.nt-empty-icon svg { width:27px; height:27px; }
.nt-empty h2 { color:var(--nt-ink); font-size:18px; font-weight:600; margin:0 0 8px; }.nt-empty p { color:var(--nt-muted); font-size:13px; margin:0 0 22px; }
.nt-toast { position:fixed; bottom:28px; left:50%; transform:translateX(-50%); padding:12px 20px; border-radius:9px; background:#30363f; color:#fff; font-size:13px; box-shadow:0 4px 25px #2223; z-index:1100; max-width:calc(100vw - 32px); }.nt-toast.is-error { background:#a74741; }
.nt-drawer { position:fixed; inset:0 0 0 auto; margin:0; width:min(620px,100vw); height:100%; max-height:100%; max-width:100%; border:0; border-left:1px solid var(--nt-line); background:#fff; padding:27px 30px; color:var(--nt-ink); box-shadow:-8px 0 60px #25232c12; overflow-y:auto; }.nt-drawer::backdrop { background:#23202d35; }
.nt-drawer-top { display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid var(--nt-line); padding-bottom:18px; margin-bottom:26px; }.nt-drawer-top .nt-eyebrow { margin:0; }.nt-icon-button { background:none; border:0; padding:5px; color:var(--nt-muted); border-radius:5px; }
.nt-drawer h2 { font-size:24px; line-height:1.4; font-weight:600; letter-spacing:-.5px; margin:20px 0 25px; color:var(--nt-ink); overflow-wrap:anywhere; }
.nt-detail-property { display:grid; grid-template-columns:125px minmax(0,1fr); align-items:center; gap:16px; margin:20px 0; font-size:12px; }.nt-detail-label { color:var(--nt-muted); }.nt-detail-property select { border:1px solid var(--nt-line); border-radius:6px; padding:7px 10px; color:var(--nt-ink); background:#fff; width:100%; }
.nt-detail-section { border-top:1px solid var(--nt-line); margin-top:27px; padding-top:24px; }.nt-detail-section h3 { font-size:12px; font-weight:650; color:var(--nt-ink); margin:0 0 12px; }.nt-detail-description { font-size:13px; color:#747680; line-height:1.8; white-space:pre-wrap; overflow-wrap:anywhere; }.nt-detail-actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:28px; }.nt-detail-section code { overflow-wrap:anywhere; font-size:11px; }
.nt-message-count { display:inline-flex; align-items:center; gap:3px; color:#9a96aa; font-size:10px; margin-left:9px; white-space:nowrap; }.nt-message-count svg { width:12px; height:12px; }
.nt-detail-tabs { display:flex; gap:24px; border-bottom:1px solid var(--nt-line); margin:0 0 18px; }.nt-detail-tabs .nt-tab { font-size:12px; }
.nt-conversation-heading { display:flex; justify-content:space-between; align-items:center; gap:10px; color:var(--nt-muted); }.nt-conversation-heading p { margin:0; font-size:12px; }.nt-conversation-state { color:var(--nt-muted); padding:24px 0; font-size:12px; }
.nt-messages { margin:22px 0; }.nt-message { border:1px solid var(--nt-line); border-radius:10px; padding:17px; margin:0 0 14px; }.nt-message.is-own { background:#faf8ff; border-color:#eae5f4; }
.nt-message-header { display:flex; flex-wrap:wrap; align-items:center; gap:7px; font-size:11px; }.nt-message-header strong { font-weight:650; }.nt-message-header time { margin-left:auto; color:#9896a1; font-size:10px; }.nt-own-label { padding:1px 5px; border-radius:4px; color:#9a89b6; background:#efebf7; font-size:9px; }
.nt-message-body { white-space:pre-wrap; overflow-wrap:anywhere; font-size:13px; line-height:1.8; margin:10px 0 0; color:#62626e; }.nt-message-attachments { display:flex; flex-wrap:wrap; gap:6px; margin-top:12px; }.nt-file-link { display:flex; align-items:center; gap:6px; padding:7px 9px; border:1px solid #e3dfea; background:#fff; border-radius:6px; font-size:11px; max-width:100%; overflow-wrap:anywhere; }.nt-file-link svg { width:13px; height:13px; }.nt-file-link span { color:#aaa6b1; font-size:9px; white-space:nowrap; }
.nt-conversation-empty { text-align:center; padding:35px 15px; color:#a293b8; }.nt-conversation-empty > svg { display:block; width:30px; height:30px; margin:0 auto 15px; }.nt-conversation-empty strong { display:block; font-size:14px; font-weight:550; color:#686273; }.nt-conversation-empty p { margin:9px 0 0; font-size:12px; color:#9b96a2; }
.nt-composer { background:#fff; border:1px solid #ddd7e8; border-radius:10px; overflow:hidden; }.nt-composer:focus-within { border-color:#aa9bca; box-shadow:0 0 0 3px #a692c610; }.nt-composer textarea { width:100%; border:0; background:none; resize:vertical; min-height:115px; max-height:400px; padding:15px; font-family:inherit; font-size:13px; line-height:1.7; color:var(--nt-ink); outline:none; display:block; }.nt-composer textarea:focus-visible { outline:none; }.nt-composer textarea::placeholder { color:#aaa3b3; }
.nt-composer-footer { display:flex; align-items:center; justify-content:space-between; padding:8px 12px 12px; }.nt-composer-footer .nt-button { font-size:11px; padding:6px 11px; }.nt-attachment-hint { font-size:10px; color:#aaa4b0; margin:9px 2px; }.nt-composer-files { display:flex; flex-wrap:wrap; gap:5px; padding:0 12px; }.nt-pending-file { display:flex; align-items:center; gap:5px; font-size:10px; background:#f4f0fa; border-radius:5px; padding:3px 5px 3px 8px; overflow-wrap:anywhere; max-width:100%; }.nt-pending-file svg { width:11px; height:11px; }.nt-composer-error { color:#bc534c; margin:0 14px 12px; font-size:11px; }.nt-composer-error:empty { display:none; }
.nt-workspace .nt-message.is-internal { background:#fffcf3; border-color:#ede4ca; }.nt-internal-label { color:#ad935a; font-size:9px; background:#f7efdb; padding:2px 5px; border-radius:4px; }
.nt-visibility { margin:10px 12px 0; padding:5px 8px; color:#8d7ca6; background:#f4f0fa; border:0; border-radius:5px; font-size:11px !important; }.nt-visibility-hint { color:#96909f; font-size:11px; margin:10px 2px 0; }
.nt-participants { margin:0 0 20px; padding:12px; background:#faf9fc; border:1px solid var(--nt-line); border-radius:8px; }.nt-participants summary { cursor:pointer; color:#89808f; font-size:11px; }.nt-participant-list { display:flex; gap:6px; flex-wrap:wrap; margin-top:12px; }.nt-participant { display:inline-flex; align-items:center; gap:4px; background:#eee9f5; color:#9380a9; padding:4px 7px; border-radius:5px; font-size:10px; }.nt-participant svg { width:11px; height:11px; }.nt-participant .nt-icon-button { padding:1px; }
.nt-share-form { margin-top:15px; }.nt-share-form label { display:block; font-size:11px; color:#8d8794; }.nt-share-form input { display:block; margin:8px 0; padding:9px 11px; border:1px solid var(--nt-line); border-radius:6px; background:#fff; color:var(--nt-ink); width:100%; font-size:12px; }.nt-share-form .nt-button { font-size:11px; padding:6px 10px; }.nt-share-form .nt-composer-error { margin:8px 0 0; }
@media(min-width:1600px) { .nt-workspace { padding:40px 48px; }.nt-task-row,.nt-list-head { grid-template-columns:minmax(200px,1fr) 220px 150px 155px; } }
@media(max-width:1100px) { .nt-workspace { padding:24px; }.nt-toolbar { align-items:flex-start; }.nt-search-controls { flex:1; justify-content:flex-end; }.nt-list-head,.nt-task-row { grid-template-columns:minmax(180px,1fr) 145px 105px 105px; gap:12px; padding-left:16px; padding-right:16px; }.nt-task-subtitle { max-width:230px; } }
@media(max-width:760px) { .nt-workspace { padding:22px 16px; border-radius:11px; }.nt-workspace h1 { font-size:23px; }.nt-app-icon { display:none; }.nt-header { align-items:flex-start; flex-wrap:wrap; gap:18px; }.nt-header > .nt-button { padding:8px 12px; }.nt-intro { max-width:290px; line-height:1.6; }.nt-stats { grid-template-columns:repeat(2,minmax(0,1fr)); gap:9px; margin-top:20px; }.nt-stat { padding:14px; gap:11px; }.nt-stat strong { font-size:24px; }.nt-stat-label { font-size:11px; }.nt-search-controls { justify-content:flex-start; flex-wrap:wrap; width:100%; }.nt-search { flex:1; min-width:160px; }.nt-search input { width:100%; }.nt-search-controls select { max-width:100%; }.nt-chip { padding:7px; font-size:11px !important; gap:5px; }.nt-navigation { gap:10px; }.nt-view-tabs { gap:18px; }.nt-tab { font-size:12px !important; }.nt-scope-links { gap:12px; font-size:11px; }.nt-scope-links svg { display:none; }.nt-list-head { display:none; }.nt-task-row { grid-template-columns:minmax(0,1fr) auto; gap:12px; padding:14px; }.nt-task-main { grid-column:1 / -1; }.nt-task-row > .nt-person { padding-left:30px; }.nt-task-row > .nt-status { display:none; }.nt-task-subtitle { max-width:calc(100vw - 150px); }.nt-group-heading { padding-left:17px; }.nt-board { grid-template-columns:repeat(4,260px); }.nt-content-meta { flex-wrap:wrap; }.nt-board-hint { order:3; flex-basis:100%; }.nt-drawer { padding:22px; }.nt-content-meta a { font-size:10px; } }
@media(prefers-reduced-motion:reduce) { .nt-workspace * { transition:none !important; scroll-behavior:auto !important; } }
</style>
