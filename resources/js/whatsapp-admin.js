const states = {
    disabled: ['Desactivado', 'secondary', 'La configuración del servidor mantiene la integración desactivada.'],
    idle: ['Sin vincular', 'secondary', 'Genera el QR para vincular el número de notificaciones.'],
    connecting: ['Conectando', 'info', 'Espera mientras se prepara o recupera la sesión.'],
    qr: ['QR disponible', 'primary', 'Escanea desde los dispositivos vinculados de WhatsApp Messenger.'],
    connected: ['Conectado', 'success', 'La sesión está disponible para enviar un mensaje de prueba.'],
    reconnecting: ['Reconectando', 'warning', 'La conexión se está recuperando. Los envíos pendientes respetan sus reintentos.'],
    paused: ['Requiere revisión', 'warning', 'La conexión se detuvo. Revisa el servicio y usa Generar / renovar QR para reanudarla.'],
    logged_out: ['Sesión cerrada', 'warning', 'Vuelve a generar el QR para vincular el dispositivo.'],
    unlinked: ['Desvinculado', 'secondary', 'Genera un nuevo QR cuando quieras vincular el número de notificaciones.'],
    unavailable: ['Servicio no disponible', 'danger', 'Comprueba el servicio y su configuración con el administrador del servidor.'],
};
const statuses = { queued: 'En cola', sending: 'Enviando', retrying: 'Esperando reintento', sent: 'Enviado', unknown: 'Resultado incierto', failed: 'Fallido', cancelled: 'Cancelado' };
const errors = { disabled: 'La integración está desactivada.', configuration: 'Revisa la configuración del servicio.', authentication: 'La autenticación entre servicios necesita revisión.', queue_configuration: 'Se requiere una cola persistente para WhatsApp.', queue_unavailable: 'No fue posible poner el mensaje en cola.', connected: 'La sesión ya está conectada. Desvincúlala sólo si necesitas cambiar el dispositivo.', busy: 'Hay una operación en curso. Espera y vuelve a intentar.', idempotency_conflict: 'Esta solicitud ya se utilizó con otro mensaje. Actualiza la página para preparar una nueva prueba.' };

export function qrIsValid(image, expiresAt, now = Date.now()) {
    return typeof image === 'string' && /^data:image\/png;base64,[A-Za-z0-9+/=]+$/.test(image)
        && Number.isFinite(Date.parse(expiresAt)) && Date.parse(expiresAt) > now;
}

export function initialise(root) {
    const get = selector => root.querySelector(selector);
    const enabled = root.dataset.enabled === '1';
    const form = get('[data-wa-test]');
    const feedback = get('[data-wa-feedback]');
    const csrf = form.querySelector('[name="_token"]').value;
    let pending = false; let refreshing = false; let timer; let lastState; let qrExpiresAt = 0;
    function clearQr() {
        const image = get('[data-wa-qr]'); image.removeAttribute('src'); image.classList.add('d-none');
        get('[data-wa-qr-empty]').classList.remove('d-none'); get('[data-wa-qr-expiry]').textContent = ''; qrExpiresAt = 0;
    }
    function show(text, danger = false) {
        feedback.textContent = text; feedback.className = `alert alert-${danger ? 'danger' : 'success'}`;
    }
    async function request(url, method = 'GET', data) {
        const response = await fetch(url, { method, credentials: 'same-origin', cache: 'no-store',
            headers: { Accept: 'application/json', ...(method !== 'GET' ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf } : {}) },
            ...(data ? { body: JSON.stringify(data) } : {}), signal: AbortSignal.timeout(25000) });
        const result = await response.json();
        if (!response.ok) {
            for (const [name, messages] of Object.entries(result.errors ?? {})) {
                const field = form.elements.namedItem(name); const error = get(`[data-wa-error="${name}"]`);
                if (field && error) { field.classList.add('is-invalid'); error.textContent = messages[0]; }
            }
            throw new Error(errors[result.error] ?? (response.status === 419 ? 'La sesión venció. Actualiza esta página.' : 'No fue posible completar la operación. Revisa el servicio y vuelve a intentar.'));
        }
        return result;
    }
    function render(data) {
        const [label, tone, help] = states[data.state] ?? states.unavailable;
        lastState = data.state;
        get('[data-wa-state]').textContent = label; get('[data-wa-state]').className = `badge rounded-pill text-bg-${tone}`;
        get('[data-wa-state-help]').textContent = help;
        const image = get('[data-wa-qr]');
        const valid = qrIsValid(data.qr, data.qr_expires_at);
        qrExpiresAt = valid ? Date.parse(data.qr_expires_at) : 0;
        image.classList.toggle('d-none', !valid); get('[data-wa-qr-empty]').classList.toggle('d-none', valid);
        if (valid) image.src = data.qr; else image.removeAttribute('src');
        get('[data-wa-qr-placeholder]').textContent = data.state === 'connected'
            ? 'Dispositivo vinculado. No se requiere un nuevo QR.'
            : data.state === 'qr' || data.state === 'connecting' || data.state === 'reconnecting'
                ? 'Espera mientras se prepara un QR vigente o se recupera la sesión.'
                : 'Genera el QR cuando estés listo para vincular el número de notificaciones.';
        get('[data-wa-qr-expiry]').textContent = valid ? `QR válido hasta ${new Date(data.qr_expires_at).toLocaleTimeString('es-CL')}. Se renovará mientras la vinculación esté pendiente.` : '';
        get('[data-wa-send]').disabled = !enabled || data.state !== 'connected' || pending;
        get('[data-wa-generate]').disabled = !enabled || data.state === 'connected' || pending;
        const history = get('[data-wa-history]'); history.replaceChildren();
        for (const item of data.recent ?? []) {
            const row = document.createElement('tr');
            for (const [index, value] of [item.id, statuses[item.status] ?? 'Pendiente', new Date(item.created_at).toLocaleString('es-CL')].entries()) {
                const cell = document.createElement('td'); cell.textContent = value;
                if (index === 0) cell.className = 'wa-request-id'; row.appendChild(cell);
            }
            history.appendChild(row);
        }
        if (!history.children.length) { const row = history.insertRow(); const cell = row.insertCell(); cell.colSpan = 3; cell.textContent = 'Aún no hay envíos para mostrar.'; cell.className = 'text-secondary'; }
    }
    async function refresh() {
        if (refreshing || document.hidden) return;
        refreshing = true;
        try { render(await request(root.dataset.statusUrl)); }
        catch { render({ state: 'unavailable', recent: [] }); }
        finally { refreshing = false; }
    }
    async function action(button, url, confirmText) {
        if (pending || (confirmText && !window.confirm(confirmText))) return;
        pending = true; button.disabled = true;
        try { await request(url, 'POST', {}); show(confirmText ? 'Sesión desvinculada. Si estaba desconectada, elimina también el dispositivo del SGA desde Messenger.' : 'Vinculación iniciada. Espera el QR o la recuperación de la sesión existente.'); }
        catch (error) { show(error.message, true); }
        finally { pending = false; button.disabled = !enabled; await refresh(); }
    }
    get('[data-wa-refresh]').addEventListener('click', refresh);
    get('[data-wa-generate]').addEventListener('click', event => action(event.currentTarget, root.dataset.qrUrl));
    get('[data-wa-unlink]').addEventListener('click', event => action(event.currentTarget, root.dataset.unlinkUrl, '¿Desvincular el dispositivo de notificaciones del SGA? Los envíos pendientes esperarán una nueva vinculación.'));
    form.addEventListener('submit', async event => {
        event.preventDefault(); if (pending || lastState !== 'connected' || !form.reportValidity()) return;
        pending = true; get('[data-wa-send]').disabled = true;
        form.querySelectorAll('.is-invalid').forEach(field => field.classList.remove('is-invalid'));
        try {
            const result = await request(form.action, 'POST', { to: form.elements.to.value.trim(), text: form.elements.text.value, request_id: form.elements.request_id.value });
            show(`Solicitud ${result.id}: ${statuses[result.status] ?? 'En cola'}. Revisa su resultado antes de iniciar otra prueba.`);
            // Conservar la clave tras éxito/timeout para que doble clic, pérdida de respuesta o reenvío dedupliquen.
        } catch (error) { show(error.message, true); }
        finally { pending = false; await refresh(); }
    });
    const tick = () => {
        if (qrExpiresAt && qrExpiresAt <= Date.now()) clearQr();
        void refresh();
    };
    void refresh(); timer = window.setInterval(tick, 5000);
    document.addEventListener('visibilitychange', () => { if (document.hidden) clearQr(); else void refresh(); });
    window.addEventListener('pagehide', () => { window.clearInterval(timer); clearQr(); });
    window.addEventListener('pageshow', event => { if (event.persisted) { window.clearInterval(timer); timer = window.setInterval(tick, 5000); void refresh(); } });
}

if (typeof document !== 'undefined') document.querySelectorAll('[data-wa-admin]').forEach(initialise);
