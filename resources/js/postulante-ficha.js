import flatpickr from 'flatpickr';
import { Spanish } from 'flatpickr/dist/l10n/es.js';
import 'flatpickr/dist/flatpickr.min.css';
import { enhanceSelects, destroySelects, selectQuery } from './slep-listados';
import '../css/postulante-ficha.css';

function initialize(root) {
    const date = root.querySelector('[data-ficha-date]');
    const type = root.querySelector('[data-ficha-tipo]');
    let calendar;
    if (date) {
        const label = root.querySelector(`label[for="${date.id}"]`);
        calendar = flatpickr(date, {
            locale: Spanish, dateFormat: 'Y-m-d', altInput: true, altFormat: 'd-m-Y', disableMobile: true,
            monthSelectorType: 'static', appendTo: date.closest('.modal'), position: 'auto center',
            ariaDateFormat: 'j \\d\\e F \\d\\e Y',
            onReady: (_, __, fp) => {
                fp.calendarContainer.classList.add('slep-date-calendar');
                fp.altInput.id = `${date.id}-visible`;
                fp.altInput.setAttribute('aria-describedby', date.getAttribute('aria-describedby'));
                fp.altInput.setAttribute('aria-label', 'Fecha de término, abrir calendario');
                if (label) label.htmlFor = fp.altInput.id;
                // Escape cierra primero el calendario, conservando el formulario.
                date.closest('.modal').addEventListener('keydown', event => {
                    if (event.key === 'Escape' && fp.isOpen) {
                        event.preventDefault(); event.stopPropagation(); fp.close(); fp.altInput.focus();
                    }
                }, true);
            },
            onChange: () => clearError(date),
        });
        root.querySelector('[data-ficha-date-help]').textContent = 'Selecciona la fecha en el calendario. No se solicita para Indefinido.';
        const clear = document.createElement('button'); clear.type = 'button'; clear.className = 'slep-profile__date-clear'; clear.textContent = 'Limpiar fecha';
        clear.addEventListener('click', () => { calendar.clear(); calendar.altInput.focus(); }); calendar.altInput.after(clear);
        const syncDate = () => {
            const indefinite = type.value === 'Indefinido';
            date.disabled = indefinite; date.required = !indefinite; calendar.altInput.disabled = indefinite; clear.disabled = indefinite;
            if (indefinite) { calendar.clear(); clearError(date); }
        };
        selectQuery(type).on('change.slepFicha', syncDate); syncDate();
    }
    const rows = root.querySelector('[data-ficha-rows]');
    const add = root.querySelector('[data-ficha-add]');
    if (rows) {
        let sequence = rows.children.length;
        const refresh = () => {
            const items = [...rows.querySelectorAll('[data-ficha-row]')];
            items.forEach((row, index) => {
                row.querySelector('[data-ficha-row-number]').textContent = index + 1;
                row.querySelector('[data-ficha-establecimiento]').name = `vinculaciones[${index}][establecimiento_id]`;
                row.querySelector('[data-ficha-horas]').name = `vinculaciones[${index}][cantidad_horas]`;
                row.querySelector('[data-ficha-remove]').disabled = items.length === 1;
                row.querySelector('[data-ficha-remove]').setAttribute('aria-label', `Quitar establecimiento ${index + 1}`);
            });
            add.disabled = items.length >= 10;
            root.querySelector('[data-ficha-row-status]').textContent = `${items.length} de 10 establecimientos. Debe mantenerse al menos uno.`;
        };
        add.addEventListener('click', () => {
            if (rows.children.length >= 10) return;
            const fragment = root.querySelector('[data-ficha-template]').content.cloneNode(true);
            const row = fragment.querySelector('[data-ficha-row]');
            row.querySelectorAll('*').forEach(element => {
                [...element.attributes].forEach(attribute => {
                    if (attribute.value.includes('__INDEX__')) element.setAttribute(attribute.name, attribute.value.replaceAll('__INDEX__', String(sequence)));
                });
            });
            sequence++; rows.append(fragment); enhanceSelects(row, root); refresh(); focusField(row.querySelector('select'));
        });
        rows.addEventListener('click', event => {
            const button = event.target.closest('[data-ficha-remove]');
            if (!button || rows.children.length <= 1) return;
            const row = button.closest('[data-ficha-row]'); destroySelects(row); row.remove(); refresh(); add.focus();
        });
        refresh();
    }
    root.querySelectorAll('[data-ficha-form]').forEach(form => {
        const validate = () => {
            let firstInvalid = null;
            const fail = (field, message) => { showError(field, message); firstInvalid ||= field; };
            form.querySelectorAll('input,textarea,select').forEach(field => clearError(field));
            if (form.dataset.fichaForm === 'contrato') {
                if (!type.value) fail(type, 'Selecciona un tipo de contrato.');
                if (!date.disabled && !/^\d{4}-\d{2}-\d{2}$/.test(date.value)) fail(date, 'Selecciona una fecha de término válida.');
                const seen = new Set();
                form.querySelectorAll('[data-ficha-row]').forEach(row => {
                    const ee = row.querySelector('[data-ficha-establecimiento]'); const hours = row.querySelector('[data-ficha-horas]');
                    if (!ee.value) fail(ee, 'Selecciona un establecimiento.');
                    else if (seen.has(ee.value)) fail(ee, 'Este establecimiento ya está seleccionado.');
                    seen.add(ee.value);
                    if (!/^\d{1,2}$/.test(hours.value.trim()) || Number(hours.value) < 1 || Number(hours.value) > 60) fail(hours, 'Ingresa entre 1 y 60 horas enteras.');
                });
            }
            const reason = form.querySelector('[data-ficha-reason]');
            if (reason && (reason.value.trim().length < 5 || reason.value.length > 500)) fail(reason, 'Ingresa un motivo de entre 5 y 500 caracteres.');
            const observation = form.querySelector('[data-ficha-text]');
            if (observation?.value.length > 1000) fail(observation, 'La observación admite hasta 1.000 caracteres.');
            if (firstInvalid) { focusField(firstInvalid); return false; }
            return true;
        };
        form.addEventListener('submit', event => {
            if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
            if (!validate()) { event.preventDefault(); return; }
            form.dataset.submitting = 'true'; form.setAttribute('aria-busy', 'true');
            const button = form.querySelector('button[type=submit]'); button.disabled = true; button.dataset.originalHtml = button.innerHTML; button.textContent = 'Guardando…';
        });
        form.querySelectorAll('.invalid-feedback').forEach(error => {
            if (error.textContent.trim() && error.id.endsWith('-error')) {
                const field = root.querySelector(`[id="${error.id.slice(0, -6)}"]`); if (field) showError(field, error.textContent.trim());
            }
        });
    });
    root.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('shown.bs.modal', () => {
            enhanceSelects(modal, root);
            const field = modal.querySelector('input.is-invalid,textarea.is-invalid,select.is-invalid') || modal.querySelector('select,textarea,input:not([type=hidden])');
            if (field) focusField(field);
        });
        modal.addEventListener('hide.bs.modal', () => {
            calendar?.close(); modal.querySelectorAll('[data-slep-select]').forEach(select => { if (selectQuery(select).data('select2')) selectQuery(select).select2('close'); });
        });
    });
    if (root.dataset.fichaErrorModal) {
        const modal = document.getElementById(root.dataset.fichaErrorModal);
        if (modal && root.contains(modal)) window.bootstrap?.Modal.getOrCreateInstance(modal).show();
    }
    window.addEventListener('pageshow', () => root.querySelectorAll('[data-ficha-form]').forEach(form => {
        delete form.dataset.submitting; form.removeAttribute('aria-busy');
        form.querySelectorAll('button[data-original-html]').forEach(button => { button.innerHTML = button.dataset.originalHtml; delete button.dataset.originalHtml; button.disabled = false; });
    }));
}

function visualField(field) { return field._flatpickr?.altInput || field; }
function clearError(field) {
    field.classList.remove('is-invalid'); field.removeAttribute('aria-invalid');
    const visible = visualField(field); visible.classList.remove('is-invalid'); visible.removeAttribute('aria-invalid');
    selectQuery(field).next('.select2-container').removeClass('is-invalid').find('.select2-selection').removeAttr('aria-invalid');
    const error = document.getElementById(`${field.id}-error`); if (error) error.textContent = '';
}
function showError(field, message) {
    field.classList.add('is-invalid'); field.setAttribute('aria-invalid', 'true');
    const visible = visualField(field); visible.classList.add('is-invalid'); visible.setAttribute('aria-invalid', 'true');
    selectQuery(field).next('.select2-container').addClass('is-invalid').find('.select2-selection').attr('aria-invalid', 'true');
    const error = document.getElementById(`${field.id}-error`); if (error) error.textContent = message;
}
function focusField(field) {
    if (selectQuery(field).data('select2')) selectQuery(field).next('.select2-container').find('.select2-selection').trigger('focus');
    else visualField(field).focus();
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('[data-ficha]').forEach(initialize));
else document.querySelectorAll('[data-ficha]').forEach(initialize);
