import './slep-listados';
import flatpickr from 'flatpickr';
import { Spanish } from 'flatpickr/dist/l10n/es.js';
import 'flatpickr/dist/flatpickr.min.css';
import '../css/postulante-ficha.css';
import '../css/dotacion-bloqueos.css';

function visibleField(field) { return field._flatpickr?.altInput || field; }
function fieldError(field, message = '') {
    for (const input of new Set([field, visibleField(field)])) {
        input.classList.toggle('is-invalid', !!message);
        if (message) input.setAttribute('aria-invalid', 'true'); else input.removeAttribute('aria-invalid');
    }
    const id = field.name === 'motivo' ? field.id.replace(/-motivo$/, '-error') : `${field.id}-error`;
    const feedback = document.getElementById(id);
    if (feedback) feedback.textContent = message;
}

function initialize() {
    document.querySelectorAll('[data-dotacion-lock-until]').forEach(input => {
        if (input.disabled) return;
        const label = document.querySelector(`label[for="${input.id}"]`);
        const previous = input.value;
        const picker = flatpickr(input, {
            locale: Spanish, enableTime: true, time_24hr: true, minuteIncrement: 1,
            dateFormat: 'Y-m-d H:i', altInput: true, altFormat: 'd-m-Y H:i', disableMobile: true, monthSelectorType: 'static',
            onChange: () => fieldError(input),
        });
        picker.calendarContainer.classList.add('slep-date-calendar');
        picker.calendarContainer.setAttribute('role', 'dialog');
        picker.calendarContainer.setAttribute('aria-label', 'Fecha y hora del próximo bloqueo');
        picker.altInput.id = `${input.id}-visible`;
        picker.altInput.removeAttribute('data-dotacion-lock-until');
        picker.altInput.setAttribute('aria-describedby', input.getAttribute('aria-describedby'));
        picker.altInput.setAttribute('aria-haspopup', 'dialog');
        picker.altInput.placeholder = 'DD-MM-AAAA HH:MM';
        if (input.hasAttribute('aria-invalid')) picker.altInput.setAttribute('aria-invalid', 'true');
        if (label) label.htmlFor = picker.altInput.id;
        if (previous && !picker.selectedDates.length) { input.value = previous; picker.altInput.value = previous; }
        const clear = document.createElement('button');
        clear.type = 'button'; clear.className = 'slep-profile__date-clear'; clear.textContent = 'Limpiar fecha y hora';
        clear.addEventListener('click', () => { picker.clear(); picker.altInput.focus(); });
        picker.altInput.after(clear);
    });
    const invalid = document.querySelector('[data-dotacion-lock-form] .is-invalid');
    if (invalid) visibleField(invalid).focus(); else document.querySelector('[data-dotacion-lock-error]')?.focus();
    document.querySelectorAll('[data-dotacion-lock-form]').forEach(form => {
        form.noValidate = true;
        const until = form.elements.desbloqueo_hasta;
        const initializedAt = Date.now();
        const serverLocal = until ? flatpickr.parseDate(until.dataset.nowLocal, 'Y-m-d H:i') : null;
        form.addEventListener('submit', event => {
            if (form.dataset.submitting) { event.preventDefault(); return; }
            const field = form.elements.motivo;
            let firstInvalid = null;
            const message = field.value.length > 1000 ? 'El motivo admite hasta 1.000 caracteres.'
                : (field.required && !field.value.trim() ? 'Indica el motivo de la autorización.' : '');
            fieldError(field, message);
            if (message) firstInvalid = field;
            if (until) {
                const date = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/.test(until.value) ? flatpickr.parseDate(until.value, 'Y-m-d H:i') : null;
                const valid = date && flatpickr.formatDate(date, 'Y-m-d H:i') === until.value
                    && (!serverLocal || date.getTime() > serverLocal.getTime() + Date.now() - initializedAt);
                fieldError(until, valid ? '' : 'Selecciona una fecha y hora futuras para volver a bloquear.');
                if (!valid) firstInvalid ||= until;
            }
            if (firstInvalid) { event.preventDefault(); visibleField(firstInvalid).focus(); return; }
            form.dataset.submitting = 'true'; form.setAttribute('aria-busy', 'true');
            const button = form.querySelector('button[type=submit]'); button.dataset.originalHtml = button.innerHTML; button.textContent = 'Guardando…'; button.disabled = true;
        });
    });
    let readonly = false;
    function enforceReadonly() {
        if (readonly) return;
        readonly = true;
        // Presentación complementaria. El middleware vuelve a verificar cada escritura,
        // incluso sin JavaScript y desde formularios abiertos antes de activar el bloqueo.
        document.querySelectorAll('.slep-content-inner form').forEach(form => {
            if (['get', 'dialog'].includes((form.getAttribute('method') || 'get').toLowerCase())) return;
            form.querySelectorAll('input,select,textarea,button').forEach(field => { field.disabled = true; });
            form.addEventListener('submit', event => event.preventDefault());
        });
        document.addEventListener('submit', event => {
            if (event.target.matches('.slep-content-inner form') && !['get', 'dialog'].includes((event.target.getAttribute('method') || 'get').toLowerCase())) event.preventDefault();
        }, true);
    }
    if (document.querySelector('[data-dotacion-solo-consulta]')) enforceReadonly();
    const temporal = document.querySelector('[data-dotacion-unlock-seconds]');
    if (temporal) {
        const expiresAt = Date.now() + Number(temporal.dataset.dotacionUnlockSeconds) * 1000;
        let timer;
        const checkExpiry = () => {
            clearTimeout(timer);
            const remaining = expiresAt - Date.now();
            if (remaining > 0) { timer = setTimeout(checkExpiry, Math.min(remaining, 60000)); return; }
            enforceReadonly();
            const status = temporal.querySelector('[data-dotacion-unlock-status]');
            status?.classList.replace('slep-record-state--info', 'slep-record-state--warning');
            const title = temporal.querySelector('[data-dotacion-unlock-title]');
            const message = temporal.querySelector('[data-dotacion-unlock-message]');
            if (title) title.textContent = 'Autorización vencida: edición bloqueada';
            if (message) message.textContent = 'El plazo autorizado terminó. Puedes seguir consultando y descargando informes. Para volver a editar, solicita una nueva autorización.';
        };
        checkExpiry();
        window.addEventListener('focus', checkExpiry);
        window.addEventListener('pageshow', checkExpiry);
    }
    window.addEventListener('pageshow', () => document.querySelectorAll('[data-dotacion-lock-form]').forEach(form => {
        delete form.dataset.submitting; form.removeAttribute('aria-busy');
        const button = form.querySelector('button[data-original-html]');
        if (button) { button.innerHTML = button.dataset.originalHtml; delete button.dataset.originalHtml; button.disabled = false; }
    }));
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize); else initialize();
