@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const page = document.querySelector('main.slep-content');
    if (!page) return;
    let storage;
    try { storage = window.sessionStorage; } catch (error) { return; }

    const url = new URL(window.location.href);
    const key = 'dotacion-contexto:' + url.pathname + ':' + (url.searchParams.get('anio') || '') + ':' + (url.searchParams.get('tab') || 'resumen');

    const saveContext = function (element) {
        const anchor = element.closest('.collapse[id], tr[id], .card[id]');
        const state = {
            savedAt: Date.now(),
            scrollY: window.scrollY,
            anchorId: anchor ? anchor.id : null,
            anchorOffset: anchor ? -anchor.getBoundingClientRect().top : 0,
            openIds: Array.from(page.querySelectorAll('.collapse.show[id]'), function (item) { return item.id; }),
        };
        try { storage.setItem(key, JSON.stringify(state)); } catch (error) {}
    };

    page.querySelectorAll('form[method="POST"]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!event.defaultPrevented) saveContext(form);
        });
    });
    page.querySelectorAll('[data-dotacion-contexto-salida]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            if (event.button === 0 && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) {
                saveContext(link);
            }
        });
    });

    let state;
    try {
        state = JSON.parse(storage.getItem(key) || 'null');
        storage.removeItem(key);
    } catch (error) { return; }
    if (!state || Date.now() - state.savedAt > 1800000) return;

    const openIds = new Set(state.openIds || []);
    page.querySelectorAll('.collapse[id]').forEach(function (collapse) {
        const open = openIds.has(collapse.id);
        collapse.classList.toggle('show', open);
        page.querySelectorAll('[aria-controls]').forEach(function (button) {
            if (button.getAttribute('aria-controls') !== collapse.id) return;
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
            button.classList.toggle('collapsed', !open);
        });
    });

    const restoreScroll = function () {
        const anchor = state.anchorId ? document.getElementById(state.anchorId) : null;
        const position = anchor
            ? window.scrollY + anchor.getBoundingClientRect().top + state.anchorOffset
            : state.scrollY;
        window.scrollTo(0, Math.max(0, position));
    };
    window.requestAnimationFrame(function () {
        window.requestAnimationFrame(restoreScroll);
    });
    window.addEventListener('load', restoreScroll, { once: true });
});
</script>
@endpush
