import './slep-listados';
import '../css/dotacion-bloqueos.css';

function initialize() {
    document.querySelector('[data-dotacion-lock-error]')?.focus();
    document.querySelectorAll('[data-dotacion-lock-form]').forEach(form => {
        form.addEventListener('submit', event => {
            if (form.dataset.submitting) { event.preventDefault(); return; }
            const field = form.elements.motivo;
            const error = document.getElementById(`${field.id.replace(/-motivo$/, '')}-error`);
            const invalid = field.value.length > 1000;
            field.classList.toggle('is-invalid', invalid);
            if (invalid) {
                event.preventDefault(); field.setAttribute('aria-invalid', 'true'); error.textContent = 'El motivo admite hasta 1.000 caracteres.'; field.focus(); return;
            }
            form.dataset.submitting = 'true'; form.setAttribute('aria-busy', 'true');
            const button = form.querySelector('button[type=submit]'); button.dataset.originalHtml = button.innerHTML; button.textContent = 'Guardando…'; button.disabled = true;
        });
    });
    if (document.querySelector('[data-dotacion-solo-consulta]')) {
        // Presentación complementaria. El middleware vuelve a verificar cada escritura,
        // incluso sin JavaScript y desde formularios abiertos antes de activar el bloqueo.
        document.querySelectorAll('.slep-content-inner form').forEach(form => {
            if (['get', 'dialog'].includes((form.getAttribute('method') || 'get').toLowerCase())) return;
            form.querySelectorAll('input,select,textarea,button').forEach(field => { field.disabled = true; });
            form.addEventListener('submit', event => event.preventDefault());
        });
    }
    window.addEventListener('pageshow', () => document.querySelectorAll('[data-dotacion-lock-form]').forEach(form => {
        delete form.dataset.submitting; form.removeAttribute('aria-busy');
        const button = form.querySelector('button[data-original-html]');
        if (button) { button.innerHTML = button.dataset.originalHtml; delete button.dataset.originalHtml; button.disabled = false; }
    }));
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize); else initialize();
