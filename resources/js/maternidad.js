import { enhanceSelects, selectQuery } from './slep-listados';
import flatpickr from 'flatpickr';
import { Spanish } from 'flatpickr/dist/l10n/es.js';
import 'flatpickr/dist/flatpickr.min.css';
import '../css/maternidad.css';

function dates(scope) {
    scope.querySelectorAll('[data-mat-date]').forEach(input => {
        if (input._flatpickr) return;
        const picker = flatpickr(input, { locale: Spanish, dateFormat: 'Y-m-d', altInput: true, altFormat: 'd-m-Y', disableMobile: true, monthSelectorType: 'static', ariaDateFormat: 'j \\d\\e F \\d\\e Y' });
        picker.calendarContainer.classList.add('slep-date-calendar');
        if (!picker.altInput) return;
        picker.altInput.id = `${input.id}-visible`;
        picker.altInput.setAttribute('aria-describedby', input.getAttribute('aria-describedby') || '');
        if (input.hasAttribute('aria-invalid')) picker.altInput.setAttribute('aria-invalid', 'true');
        scope.querySelector(`label[for="${input.id}"]`)?.setAttribute('for', picker.altInput.id);
        const clear = document.createElement('button'); clear.type = 'button'; clear.className = 'mat-date-clear'; clear.textContent = 'Limpiar fecha';
        clear.addEventListener('click', () => { picker.clear(); picker.altInput.focus(); }); picker.altInput.after(clear);
    });
}

function initialize(root) {
    enhanceSelects(root);
    dates(root);
    const form = root.querySelector('[data-mat-form]');
    const errors = root.querySelector('[data-mat-errors]');
    if (errors) { errors.tabIndex = -1; errors.focus(); }
    if (!form) return;
    const field = name => form.querySelector(`[name="${name}"]:not([type="hidden"])`) || form.querySelector(`[name="${name}"]`);
    const set = (name, value) => {
        const input = field(name);
        if (!input) return;
        if (input._flatpickr) input._flatpickr.setDate(value || '', true, 'Y-m-d');
        else input.value = value ?? '';
        if (input.matches('select')) selectQuery(input).trigger('change');
    };
    const lookup = root.querySelector('#mat-busqueda');
    if (lookup) {
        let candidates = []; let request;
        try { candidates = JSON.parse(lookup.dataset.candidatos || '[]'); } catch { /* Búsqueda GET disponible. */ }
        const rut = lookup.querySelector('[name="rut"]');
        const select = lookup.querySelector('[name="fuente"]');
        const status = lookup.querySelector('[role="status"]');
        const save = form.querySelector('[data-mat-save]');
        const apply = candidate => {
            for (const key of ['rut', 'fuente', 'nombres', 'apellido_paterno', 'apellido_materno', 'estamento', 'calidad_juridica', 'fecha_ingreso', 'fecha_termino']) set(key, candidate?.[key] || '');
            root.querySelector('#mat-establecimiento').value = candidate?.establecimiento_nombre || '';
            root.querySelector('#mat-comuna').value = candidate?.comuna || '';
            root.querySelector('[data-mat-nombre-aviso]').hidden = !candidate?.nombre_requiere_revision;
            save.disabled = !candidate;
            root.querySelector('[data-mat-save-help]').textContent = candidate ? 'Revise los nombres y complete los antecedentes del caso.' : 'Seleccione primero el vínculo laboral de la funcionaria.';
        };
        selectQuery(select).on('change.maternidad', () => {
            const candidate = candidates.find(item => item.fuente === select.value);
            if (candidates.length) apply(candidate);
        });
        rut.addEventListener('input', () => { request?.abort(); candidates = []; select.replaceChildren(new Option('Seleccione un vínculo', '')); selectQuery(select).trigger('change'); apply(null); status.textContent = 'Busque el RUT para actualizar los vínculos disponibles.'; });
        lookup.addEventListener('submit', async event => {
            event.preventDefault(); request?.abort(); request = new AbortController();
            lookup.setAttribute('aria-busy', 'true'); status.textContent = 'Buscando vínculos laborales…';
            const button = lookup.querySelector('button'); button.disabled = true;
            try {
                const url = new URL(lookup.dataset.url, location.origin); url.searchParams.set('rut', rut.value);
                const response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', signal: request.signal });
                const data = await response.json().catch(() => { throw new Error('No se pudo leer la respuesta. Recargue la página y vuelva a intentar.'); });
                if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || 'No se pudo realizar la búsqueda.');
                candidates = data.candidatos || [];
                select.replaceChildren(new Option('Seleccione un vínculo', ''));
                candidates.forEach(candidate => select.add(new Option(candidate.descripcion, candidate.fuente)));
                select.value = candidates.length === 1 ? candidates[0].fuente : '';
                selectQuery(select).trigger('change');
                if (!candidates.length) apply(null);
                status.textContent = candidates.length ? `${candidates.length} vínculo(s) encontrado(s). Seleccione el correspondiente y revise los nombres.` : 'No se encontraron vínculos. Revise el RUT o los antecedentes en padrón y reemplazos.';
            } catch (error) {
                if (error.name !== 'AbortError') { apply(null); status.textContent = error.message; }
            } finally { lookup.removeAttribute('aria-busy'); button.disabled = false; }
        });
    }

    const enabled = name => !!form.querySelector(`[data-mat-flag="${name}"]`)?.checked;
    const syncSections = () => form.querySelectorAll('[data-mat-condicional]').forEach(section => {
        section.hidden = !enabled(section.dataset.matCondicional);
    });
    let existing;
    try { existing = JSON.parse(form.dataset.documentos || '[]'); } catch { existing = []; }
    const hasDoc = type => existing.includes(type) || !!form.querySelector(`[name="documentos[${type}]"]`)?.files?.length;
    const date = name => {
        const raw = field(name)?.value;
        if (!/^\d{4}-\d{2}-\d{2}$/.test(raw || '')) return null;
        const parsed = new Date(`${raw}T00:00:00Z`);
        return Number.isNaN(parsed.getTime()) || parsed.toISOString().slice(0, 10) !== raw ? null : parsed;
    };
    const addYears = (value, years) => {
        if (!value) return null;
        const next = new Date(value); const month = next.getUTCMonth();
        next.setUTCFullYear(next.getUTCFullYear() + years);
        if (next.getUTCMonth() !== month) next.setUTCDate(0);
        return next;
    };
    const plus84 = value => { if (value) value.setUTCDate(value.getUTCDate() + 84); return value; };
    const preview = () => {
        let dominga = null;
        if (enabled('ley_dominga') && hasDoc('ley_dominga')) {
            dominga = date('fecha_ley_dominga');
            const regla = field('regla_fuero_dominga')?.value || form.dataset.reglaDominga;
            if (regla === 'anio_84_dias') dominga = plus84(addYears(dominga, 1));
            else if (dominga) dominga.setUTCDate(dominga.getUTCDate() + 30);
        }
        const values = {
            estimado: plus84(addYears(date('fpp'), 1)),
            nacimiento: hasDoc('nacimiento') ? plus84(addYears(date('fnh'), 1)) : null,
            dominga,
            lactancia: enabled('lactancia') && hasDoc('lactancia') && hasDoc('nacimiento') ? addYears(date('fnh'), 2) : null,
        };
        const format = new Intl.DateTimeFormat('es-CL', { timeZone: 'UTC', day: '2-digit', month: '2-digit', year: 'numeric' });
        for (const [key, value] of Object.entries(values)) {
            const target = form.querySelector(`[data-mat-fecha="${key}"]`);
            if (target) target.textContent = value ? format.format(value) : 'Pendiente de fecha y/o respaldo';
        }
    };
    form.addEventListener('change', event => {
        if (event.target.name === 'documentos[postnatal_parental]' && event.target.files.length) form.querySelector('[data-mat-flag="postnatal_parental"]').checked = true;
        syncSections(); preview();
    });
    selectQuery(form).on('change.maternidad', 'select', () => { syncSections(); preview(); });
    syncSections(); preview();

    const children = form.querySelector('[data-mat-hijos]');
    const add = form.querySelector('[data-mat-agregar-hijo]');
    let index = Math.max(-1, ...[...children.querySelectorAll('input[name]')].map(input => Number(input.name.match(/^hijos\[(\d+)\]/)?.[1] ?? -1))) + 1;
    const updateChildren = () => {
        const rows = children.querySelectorAll('[data-mat-hijo]');
        rows.forEach((row, i) => { row.querySelector('[data-mat-numero-hijo]').textContent = i + 1; });
        add.disabled = rows.length >= 10;
        form.querySelector('[data-mat-hijos-estado]').textContent = `${rows.length} hijo(s) en el formulario. Máximo 10 por caso.`;
    };
    add.addEventListener('click', () => {
        if (children.querySelectorAll('[data-mat-hijo]').length >= 10) return;
        const template = form.querySelector('[data-mat-hijo-template]').innerHTML.replaceAll('__INDEX__', String(index)).replaceAll('--INDEX--', String(index));
        index++;
        children.insertAdjacentHTML('beforeend', template);
        const row = children.lastElementChild; dates(row); updateChildren(); row.querySelector('input:not([type="hidden"])')?.focus();
    });
    children.addEventListener('click', event => {
        const button = event.target.closest('[data-mat-quitar-hijo]');
        if (!button) return;
        const row = button.closest('[data-mat-hijo]');
        row.querySelectorAll('[data-mat-date]').forEach(input => input._flatpickr?.destroy()); row.remove(); updateChildren(); add.focus();
    });
    updateChildren();
    form.addEventListener('submit', () => { const save = form.querySelector('[data-mat-save]'); save.disabled = true; save.textContent = 'Guardando…'; form.setAttribute('aria-busy', 'true'); });
    window.addEventListener('pageshow', () => { form.removeAttribute('aria-busy'); const save = form.querySelector('[data-mat-save]'); save.textContent = 'Guardar caso'; save.disabled = !!lookup && !form.querySelector('[name="fuente"]').value; });
}

const start = () => document.querySelectorAll('.mat-workspace').forEach(initialize);
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true }); else start();
