@if(!$hasTaskOldInput)
<script data-task-page-prefill>
(() => {
    const form = document.getElementById(@json($taskFormId));
    const input = form?.querySelector('input[name="link"]');
    if (!input || input.value || form.dataset.taskPageLinkPrefilled) return;
    form.dataset.taskPageLinkPrefilled = 'true';

    try {
        // Read inside the iframe, so cached and lazy-loaded menu entries always
        // use the current caller URL, including query parameters and fragments.
        const source = window.parent !== window ? window.parent.location.href : document.referrer;
        if (!source) return;
        const url = new URL(source);
        if (!['http:', 'https:'].includes(url.protocol) || url.origin !== window.location.origin) return;
        input.value = url.href;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    } catch (_) {
        // A cross-origin embed cannot read its parent; the link stays editable.
    }
})();
</script>
@endif
