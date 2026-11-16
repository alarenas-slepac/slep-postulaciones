import { selectQuery } from './slep-listados.js';

const number = new Intl.NumberFormat('es-CL', { maximumFractionDigits: 2 });

function initialize(root) {
    const select = root.querySelector('[data-disposicion-destino]');
    const summary = root.querySelector('[data-disposicion-resumen]');
    if (!select || !summary) return;
    const update = () => {
        const option = select.selectedOptions[0];
        summary.hidden = !select.value;
        if (summary.hidden) return;
        let data = {};
        try { data = JSON.parse(option?.dataset.resumen || '{}'); } catch { /* Mantener los valores legibles. */ }
        summary.querySelector('[data-disposicion-nombre]').textContent = option?.dataset.nombre || '';
        summary.querySelectorAll('[data-disposicion-valor]').forEach(element => {
            const [block, field] = element.dataset.disposicionValor.split('.');
            const value = Number(data[block]?.[field] ?? 0);
            element.textContent = number.format(Number.isFinite(value) ? Math.max(0, value) : 0);
        });
    };
    selectQuery(select).on('change.disposicion', update);
    update();
}

const start = () => document.querySelectorAll('[data-disposicion]').forEach(initialize);
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
else start();
