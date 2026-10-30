import { enhanceSelects, selectQuery } from './slep-listados';

function initializeMencion(root) {
    const select = root.querySelector('#mencion');
    if (!select) return;
    enhanceSelects(root, root);
    const widget = selectQuery(select);
    const container = widget.next('.select2-container');
    const selection = container.find('.select2-selection');
    const error = root.querySelector('#mencion-error');

    const syncState = () => {
        const invalid = select.classList.contains('is-invalid');
        container.toggleClass('is-invalid', invalid);
        selection.attr('aria-invalid', String(invalid));
        selection.attr('aria-required', String(select.required));
        if (invalid && !error.textContent.trim()) error.textContent = select.dataset.requiredMessage;
        if (!invalid) error.textContent = '';
        if (select.disabled) widget.select2('close');
    };
    widget.on('change.profileMencion', event => {
        // Los eventos de jQuery deben llegar a la validación y al checklist nativos.
        if (!event.originalEvent) {
            if (select.checkValidity()) select.classList.remove('is-invalid');
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        syncState();
    });
    root.querySelector('label[for="mencion"]').addEventListener('click', event => {
        event.preventDefault();
        selection.trigger('focus');
    });
    new MutationObserver(syncState).observe(select, {
        attributes: true, attributeFilter: ['class', 'required', 'disabled'],
    });
    syncState();
}

function initialize() {
    document.querySelectorAll('[data-profile-mencion]').forEach(initializeMencion);
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
else initialize();
