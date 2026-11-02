import { enhanceSelects, selectQuery } from './slep-listados';

function initialize(root) {
    enhanceSelects(root);
    const form = root.querySelector('[data-reapertura-form]');
    const causal = form.querySelector('[name="causal_modificacion_termino"]');
    const button = form.querySelector('[data-reapertura-submit]');
    const sync = () => {
        const licencia = causal.value === root.dataset.causalLicencia;
        const renuncia = causal.value === 'renuncia_voluntaria';
        root.querySelectorAll('[data-reapertura-renuncia]').forEach(section => {
            section.hidden = licencia;
            const input = section.querySelector('input');
            input.disabled = licencia;
            input.required = renuncia;
            section.querySelector('[data-reapertura-obligatorio]').hidden = !renuncia;
        });
        const section = root.querySelector('[data-reapertura-licencia]');
        section.hidden = !licencia;
        const input = section.querySelector('input');
        input.disabled = !licencia;
        input.required = licencia;
    };
    selectQuery(causal).on('change.reaperturaTermino', sync);
    sync();
    const errors = root.querySelector('[data-reapertura-errores]');
    if (errors) errors.focus();
    form.addEventListener('submit', () => {
        button.disabled = true;
        button.textContent = 'Registrando modificación…';
        form.setAttribute('aria-busy', 'true');
    });
    window.addEventListener('pageshow', () => {
        button.disabled = false;
        button.textContent = 'Reabrir y registrar modificación';
        form.removeAttribute('aria-busy');
        sync();
    });
}

const start = () => document.querySelectorAll('[data-reapertura-termino]').forEach(initialize);
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true }); else start();
