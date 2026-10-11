import { createHash } from 'node:crypto';
import { validateDocument, readDocument, DocumentError } from './documents.js';

export class BridgeError extends Error {
    constructor(code, status = 503) { super(code); this.code = code; this.status = status; }
}

export class Session {
    constructor({ store, createSocket, authFactory, qrImage, reasons, logger, documentRoot = null, schedule = setTimeout, cancel = clearTimeout }) {
        Object.assign(this, { store, createSocket, authFactory, qrImage, reasons, logger, documentRoot, schedule, cancel });
        this.state = 'idle'; this.socket = null; this.generation = 0; this.timer = null; this.attempts = 0;
        this.qr = null; this.busy = false; this.stopped = false; this.tail = Promise.resolve(); this.stableTimer = null;
    }

    exclusive(operation) {
        const result = this.tail.then(operation);
        this.tail = result.catch(() => {});
        return result;
    }

    async start() {
        if (this.store.meta('paused') === '1') { this.state = this.store.meta('paused_state') ?? 'unlinked'; return; }
        const auth = this.authFactory();
        // El login por QR persiste creds.me; registered no se activa necesariamente con QR.
        if (auth.state.creds.me?.id) await this.exclusive(() => this.connect());
    }

    async dispose() {
        this.generation++;
        if (this.timer) this.cancel(this.timer);
        if (this.stableTimer) this.cancel(this.stableTimer);
        this.timer = null; this.stableTimer = null; this.qr = null;
        if (this.socket) {
            const socket = this.socket;
            this.socket = null;
            socket.ev.removeAllListeners('connection.update');
            socket.ev.removeAllListeners('creds.update');
            // Baileys espera el cierre del WebSocket en end(); no abrir otro antes.
            await socket.end(new Error('session_closed'));
        }
    }

    async connect() {
        if (this.stopped) return;
        await this.dispose();
        this.state = 'connecting';
        const generation = this.generation;
        const auth = this.authFactory();
        this.socket = this.createSocket(auth.state);
        this.socket.ev.on('creds.update', update => {
            if (generation !== this.generation || this.stopped) return;
            try { auth.save(update); }
            catch { void this.exclusive(() => this.halt('storage_failed')); }
        });
        this.socket.ev.on('connection.update', update => {
            this.exclusive(async () => {
                if (generation !== this.generation || this.stopped) return;
                if (update.qr) {
                    const image = await this.qrImage(update.qr);
                    if (generation !== this.generation) return;
                    this.qr = { image, expires_at: new Date(Date.now() + 45000).toISOString() };
                    this.state = 'qr';
                }
                if (update.connection === 'open') {
                    this.qr = null; this.state = 'connected';
                    // Un enlace que abre y cae inmediatamente no reinicia el contador.
                    this.stableTimer = this.schedule(() => {
                        if (generation === this.generation && this.state === 'connected') this.attempts = 0;
                    }, 60000);
                    this.store.setMeta('paused', '0'); this.logger('connected');
                }
                if (update.connection === 'close') {
                    const reason = update.lastDisconnect?.error?.output?.statusCode;
                    await this.dispose();
                    const fatal = [this.reasons.loggedOut, this.reasons.connectionReplaced,
                        this.reasons.badSession, this.reasons.multideviceMismatch, this.reasons.forbidden].filter(Number.isFinite);
                    if (fatal.includes(reason)) {
                        if (reason === this.reasons.loggedOut) this.store.clearAuth();
                        this.store.setMeta('paused', '1');
                        this.state = reason === this.reasons.loggedOut ? 'logged_out' : 'paused';
                        this.store.setMeta('paused_state', this.state);
                        this.logger('session_paused'); return;
                    }
                    if (++this.attempts > 6) {
                        this.state = 'paused'; this.store.setMeta('paused', '1'); this.store.setMeta('paused_state', 'paused'); this.logger('reconnect_limit'); return;
                    }
                    this.state = 'reconnecting';
                    const delay = Math.min(60000, 2000 * (2 ** (this.attempts - 1))) + Math.floor(Math.random() * 1000);
                    this.timer = this.schedule(() => {
                        this.timer = null;
                        this.exclusive(() => this.connect()).catch(() => this.exclusive(() => this.halt('connection_failed')));
                    }, delay);
                }
            }).catch(() => this.exclusive(() => this.halt('connection_failed')));
        });
    }

    async halt(code) {
        await this.dispose(); this.state = 'paused';
        this.store.setMeta('paused', '1'); this.store.setMeta('paused_state', 'paused'); this.logger(code);
    }

    async renewQr() {
        return this.exclusive(async () => {
            if (this.busy) throw new BridgeError('busy');
            if (this.state === 'connected') throw new BridgeError('connected', 409);
            this.attempts = 0; this.store.setMeta('paused', '0');
            await this.connect();
            return this.status();
        });
    }

    async unlink() {
        return this.exclusive(async () => {
            if (this.busy) throw new BridgeError('busy');
            // Sólo el dispositivo vinculado: no elimina la cuenta ni modifica otras apps del iPhone.
            this.store.setMeta('paused', '1');
            this.store.setMeta('paused_state', 'unlinked');
            if (this.socket && this.state === 'connected') {
                try { await this.socket.logout(); }
                catch { await this.dispose(); this.state = 'paused'; this.store.setMeta('paused_state', 'paused'); throw new BridgeError('unlink_failed'); }
            }
            await this.dispose(); this.store.clearAuth(); this.state = 'unlinked';
            return this.status();
        });
    }

    status() { return { state: this.state, reconnect_attempts: this.attempts }; }
    getQr() {
        if (!this.qr || Date.parse(this.qr.expires_at) <= Date.now()) throw new BridgeError('qr_expired', 404);
        return this.qr;
    }

    async send({ id, to, text, document }) {
        if (!/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(id ?? '')
            || typeof to !== 'string' || !/^\+[1-9][0-9]{7,14}$/.test(to)
            || typeof text !== 'string' || !text.trim() || [...text].length > 1000) {
            throw new BridgeError('invalid_message', 422);
        }
        let media;
        if (document !== undefined) {
            try { media = validateDocument(document); }
            catch (error) { throw new BridgeError(error instanceof DocumentError ? error.code : 'invalid_document', 422); }
        }
        // Conservar las huellas históricas de texto; incluir PDF y ubicación en las nuevas.
        const fingerprint = this.store.fingerprint(to, media ? JSON.stringify({ text, document: media }) : text);
        const prior = this.store.find(id);
        if (prior) {
            if (prior.fingerprint !== fingerprint) throw new BridgeError('idempotency_conflict', 409);
            if (prior.status !== 'sent') throw new BridgeError('delivery_unknown', 409);
            return { id, status: 'sent' };
        }
        if (this.state !== 'connected' || !this.socket || this.stopped) throw new BridgeError('disconnected');
        if (this.busy) throw new BridgeError('busy');
        this.busy = true;
        try {
            const socket = this.socket;
            const generation = this.generation;
            let recipients;
            try { recipients = await socket.onWhatsApp(to.slice(1)); }
            catch { throw new BridgeError('disconnected'); }
            if (generation !== this.generation || this.state !== 'connected') throw new BridgeError('disconnected');
            const recipient = recipients?.find(item => item.exists);
            if (!recipient) throw new BridgeError('invalid_recipient', 422);
            let content = { text };
            if (media) {
                try { content = { ...readDocument(media, this.documentRoot), caption: text }; }
                catch (error) { throw new BridgeError(error instanceof DocumentError ? error.code : 'document_unavailable', 422); }
            }
            this.store.reserve(id, fingerprint);
            try {
                if (media?.location) {
                    const l = media.location;
                    const locationId = createHash('sha256').update(id + '|location').digest('hex').slice(0, 32).toUpperCase();
                    const locationResult = await socket.sendMessage(recipient.jid, { location: {
                        degreesLatitude: l.latitude, degreesLongitude: l.longitude, name: l.name, address: l.address,
                    } }, { messageId: locationId });
                    if (!locationResult?.key?.id) throw new Error('no_ack');
                }
                const result = await socket.sendMessage(recipient.jid, content, { messageId: id.replaceAll('-', '').toUpperCase(),
                    ...(media ? { mediaUploadTimeoutMs: 40000 } : {}) });
                if (!result?.key?.id) throw new Error('no_ack');
                this.store.finish(id, 'sent', result.key.id);
                return { id, status: 'sent' };
            } catch {
                this.store.finish(id, 'unknown');
                throw new BridgeError('delivery_unknown', 409);
            }
        } finally { this.busy = false; }
    }

    async close() {
        this.stopped = true;
        await this.exclusive(async () => this.dispose());
    }
}
