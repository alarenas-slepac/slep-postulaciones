import jquery from 'jquery';
import select2 from 'select2';
import 'select2/dist/css/select2.min.css';
import 'select2-bootstrap-5-theme/dist/select2-bootstrap-5-theme.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
import '../css/slep-listados.css';

select2(window, jquery);

export function enhanceSelects(scope, root = scope.closest('[data-slep-listing]') || scope) {
    scope.querySelectorAll('[data-slep-select]').forEach(select => {
        if (jquery(select).data('select2')) return;
        const mode = select.dataset.slepSelect;
        const label = root.querySelector(`label[for="${select.id}"]`);
        const widget = jquery(select);
        widget.select2({
            theme: 'bootstrap-5', width: '100%', dropdownParent: jquery(select.closest('.modal') || root), dropdownCssClass: 'slep-select-dropdown',
            minimumResultsForSearch: mode === 'compact' ? Infinity : 0,
            tags: mode === 'tags',
            createTag: params => {
                const term = params.term.trim();
                return term && term.length <= 255 ? { id: term, text: term, newTag: true } : null;
            },
            language: { noResults: () => 'No hay coincidencias.', searching: () => 'Buscando…', errorLoading: () => 'No se pudieron cargar las opciones.' },
        });
        const selection = widget.next('.select2-container').find('.select2-selection');
        if (label) { label.id ||= `${select.id}-label`; selection.attr('aria-labelledby', label.id); }
        selection.attr('aria-describedby', select.getAttribute('aria-describedby') || '');
        if (select.classList.contains('is-invalid')) {
            widget.next('.select2-container').addClass('is-invalid');
            selection.attr('aria-invalid', 'true');
        }
        widget.on('select2:open.slepListing', () => {
            const search = root.querySelector('.select2-container--open .select2-search__field');
            if (search) {
                search.setAttribute('aria-label', `Buscar ${label?.textContent.trim().toLocaleLowerCase('es') || 'opción'}`);
                if (mode === 'tags') search.maxLength = 255;
                search.focus();
            }
        });
        // Select2 usa title para su texto completo. El valor ya está visible y
        // ajusta líneas; se retira el tooltip nativo de esta presentación.
        const removeTitle = () => widget.next('.select2-container').find('[title]').removeAttr('title');
        widget.on('change.slepListing select2:select.slepListing', removeTitle);
        removeTitle();
    });

}

export function destroySelects(scope) {
    scope.querySelectorAll('[data-slep-select]').forEach(select => {
        if (jquery(select).data('select2')) jquery(select).off('.slepListing').select2('destroy');
    });
}

export { jquery as selectQuery };

function initialize(root) {
    if (root.dataset.slepInitialized) return;
    root.dataset.slepInitialized = 'true';
    enhanceSelects(root);
    const area = root.querySelector('[data-slep-area]');
    const syncArea = () => {
        const selected = area?.selectedOptions[0];
        root.querySelectorAll('[data-slep-conditional]').forEach(section => {
            const enabled = selected?.dataset[section.dataset.slepConditional] === '1';
            section.hidden = !enabled;
            const control = section.querySelector('select');
            control.disabled = !enabled;
            if (!enabled && control.value) jquery(control).val('').trigger('change');
            jquery(control).trigger('change.select2');
            jquery(control).next('.select2-container').find('[title]').removeAttr('title');
        });
    };
    if (area) { jquery(area).on('change.slepArea', syncArea); syncArea(); }

    root.querySelectorAll('[data-slep-filter-form]').forEach(form => {
        const submit = form.querySelector('[data-slep-submit]');
        const label = form.querySelector('[data-slep-submit-label]');
        const validateInteger = input => {
            const value = input.value.trim();
            const valid = !value || (/^\d{1,2}$/.test(value) && Number(value) <= 80);
            input.classList.toggle('is-invalid', !valid);
            if (valid) input.removeAttribute('aria-invalid'); else input.setAttribute('aria-invalid', 'true');
            const error = form.querySelector(`#${input.id}-error`);
            if (error) error.textContent = valid ? '' : 'Ingresa un número entero entre 0 y 80 años.';
            return valid;
        };
        form.querySelectorAll('[data-slep-integer]').forEach(input => input.addEventListener('input', () => validateInteger(input)));
        form.addEventListener('submit', event => {
            const invalid = [...form.querySelectorAll('[data-slep-integer]')].filter(input => !validateInteger(input));
            if (invalid.length) { event.preventDefault(); invalid[0].focus(); return; }
            if (submit.disabled) { event.preventDefault(); return; }
            submit.disabled = true; label.textContent = 'Buscando…'; form.setAttribute('aria-busy', 'true');
        });
        window.addEventListener('pageshow', () => {
            submit.disabled = false; label.textContent = 'Buscar'; form.removeAttribute('aria-busy');
        });
    });
    root.querySelector('[data-slep-errors]')?.focus();
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('[data-slep-listing]').forEach(initialize));
else document.querySelectorAll('[data-slep-listing]').forEach(initialize);
