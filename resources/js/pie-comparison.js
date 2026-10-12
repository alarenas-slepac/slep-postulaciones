import { preview, summarize, hourFields, formatHours, difference } from './pie-comparison-calculation.js';

function initialize() {
    const filter = document.getElementById('pc-filters');
    const establishment = document.getElementById('pc-establishment');
    if (!filter || !establishment) return;
    if (window.jQuery?.fn?.select2) window.jQuery(establishment).select2({
        theme: 'bootstrap-5', width: '100%', placeholder: 'Buscar establecimiento por RBD o nombre',
        dropdownCssClass: 'pc-select-dropdown', language: { noResults: () => 'Sin resultados', searching: () => 'Buscando…' },
    });
    const form = document.getElementById('pc-editor');
    const dataElement = document.getElementById('pc-data');
    if (!form || !dataElement) return;
    const data = JSON.parse(dataElement.textContent), states = new Map(), elements = new Map();
    const save = document.getElementById('pc-save'), status = document.getElementById('pc-dirty-status');
    const errorBox = document.getElementById('pc-errors');
    let dirty = 0, submitting = false, navigating = false;
    for (const element of form.querySelectorAll('[data-course-id]')) elements.set(Number(element.dataset.courseId), element);
    const display = (container, selector, value) => { const output = container.querySelector(selector); if (output) output.textContent = value; };

    function refresh() {
        for (const row of Object.values(data.rows)) {
            const element = elements.get(row.id);
            const neet = element?.querySelector('[data-count="neet"]'), neep = element?.querySelector('[data-count="neep"]');
            const value = row.editable ? preview(row, neet.value, neep.value, data.matrix)
                : { changed: false, valid: true, neet: row.neet, neep: row.neep, total: row.neet + row.neep, calculation: row.calculation };
            states.set(row.id, value);
            element.classList.toggle('is-dirty', value.changed);
            display(element, '[data-output="total"]', value.total == null ? '—' : value.total);
            for (const field of hourFields) display(element, `[data-output="${field}"]`, formatHours(value.valid ? value.calculation[field] : null));
            display(element, '[data-output="note"]', value.valid ? value.calculation.calculo_observacion || '' : 'Corrige las cantidades para calcular las horas.');
            display(element, '[data-error]', value.error || '');
            for (const input of [neet, neep].filter(Boolean)) { input.classList.toggle('is-invalid', !value.valid); input.setAttribute('aria-invalid', String(!value.valid)); }
        }
        for (const year of data.years) {
            const values = Object.values(data.rows).filter(row => row.year === year).map(row => states.get(row.id));
            const total = summarize(values), container = document.querySelector(`[data-summary-year="${year}"]`);
            for (const field of ['neet', 'neep', 'total']) display(container, `[data-summary="${field}"]`, total.invalid ? '—' : total[field]);
            for (const field of hourFields) display(container, `[data-summary="${field}"]`, total.invalid ? '—' : formatHours(total[field]));
            display(container, '[data-summary="notice"]', total.invalid ? 'Corrige las cantidades: los totales están pendientes de cálculo.'
                : total.unknown ? `${total.unknown} cursos tienen horas sin cálculo; la suma de horas es parcial.` : 'Los totales se actualizan al instante. Los cambios se aplican al guardar.');
        }
        data.groups.forEach((group, index) => {
            const totals = data.years.map(year => summarize(group.years[year].map(id => states.get(id))));
            const hasBoth = data.years.every(year => group.years[year].length), valid = hasBoth && totals.every(total => !total.invalid);
            const element = form.querySelector(`[data-group="${index}"]`);
            display(element, '[data-delta="students"]', valid ? difference(totals[0].total, totals[1].total) : '—');
            display(element, '[data-delta="hours"]', valid && totals.every(total => !total.unknown) ? difference(totals[0].total_crono_minutos, totals[1].total_crono_minutos, true) : '—');
        });
        dirty = [...states.values()].filter(state => state.changed).length;
        const invalid = [...states.values()].some(state => state.changed && !state.valid);
        save.disabled = submitting || !dirty || invalid;
        status.textContent = dirty ? `${dirty} cursos/secciones con cambios pendientes${invalid ? '; corrige los errores antes de guardar.' : '.'}` : 'No hay modificaciones pendientes.';
    }

    form.addEventListener('input', refresh);
    filter.addEventListener('submit', event => {
        if (dirty && !window.confirm('Hay cambios sin guardar. ¿Quieres descartarlos y cambiar la comparación?')) { event.preventDefault(); return; }
        navigating = true;
    });
    window.addEventListener('beforeunload', event => { if (dirty && !submitting && !navigating) { event.preventDefault(); event.returnValue = ''; } });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (submitting || !dirty || !form.reportValidity()) return;
        const rows = Object.values(data.rows).filter(row => states.get(row.id).changed).map(row => {
            const state = states.get(row.id);
            return { course_id: row.id, neet: state.neet, neep: state.neep, version: elements.get(row.id).querySelector('[data-version]').value };
        });
        if (rows.some(row => !states.get(row.course_id).valid)) return;
        const payload = Object.fromEntries(['establecimiento_id', 'anio_a', 'anio_b', 'rules_token'].map(name => [name, form.elements.namedItem(name).value]));
        payload.rows = rows; payload.expected_rows = rows.length;
        submitting = true; save.disabled = true; save.textContent = 'Guardando todas las modificaciones…'; errorBox.classList.add('d-none');
        try {
            const response = await fetch(form.action, { method: 'PUT', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': form.elements.namedItem('_token').value }, body: JSON.stringify(payload) });
            const result = await response.json();
            if (!response.ok) {
                const list = errorBox.querySelector('ul'); list.replaceChildren();
                for (const message of Object.values(result.errors || { general: ['No se pudo guardar. Revisa tu sesión y recarga los datos.'] }).flat()) {
                    const item = document.createElement('li'); item.textContent = message; list.append(item);
                }
                errorBox.classList.remove('d-none'); errorBox.focus();
                return;
            }
            const destination = new URL(result.redirect, window.location.href);
            if (destination.origin !== window.location.origin) throw new Error('Unexpected redirect');
            dirty = 0; navigating = true; window.location.assign(destination.href);
        } catch {
            errorBox.querySelector('ul').replaceChildren(); const item = document.createElement('li');
            item.textContent = 'No se confirmó el guardado. Revisa la conexión o tu sesión y recarga los datos guardados antes de volver a intentarlo.';
            errorBox.querySelector('ul').append(item); errorBox.classList.remove('d-none'); errorBox.focus();
        } finally { if (!navigating) { submitting = false; save.textContent = 'Guardar todas las modificaciones'; refresh(); } }
    });
    refresh();
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
else initialize();
