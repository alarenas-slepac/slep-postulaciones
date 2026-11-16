import { selectQuery } from './slep-listados.js';
import '../css/dotacion-disposicion.css';

const number = new Intl.NumberFormat('es-CL', { maximumFractionDigits: 2 });

function initialize(root) {
    const select = root.querySelector('[data-disposicion-destino]');
    const summary = root.querySelector('[data-disposicion-resumen]');
    const cupoSelect = root.querySelector('[data-disposicion-cupo]');
    if (!select || !summary || !cupoSelect) return;
    const groups = Array.from(cupoSelect.querySelectorAll('optgroup')).map(group => {
        const template = group.cloneNode(true);
        // Los identificadores de Select2 pertenecen a nodos concretos: no reutilizar su caché al cambiar de destino.
        template.removeAttribute('data-select2-id');
        template.querySelectorAll('[data-select2-id]').forEach(element => element.removeAttribute('data-select2-id'));
        return template;
    });
    const empty = cupoSelect.options[0].cloneNode(true);
    empty.removeAttribute('data-select2-id');
    const hours = root.querySelector('#dis-horas');
    const excess = root.querySelector('[data-disposicion-excedente]');
    const blocks = { plan_estudio: 'Planes de Estudio', parvularia: 'Educación Parvularia', pie: 'PIE' };
    let cupos = [];
    const paragraph = (text, className = '') => {
        const element = document.createElement('p');
        element.className = className;
        element.textContent = text;
        return element;
    };
    const updateSelection = () => {
        const cupo = cupos.find(item => item.clave === cupoSelect.value);
        const assigned = Number(hours?.value || 0);
        const difference = Math.round((assigned - Number(cupo?.disponibles || 0)) * 100) / 100;
        excess.hidden = !cupo || difference <= 0;
        excess.textContent = excess.hidden ? '' : `Se destinarán ${number.format(assigned)} h a un cupo con ${number.format(cupo.disponibles)} h disponibles. Las ${number.format(difference)} h adicionales quedarán registradas y el cupo se mostrará como Ya destinado.`;
        summary.querySelectorAll('[data-cupo-clave]').forEach(article => {
            article.classList.toggle('border-primary', article.dataset.cupoClave === cupoSelect.value);
        });
    };
    const update = () => {
        const option = select.selectedOptions[0];
        const previous = cupoSelect.value;
        cupoSelect.replaceChildren(empty.cloneNode(true));
        groups.filter(group => group.dataset.destinoId === select.value).forEach(group => cupoSelect.append(group.cloneNode(true)));
        cupoSelect.value = Array.from(cupoSelect.options).some(item => item.value === previous && !item.disabled) ? previous : '';
        cupoSelect.disabled = !select.value;
        selectQuery(cupoSelect).trigger('change.select2');
        summary.hidden = !select.value;
        cupos = [];
        if (summary.hidden) { updateSelection(); return; }
        let data = {};
        try { data = JSON.parse(option?.dataset.resumen || '{}'); } catch { /* Mantener los valores legibles. */ }
        try { cupos = JSON.parse(option?.dataset.cupos || '[]'); } catch { /* El servidor valida el cupo. */ }
        summary.querySelector('[data-disposicion-nombre]').textContent = option?.dataset.nombre || '';
        summary.querySelectorAll('[data-disposicion-valor]').forEach(element => {
            const [block, field] = element.dataset.disposicionValor.split('.');
            const value = Number(data[block]?.[field] ?? 0);
            element.textContent = number.format(Number.isFinite(value) ? Math.max(0, value) : 0);
        });
        const list = summary.querySelector('[data-disposicion-cupos-lista]');
        list.replaceChildren();
        cupos.forEach(cupo => {
            const article = document.createElement('article');
            article.className = `slep-record-state slep-record-state--${cupo.disponibles > 0 ? 'info' : 'neutral'} mb-3`;
            article.dataset.cupoClave = cupo.clave;
            const title = document.createElement('strong');
            title.textContent = `${blocks[cupo.bloque] || cupo.bloque} · ${cupo.nombre}`;
            article.append(title, paragraph(cupo.titulo, 'mb-1'), paragraph(cupo.areas.join(' / ') || 'Sin área informada', 'small mb-2'),
                paragraph(`Requeridas: ${number.format(cupo.horas)} h · Ya destinadas: ${number.format(cupo.destinadas)} h · Disponibles: ${number.format(cupo.disponibles)} h`, 'my-2'));
            const state = document.createElement('span');
            state.className = `slep-status-pill slep-status-pill--${cupo.disponibles > 0 ? 'info' : 'neutral'}`;
            state.textContent = cupo.estado;
            article.append(state);
            cupo.destinaciones.forEach(item => article.append(paragraph(`Destinación #${item.id} · ${item.nombre} · ${item.origen} · ${number.format(item.horas)} h destinadas.`, 'small mt-2 mb-0')));
            list.append(article);
        });
        if (!cupos.length) list.append(paragraph('No hay cupos con asignaciones que coincidan con la selección.', 'mb-0 slep-listing__muted'));
        updateSelection();
    };
    selectQuery(select).on('change.disposicion', update);
    selectQuery(cupoSelect).on('change.disposicion', updateSelection);
    hours?.addEventListener('input', updateSelection);
    update();
}

const start = () => document.querySelectorAll('[data-disposicion]').forEach(initialize);
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
else start();
