import '../css/account-verification.css';

export function normalizeMobile(value) {
    const clean = String(value).replace(/[^+0-9]/g, '');
    const phone = (clean.startsWith('+') ? '+' : '') + clean.replace(/\+/g, '');
    if (/^9[0-9]{8}$/.test(phone)) return '+56' + phone;
    if (/^569[0-9]{8}$/.test(phone)) return '+' + phone;
    return phone;
}

function initialize(root) {
    const phone = root.querySelector('#whatsapp-phone');
    const code = root.querySelector('#whatsapp-code');
    const send = root.querySelector('[data-whatsapp-send-button]');
    const confirm = root.querySelector('[data-whatsapp-confirm-button]');
    const resendTimer = root.querySelector('[data-resend-timer]');
    const expiryTimer = root.querySelector('[data-expiry-timer]');
    const announcement = root.querySelector('[data-whatsapp-announcement]');
    if (!phone && !code) { root.querySelector('[data-verification-errors]')?.focus(); return; }
    let available = root.dataset.available === '1';
    let serverNow = Number(root.dataset.now);
    let clockStart = performance.getEntriesByType('navigation')[0]?.responseStart || performance.now();
    let resendAt = Number(root.dataset.resendAt);
    let expiresAt = Number(root.dataset.expiresAt);
    let lastWait = null;
    let expired = false;
    const format = seconds => `${Math.floor(seconds / 60).toString().padStart(2, '0')}:${(seconds % 60).toString().padStart(2, '0')}`;
    const now = () => serverNow + (performance.now() - clockStart) / 1000;
    const update = () => {
        const wait = Math.max(0, Math.ceil(resendAt - now()));
        if (send) send.disabled = !available || wait > 0 || send.form.dataset.busy === '1';
        if (resendTimer) resendTimer.textContent = wait > 0 ? `Podrás solicitar otro código en ${format(wait)}.` : 'Puedes solicitar un código nuevo.';
        if (lastWait > 0 && wait === 0 && announcement) announcement.textContent = 'Ya puedes solicitar otro código.';
        lastWait = wait;
        if (code && expiryTimer) {
            const left = Math.max(0, Math.ceil(expiresAt - now()));
            expiryTimer.textContent = left > 0 ? `El código vence en ${format(left)}.` : 'Este código ya no es válido. Solicita uno nuevo.';
            if (confirm) confirm.disabled = !available || left === 0 || confirm.form.dataset.busy === '1';
            if (left === 0 && !expired) {
                expired = true;
                if (announcement) announcement.textContent = 'El código ya no es válido. Puedes solicitar uno nuevo.';
            }
        }
    };
    phone?.addEventListener('input', () => { phone.value = normalizeMobile(phone.value); });
    phone?.addEventListener('blur', () => { phone.value = normalizeMobile(phone.value); });
    code?.addEventListener('input', () => { code.value = code.value.replace(/[^0-9]/g, '').slice(0, 6); });
    root.querySelectorAll('[data-whatsapp-send], [data-whatsapp-confirm]').forEach(form => {
        form.addEventListener('submit', event => {
            const button = form.querySelector('button[type="submit"]');
            if (button.disabled || form.dataset.busy === '1') { event.preventDefault(); return; }
            if (phone) phone.value = normalizeMobile(phone.value);
            form.dataset.busy = '1'; form.setAttribute('aria-busy', 'true');
            button.disabled = true;
            button.textContent = form.hasAttribute('data-whatsapp-send') ? 'Solicitando código…' : 'Validando…';
        });
    });
    root.querySelector('[data-verification-errors]')?.focus();
    root.querySelector('[data-focus-code]')?.focus();
    let ticker;
    let polling = Boolean(root.dataset.challenge);
    let nextPoll;
    const poll = async () => {
        if (!polling || now() >= expiresAt) return;
        if (document.hidden) { nextPoll = window.setTimeout(poll, 5000); return; }
        try {
            const response = await fetch(root.dataset.statusUrl, {headers: {Accept: 'application/json'}, credentials: 'same-origin', cache: 'no-store'});
            if (response.status === 401 || response.status === 403) { polling = false; return; }
            if (!response.ok) { nextPoll = window.setTimeout(poll, 10000); return; }
            const state = await response.json();
            if (state.verified) { window.location.assign(root.dataset.dashboardUrl); return; }
            if (state.challenge_id && state.challenge_id !== root.dataset.challenge) { window.location.reload(); return; }
            serverNow = state.server_now; clockStart = performance.now();
            resendAt = Number(state.resend_at); expiresAt = Number(state.expires_at); available = state.available;
            const delivery = root.querySelector('[data-whatsapp-delivery]');
            if (delivery) {
                delivery.textContent = state.delivery_error || '';
                delivery.classList.toggle('d-none', !state.delivery_error);
            }
            update();
            if (!['queued', 'sending', 'retrying'].includes(state.delivery)) polling = false;
        } catch { /* El formulario permanece disponible y el servidor valida los plazos. */ }
        if (polling) nextPoll = window.setTimeout(poll, 5000);
    };
    const startTimers = () => {
        clearInterval(ticker); clearTimeout(nextPoll);
        update(); ticker = window.setInterval(update, 1000);
        if (polling) nextPoll = window.setTimeout(poll, 5000);
    };
    window.addEventListener('pageshow', () => {
        root.querySelectorAll('form').forEach(form => { form.dataset.busy = '0'; form.removeAttribute('aria-busy'); });
        if (send) send.textContent = 'Enviar código a WhatsApp';
        if (confirm) confirm.textContent = 'Validar cuenta';
        startTimers();
    });
    startTimers();
    window.addEventListener('pagehide', () => { clearInterval(ticker); clearTimeout(nextPoll); });
}

if (typeof document !== 'undefined') {
    const start = () => document.querySelectorAll('[data-account-verification]').forEach(initialize);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
}
