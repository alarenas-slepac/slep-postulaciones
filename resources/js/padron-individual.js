import flatpickr from 'flatpickr';
import { Spanish } from 'flatpickr/dist/l10n/es.js';
import 'flatpickr/dist/flatpickr.min.css';
import { enhanceSelects, selectQuery } from './slep-listados';
import '../css/postulante-ficha.css';
import '../css/padron-individual.css';

function validDate(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
    const date = new Date(`${value}T00:00:00Z`);
    return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value;
}

function visibleField(field) { return field._flatpickr?.altInput || field; }
function error(field, message = '') {
    for (const input of new Set([field, visibleField(field)])) {
        input.classList.toggle('is-invalid', !!message);
        if (message) input.setAttribute('aria-invalid', 'true'); else input.removeAttribute('aria-invalid');
    }
    const selection = selectQuery(field).next('.select2-container');
    selection.toggleClass('is-invalid', !!message);
    if (message) selection.find('.select2-selection').attr('aria-invalid', 'true');
    else selection.find('.select2-selection').removeAttr('aria-invalid');
    const feedback = document.getElementById(`${field.id}-error`);
    if (feedback) feedback.textContent = message;
}
function focus(field) {
    let disclosure = field.closest('details');
    while (disclosure) { disclosure.open = true; disclosure = disclosure.parentElement.closest('details'); }
    if (selectQuery(field).data('select2')) selectQuery(field).next('.select2-container').find('.select2-selection').trigger('focus');
    else visibleField(field).focus();
}

function initialize(root) {
    enhanceSelects(root);
    root.querySelectorAll('[data-padron-date]').forEach(input => {
        const previous = input.value;
        const label = root.querySelector(`label[for="${input.id}"]`);
        if (previous && !validDate(previous)) input.value = '';
        const picker = flatpickr(input, {
            locale: Spanish, dateFormat: 'Y-m-d', altInput: true, altFormat: 'd-m-Y',
            disableMobile: true, monthSelectorType: 'static', ariaDateFormat: 'j \\d\\e F \\d\\e Y',
            onChange: () => error(input),
        });
        picker.calendarContainer.classList.add('slep-date-calendar', 'padron-date-calendar');
        picker.calendarContainer.setAttribute('role', 'dialog');
        picker.calendarContainer.setAttribute('aria-label', `Calendario: ${label?.textContent.replace('*', '').trim() || 'seleccionar fecha'}`);
        picker.altInput.id = `${input.id}-visible`;
        picker.altInput.removeAttribute('data-padron-field');
        picker.altInput.removeAttribute('data-padron-date');
        picker.altInput.setAttribute('aria-describedby', input.getAttribute('aria-describedby'));
        picker.altInput.setAttribute('aria-haspopup', 'dialog');
        picker.altInput.placeholder = 'DD-MM-AAAA';
        if (input.hasAttribute('aria-invalid')) picker.altInput.setAttribute('aria-invalid', 'true');
        if (label) label.htmlFor = picker.altInput.id;
        if (previous && !validDate(previous)) { input.value = previous; picker.altInput.value = previous; }
        const help = document.getElementById(`${input.id}-help`);
        help.textContent = 'Selecciona una fecha. Formato DD-MM-AAAA.';
        const clear = document.createElement('button'); clear.type = 'button'; clear.className = 'slep-profile__date-clear'; clear.textContent = 'Limpiar fecha';
        clear.setAttribute('aria-label', `Limpiar ${label?.textContent.replace('*', '').trim().toLocaleLowerCase('es') || 'fecha'}`);
        clear.addEventListener('click', () => { picker.clear(); picker.altInput.focus(); });
        picker.altInput.after(clear);
    });
    const tramo = root.querySelector('#tramo');
    if (tramo) selectQuery(tramo).on('select2:open.padron', () => {
        const search = root.querySelector('.select2-container--open .select2-search__field');
        if (search) search.maxLength = 100;
    }).on('select2:selecting.padron', event => {
        if (String(event.params.args.data.id).length > 100) { event.preventDefault(); error(tramo, 'El tramo admite hasta 100 caracteres.'); }
    });
    const form = root.querySelector('[data-padron-form]');
    if (form) {
        const type = form.elements.tipocontrato, end = form.elements.fecha_termino;
        const syncEnd = () => {
            end.required = ['REEMPLAZO', 'PLAZO FIJO'].includes(type.value);
            if (end._flatpickr) end._flatpickr.altInput.required = end.required;
            root.querySelector('[data-padron-required="fecha_termino"]').hidden = !end.required;
        };
        selectQuery(type).on('change.padron', syncEnd); syncEnd();
        form.querySelectorAll('[data-padron-field]').forEach(field => {
            field.addEventListener('input', () => error(field));
            selectQuery(field).on('change.padron', () => error(field));
        });
        const selectedAssignments = () => [...form.querySelectorAll('[data-padron-assignment]:checked')];
        const syncSelection = event => {
            const status = form.querySelector('[data-padron-selection-status]');
            if (status) status.textContent = selectedAssignments().length ? `${selectedAssignments().length} asignaciones seleccionadas para liberar al guardar.` : 'Ninguna asignación seleccionada para liberar.';
            if (event) {
                root.querySelector('#liberar_asignaciones-error').textContent = '';
                error(form.elements.confirmar_liberacion);
            }
        };
        form.querySelectorAll('[data-padron-assignment]').forEach(field => field.addEventListener('change', syncSelection)); syncSelection();
        form.addEventListener('submit', event => {
            if (form.dataset.submitting) { event.preventDefault(); return; }
            const invalid = [];
            const fail = (field, message) => { error(field, message); if (!invalid.includes(field)) invalid.push(field); };
            for (const field of form.querySelectorAll('[data-padron-field]')) {
                error(field); const value = field.value.trim();
                if (field.required && !value) { fail(field, 'Completa este campo.'); continue; }
                if (value && field.dataset.padronDate !== undefined && !validDate(value)) fail(field, 'Selecciona una fecha válida.');
                if (value && field.dataset.padronInteger !== undefined && (!/^\d+$/.test(value) || Number(value) < Number(field.dataset.min) || Number(value) > Number(field.dataset.max))) fail(field, `Ingresa un número entero entre ${field.dataset.min} y ${field.dataset.max}.`);
                if (field.maxLength > 0 && value.length > field.maxLength) fail(field, `Máximo ${field.maxLength} caracteres.`);
                if (value && field.minLength > 0 && value.length < field.minLength) fail(field, `Ingresa al menos ${field.minLength} caracteres.`);
            }
            if (tramo.value.length > 100) fail(tramo, 'El tramo admite hasta 100 caracteres.');
            if (validDate(form.elements.fecha_nacimiento.value) && form.elements.fecha_nacimiento.value >= form.dataset.today) fail(form.elements.fecha_nacimiento, 'La fecha de nacimiento debe ser anterior a hoy.');
            if (validDate(end.value) && validDate(form.elements.fecha_ingreso.value) && end.value < form.elements.fecha_ingreso.value) fail(end, 'La fecha de término no puede ser anterior al inicio.');
            const total = form.elements.jornada;
            const components = [total, form.elements.jornada_basica, form.elements.jornada_media];
            if (!components.some(field => invalid.includes(field)) && Number(form.elements.jornada_basica.value) + Number(form.elements.jornada_media.value) > Number(total.value)) fail(total, 'La suma de jornada básica y media no puede superar la jornada total.');
            const assignments = selectedAssignments();
            if (assignments.length) {
                const checkbox = form.elements.confirmar_liberacion, reason = form.elements.justificacion_liberacion;
                error(checkbox);
                if (!checkbox.checked) fail(checkbox, 'Confirma la liberación de las asignaciones seleccionadas.');
                if (reason.value.trim().length < 10) fail(reason, 'Justifica la liberación con al menos 10 caracteres.');
                const destination = assignments.find(field => field.dataset.origen === form.elements.establecimiento_id.value);
                if (destination) {
                    invalid.push(destination);
                    root.querySelector('#liberar_asignaciones-error').textContent = 'No puedes liberar asignaciones del establecimiento de destino. Revisa la selección.';
                }
            }
            const summary = form.querySelector('[data-padron-validation]');
            summary.hidden = !invalid.length;
            summary.textContent = invalid.length ? 'Revisa los campos señalados antes de guardar. Los cambios aún no se han enviado.' : '';
            if (invalid.length) { event.preventDefault(); focus(invalid[0]); return; }
            busy(form, 'Guardando…');
        });
    }
    const search = root.querySelector('[data-padron-search]');
    search.addEventListener('submit', event => {
        const rut = search.elements.rut;
        if (search.dataset.submitting) { event.preventDefault(); return; }
        error(rut);
        if (!rut.value.trim()) { event.preventDefault(); error(rut, 'Ingresa un RUT con su dígito verificador.'); rut.focus(); return; }
        busy(search, 'Consultando…');
    });
    window.addEventListener('pageshow', () => root.querySelectorAll('form').forEach(form => {
        delete form.dataset.submitting; form.removeAttribute('aria-busy');
        const button = form.querySelector('button[data-original-html]');
        if (button) { button.innerHTML = button.dataset.originalHtml; delete button.dataset.originalHtml; button.disabled = false; }
        const status = form.querySelector('[data-padron-submit-status]'); if (status) status.textContent = '';
    }));
    const first = root.querySelector('input.is-invalid:not([type="hidden"]),select.is-invalid,textarea.is-invalid') || root.querySelector('[data-padron-date].is-invalid');
    if (first) focus(first); else root.querySelector('[data-padron-errors]')?.focus();
}

function busy(form, text) {
    form.dataset.submitting = 'true'; form.setAttribute('aria-busy', 'true');
    const button = form.querySelector('button[type="submit"]'); button.dataset.originalHtml = button.innerHTML; button.textContent = text; button.disabled = true;
    const status = form.querySelector('[data-padron-submit-status]'); if (status) status.textContent = text;
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('[data-padron-individual]').forEach(initialize));
else document.querySelectorAll('[data-padron-individual]').forEach(initialize);
