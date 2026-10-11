import test from 'node:test';
import assert from 'node:assert/strict';
import { EventEmitter } from 'node:events';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import * as baileys from '@whiskeysockets/baileys';
import lockfile from 'proper-lockfile';
import { Store } from '../src/store.js';
import { Session } from '../src/session.js';
import { createApi } from '../src/server.js';
import { configuration } from '../src/config.js';

const payload = { id: '10000000-0000-4000-8000-000000000001', to: '+99900000000', text: 'Mensaje sintético de prueba' };
const png = 'data:image/png;base64,aGVsbG8=';

async function fixture(t, options = {}) {
    const directory = mkdtempSync(join(tmpdir(), 'sga-whatsapp-test-'));
    let store = new Store(directory);
    const sockets = []; const scheduled = []; const events = [];
    const session = new Session({ store, authFactory: () => store.auth(baileys), reasons: baileys.DisconnectReason,
        qrImage: async () => png, logger: event => events.push(event),
        schedule: (callback, delay) => { const timer = { callback, delay, cancelled: false }; scheduled.push(timer); return timer; },
        cancel: timer => { timer.cancelled = true; },
        createSocket: () => {
            const socket = { ev: new EventEmitter(), ended: false, sends: 0,
                end() { this.ended = true; }, logout: async () => {},
                onWhatsApp: options.lookup ?? (async () => [{ exists: true, jid: 'synthetic@s.whatsapp.net' }]),
                async sendMessage() { this.sends++; if (options.sendError) throw new Error('contenido secreto ficticio'); return { key: { id: 'synthetic-message' } }; },
            };
            sockets.push(socket); return socket;
        },
    });
    t.after(async () => { await session.close(); store.close(); rmSync(directory, { recursive: true, force: true }); });
    const open = async () => { await session.renewQr(); sockets.at(-1).ev.emit('connection.update', { connection: 'open' }); await session.tail; };
    return { directory, store, session, sockets, scheduled, events, open,
        reopen() { store.close(); store = new Store(directory); session.store = store; return store; } };
}

test('SQLite conserva credenciales de QR y claves criptográficas al reiniciar, sin mensajes en el ledger', async t => {
    const f = await fixture(t);
    const auth = f.store.auth(baileys);
    auth.save({ me: { id: 'synthetic@s.whatsapp.net' }, registered: false });
    await auth.state.keys.set({ 'pre-key': { test: { public: Buffer.from('synthetic-public'), private: Buffer.from('synthetic-private') } } });
    const restored = f.reopen().auth(baileys);
    assert.equal(restored.state.creds.me.id, 'synthetic@s.whatsapp.net');
    assert.equal(restored.state.creds.registered, false);
    assert.equal((await restored.state.keys.get('pre-key', ['test'])).test.private.toString(), 'synthetic-private');
    await f.session.start(); assert.equal(f.sockets.length, 1);
});

test('QR nuevo requiere acción explícita y no se devuelve tras expirar', async t => {
    const f = await fixture(t); await f.session.start(); assert.equal(f.sockets.length, 0);
    await f.session.renewQr();
    f.sockets[0].ev.emit('connection.update', { qr: 'QR SINTÉTICO, NO VINCULA CUENTAS' }); await f.session.tail;
    assert.equal(f.session.status().state, 'qr'); assert.equal(f.session.getQr().image, png);
    f.session.qr.expires_at = '2000-01-01T00:00:00Z';
    assert.throws(() => f.session.getQr(), error => error.code === 'qr_expired');
    assert.deepEqual(f.events, []);
});

test('renovaciones simultáneas no dejan sockets activos ni procesan eventos de un socket anterior', async t => {
    const f = await fixture(t); await Promise.all([f.session.renewQr(), f.session.renewQr()]);
    assert.equal(f.sockets.filter(socket => !socket.ended).length, 1);
    f.sockets[0].ev.emit('connection.update', { connection: 'open' }); await f.session.tail;
    assert.equal(f.session.state, 'connecting');
});

test('espera el cierre asíncrono real antes de abrir el siguiente socket', async t => {
    const f = await fixture(t); await f.session.renewQr();
    let release;
    f.sockets[0].end = () => new Promise(resolve => { release = () => { f.sockets[0].ended = true; resolve(); }; });
    const renewal = f.session.renewQr();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(f.sockets.length, 1);
    assert.equal(f.session.socket, null);
    release(); await renewal;
    assert.equal(f.sockets.length, 2);
    assert.equal(f.sockets.filter(socket => !socket.ended).length, 1);
});

test('reconexiones y conexiones inestables se detienen después de seis intentos', async t => {
    const f = await fixture(t); await f.open();
    for (let attempt = 1; attempt <= 7; attempt++) {
        const socket = f.sockets.at(-1);
        socket.ev.emit('connection.update', { connection: 'close', lastDisconnect: { error: { output: { statusCode: 408 } } } }); await f.session.tail;
        if (attempt <= 6) {
            assert.equal(f.session.state, 'reconnecting');
            const timer = f.scheduled.findLast(timer => !timer.cancelled);
            timer.callback(); await f.session.tail;
            f.sockets.at(-1).ev.emit('connection.update', { connection: 'open' }); await f.session.tail;
        }
    }
    assert.equal(f.session.state, 'paused'); assert.equal(f.sockets.filter(socket => !socket.ended).length, 0);
    assert.ok(f.events.includes('reconnect_limit'));
});

test('cierre de sesión y conflicto con otro dispositivo no generan bucles ni revinculación automática', async t => {
    const f = await fixture(t); await f.open();
    f.sockets.at(-1).ev.emit('connection.update', { connection: 'close', lastDisconnect: { error: { output: { statusCode: 440 } } } }); await f.session.tail;
    assert.equal(f.session.state, 'paused'); await f.session.start(); assert.equal(f.session.state, 'paused');
    await f.session.renewQr();
    f.sockets.at(-1).ev.emit('connection.update', { connection: 'close', lastDisconnect: { error: { output: { statusCode: 401 } } } }); await f.session.tail;
    assert.equal(f.session.state, 'logged_out'); assert.equal(f.store.db.prepare('SELECT COUNT(*) AS n FROM auth').get().n, 0);
});

test('un mensaje ya enviado se deduplica incluso tras reinicio y desconexión', async t => {
    const f = await fixture(t); await f.open();
    assert.equal((await f.session.send(payload)).status, 'sent');
    assert.equal((await f.session.send(payload)).status, 'sent'); assert.equal(f.sockets[0].sends, 1);
    const stored = f.reopen().find(payload.id); assert.equal(stored.status, 'sent');
    assert.ok(!JSON.stringify(stored).includes(payload.to)); assert.ok(!JSON.stringify(stored).includes(payload.text));
    f.session.state = 'idle'; assert.equal((await f.session.send(payload)).status, 'sent');
    await assert.rejects(f.session.send({ ...payload, text: 'Otro mensaje' }), error => error.code === 'idempotency_conflict');
});

test('resultado incierto o caída después de reservar impiden reenviar el mismo mensaje', async t => {
    const f = await fixture(t, { sendError: true }); await f.open();
    await assert.rejects(f.session.send(payload), error => error.code === 'delivery_unknown' && !error.message.includes('secreto'));
    await assert.rejects(f.session.send(payload), error => error.code === 'delivery_unknown'); assert.equal(f.sockets[0].sends, 1);
    const id = '10000000-0000-4000-8000-000000000002'; f.store.reserve(id, f.store.fingerprint(payload.to, payload.text));
    const reopened = f.reopen(); assert.equal(reopened.find(id).status, 'unknown');
    await assert.rejects(f.session.send({ ...payload, id }), error => error.code === 'delivery_unknown');
});

test('desconexión previa y destinatario inválido fallan sin reservar ni enviar', async t => {
    const f = await fixture(t, { lookup: async () => [] });
    await assert.rejects(f.session.send(payload), error => error.code === 'disconnected');
    await f.open(); await assert.rejects(f.session.send(payload), error => error.code === 'invalid_recipient');
    assert.equal(f.store.find(payload.id), undefined); assert.equal(f.sockets[0].sends, 0);
});

test('desvincular conserva las claves de deduplicación y permanece detenido al reiniciar', async t => {
    const f = await fixture(t); await f.open(); await f.session.send(payload); await f.session.unlink();
    assert.equal(f.session.state, 'unlinked'); assert.equal(f.store.find(payload.id).status, 'sent');
    await f.session.start(); assert.equal(f.session.state, 'unlinked'); assert.equal(f.sockets.filter(socket => !socket.ended).length, 0);
});

test('bloqueo de almacenamiento rechaza una segunda instancia', async t => {
    const f = await fixture(t); const release = await lockfile.lock(f.directory, { retries: 0 });
    try { await assert.rejects(lockfile.lock(f.directory, { retries: 0 }), error => error.code === 'ELOCKED'); }
    finally { await release(); }
});

test('API exige autenticación también para estado, QR, desvinculación y envío; no expone QR o errores sensibles', async t => {
    const f = await fixture(t); const token = 'synthetic_test_token_'.repeat(3);
    const api = createApi({ token, session: f.session });
    await new Promise(resolve => api.listen(0, '127.0.0.1', resolve)); t.after(() => new Promise(resolve => api.close(resolve)));
    const url = `http://127.0.0.1:${api.address().port}`;
    for (const [method, path] of [['GET', '/v1/status'], ['GET', '/v1/qr'], ['POST', '/v1/session/qr'], ['POST', '/v1/session/unlink'], ['POST', '/v1/messages']]) {
        const response = await fetch(url + path, { method }); assert.equal(response.status, 401);
        assert.deepEqual(await response.json(), { error: 'authentication' });
    }
    const headers = { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' };
    assert.equal((await fetch(url + '/v1/status', { headers: { ...headers, Origin: 'https://example.test' } })).status, 401);
    const status = await fetch(url + '/v1/status', { headers }); assert.equal(status.status, 200); assert.equal(status.headers.get('cache-control'), 'no-store');
    assert.equal((await fetch(url + '/v1/session/qr', { method: 'POST', headers, body: '{}' })).status, 202);
    f.sockets.at(-1).ev.emit('connection.update', { qr: 'QR SINTÉTICO' }); await f.session.tail;
    const qr = await fetch(url + '/v1/qr', { headers }); assert.equal((await qr.json()).image, png);
    assert.equal((await fetch(url + '/v1/messages', { method: 'POST', headers, body: JSON.stringify(payload) })).status, 503);
    const invalid = await fetch(url + '/v1/messages', { method: 'POST', headers, body: 'invalid json' }); assert.equal(invalid.status, 422);
    for (const body of ['null', '[]', '1', '"text"']) {
        assert.equal((await fetch(url + '/v1/messages', { method: 'POST', headers, body })).status, 422);
    }
});

test('configuración rechaza directorio público, raíz, token débil y puerto inválido', () => {
    const config = { WHATSAPP_SERVICE_TOKEN: 'synthetic_token_'.repeat(4), WHATSAPP_DATA_DIR: join(tmpdir(), 'sga-private') };
    assert.equal(configuration(config).host, '127.0.0.1');
    for (const override of [{ WHATSAPP_SERVICE_TOKEN: 'weak' }, { WHATSAPP_DATA_DIR: join(tmpdir(), 'public', 'session') }, { WHATSAPP_PORT: '80' }, { WHATSAPP_DATA_DIR: '/' }]) {
        assert.throws(() => configuration({ ...config, ...override }), /configuration/);
    }
});
