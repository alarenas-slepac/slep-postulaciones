import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, rmSync, writeFileSync, statSync, existsSync, utimesSync } from 'node:fs';
import { DatabaseSync } from 'node:sqlite';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { createServer } from 'node:http';
import { runService, command, recordBridgeOutput, recordEvent } from '../deploy/cron-service.mjs';

function fixture(t) {
    const dataDir = mkdtempSync(join(tmpdir(), 'sga-cron-service-'));
    const config = { dataDir, token: 'synthetic_token_'.repeat(4), port: 3210 };
    const runners = [];
    t.after(async () => {
        for (const runner of runners) await runner?.close();
        rmSync(dataDir, { recursive: true, force: true });
    });
    return { config, directory: join(dataDir, 'cron-control'), runners };
}

async function waitFor(callback) {
    for (let attempt = 0; attempt < 100; attempt++) {
        if (await callback()) return;
        await delay(30);
    }
    throw new Error('condition_timeout');
}

test('excluye otra ejecución de cron sin importar el puente ni incrementar intentos', async t => {
    const f = fixture(t);
    let boots = 0;
    f.runners.push(await runService(f.config, { boot: async () => { boots++; } }));
    assert.equal(await runService(f.config, { boot: async () => { boots++; } }), null);
    const status = await command(f.config, 'status');
    assert.equal(status.runner_running, true);
    assert.equal(status.recent_starts, 1);
    assert.equal(boots, 1);
    await assert.rejects(command(f.config, 'start'), /already_running/);
});

test('stop persiste la detención y start habilita el siguiente cron sin borrar la sesión', async t => {
    const f = fixture(t);
    const sentinel = join(f.config.dataDir, 'session-sentinel');
    writeFileSync(sentinel, 'SESSION_SYNTHETIC');
    let stopped = false;
    f.runners.push(await runService(f.config, { boot: async () => {}, stop: async () => { stopped = true; } }));
    assert.equal((await command(f.config, 'stop')).enabled, false);
    await waitFor(() => stopped);
    await waitFor(async () => !(await command(f.config, 'status')).runner_running);
    assert.equal(await runService(f.config, { boot: async () => assert.fail('disabled') }), null);
    assert.equal((await command(f.config, 'start')).enabled, true);
    assert.equal(readFileSync(sentinel, 'utf8'), 'SESSION_SYNTHETIC');
    assert.equal((await command(f.config, 'status')).recent_starts, 0);
});

test('restart cierra el propietario y la siguiente ejecución conserva sesión e intentos', async t => {
    const f = fixture(t);
    let stopped = false;
    f.runners.push(await runService(f.config, { boot: async () => {}, stop: async () => { stopped = true; } }));
    await command(f.config, 'restart');
    await waitFor(() => stopped);
    await waitFor(async () => !(await command(f.config, 'status')).runner_running);
    f.runners.push(await runService(f.config, { boot: async () => {} }));
    const status = await command(f.config, 'status');
    assert.equal(status.runner_running, true);
    assert.equal(status.recent_starts, 2);
    assert.equal(status.enabled, true);
});

test('cinco fallos en diez minutos bloquean nuevos arranques hasta una reapertura explícita', async t => {
    const f = fixture(t);
    for (let count = 0; count < 5; count++) {
        await assert.rejects(runService(f.config, { boot: async () => { throw new Error('SECRET_SYNTHETIC'); } }), /runner/);
    }
    assert.equal(await runService(f.config, { boot: async () => assert.fail('limit') }), null);
    assert.equal((await command(f.config, 'status')).blocked, true);
    assert.equal(await runService(f.config, { boot: async () => assert.fail('still blocked') }), null);
    await command(f.config, 'start');
    assert.equal((await command(f.config, 'status')).blocked, false);
    f.runners.push(await runService(f.config, { boot: async () => {} }));
    assert.ok(!readFileSync(join(f.directory, 'events.log'), 'utf8').includes('SECRET_SYNTHETIC'));
});

test('un arranque estable no conserva intentos anteriores a la ventana de diez minutos', async t => {
    const f = fixture(t);
    await command(f.config, 'status');
    writeFileSync(join(f.directory, 'attempts.json'), JSON.stringify(Array(5).fill(Date.now() - 600001)));
    f.runners.push(await runService(f.config, { boot: async () => {} }));
    assert.equal((await command(f.config, 'status')).recent_starts, 1);
});

test('logs descartan salida sensible y rotan a dos archivos acotados', async t => {
    const f = fixture(t);
    await command(f.config, 'status');
    recordBridgeOutput(f.directory, 'QR_SECRET_SYNTHETIC');
    recordBridgeOutput(f.directory, JSON.stringify({ event: 'unknown', token: 'TOKEN_SECRET_SYNTHETIC' }));
    recordBridgeOutput(f.directory, JSON.stringify({ event: 'connected', qr: 'QR_SECRET_SYNTHETIC', text: 'MESSAGE_SECRET_SYNTHETIC' }));
    for (let n = 0; n < 1500; n++) recordEvent(f.directory, 'runner_started');
    for (const name of ['events.log', 'events.previous.log']) {
        assert.ok(existsSync(join(f.directory, name)));
        const value = readFileSync(join(f.directory, name), 'utf8');
        assert.ok(!value.includes('SECRET_SYNTHETIC'));
        assert.ok(statSync(join(f.directory, name)).size <= 65700);
        if (process.platform !== 'win32') assert.equal(statSync(join(f.directory, name)).mode & 0o777, 0o600);
    }
    if (process.platform !== 'win32') assert.equal(statSync(f.directory).mode & 0o777, 0o700);
});

test('CLI mantiene el puente real idle con datos nuevos, controla sólo su proceso y no pierde SQLite al reiniciar', async t => {
    const f = fixture(t);
    const probe = createServer();
    await new Promise(resolve => probe.listen(0, '127.0.0.1', resolve));
    const port = probe.address().port;
    await new Promise(resolve => probe.close(resolve));
    const token = f.config.token;
    const script = fileURLToPath(new URL('../deploy/cron-service.mjs', import.meta.url));
    const env = { ...process.env, WHATSAPP_DATA_DIR: f.config.dataDir, WHATSAPP_PORT: String(port), WHATSAPP_SERVICE_TOKEN: token };
    const children = [];
    t.after(async () => {
        for (const child of children) {
            if (child.process.exitCode === null && child.process.signalCode === null) {
                await command(f.config, 'stop');
                await child.ended;
            }
        }
    });
    const launch = action => {
        const process = spawn(globalThis.process.execPath, [script, action], { env, stdio: ['ignore', 'pipe', 'pipe'] });
        let output = '';
        process.stdout.on('data', data => { output += data; });
        process.stderr.on('data', data => { output += data; });
        const ended = new Promise((resolve, reject) => { process.once('error', reject); process.once('close', resolve); });
        const child = { process, ended, output: () => output };
        children.push(child); return child;
    };
    const connected = async () => {
        try {
            const r = await fetch(`http://127.0.0.1:${port}/v1/status`, { headers: { Authorization: `Bearer ${token}` }, signal: AbortSignal.timeout(1000) });
            return r.status === 200 && (await r.json()).state === 'idle';
        } catch { return false; }
    };
    const first = launch('run');
    // Importar las dependencias nativas puede tardar varios segundos en Windows.
    for (let n = 0; n < 100 && !(await connected()); n++) await delay(200);
    assert.equal(await connected(), true);
    const duplicate = launch('run');
    assert.equal(await duplicate.ended, 0);
    assert.equal((await command(f.config, 'status')).recent_starts, 1);
    assert.equal(await launch('restart').ended, 0);
    assert.equal(await first.ended, 0);
    assert.ok(existsSync(join(f.config.dataDir, 'state.sqlite')));
    const snapshotDb = new DatabaseSync(join(f.config.dataDir, 'state.sqlite'));
    const credentials = snapshotDb.prepare('SELECT id, value FROM auth ORDER BY id').all();
    const fingerprintSecret = snapshotDb.prepare("SELECT value FROM meta WHERE id = 'fingerprint_secret'").get().value;
    const syntheticId = '10000000-0000-4000-8000-000000000007';
    snapshotDb.prepare('INSERT INTO sends (id, fingerprint, status, provider_id, created_at) VALUES (?, ?, ?, ?, ?)')
        .run(syntheticId, 'synthetic-fingerprint', 'sent', 'synthetic-message', new Date().toISOString());
    snapshotDb.close();
    const second = launch('run');
    for (let n = 0; n < 100 && !(await connected()); n++) await delay(200);
    assert.equal(await connected(), true);
    assert.equal((await command(f.config, 'status')).recent_starts, 2);
    // Caída abrupta del único proceso: los bloqueos quedan, sin un hijo que mantenga la API.
    assert.equal(second.process.kill('SIGKILL'), true);
    await second.ended;
    // Adelantar sólo el mtime de los bloqueos de ESTA prueba, una vez confirmado el cierre.
    const stale = new Date(Date.now() - 31000);
    for (const path of [f.config.dataDir + '.lock', f.directory + '.lock']) {
        if (existsSync(path)) utimesSync(path, stale, stale);
    }
    const third = launch('run');
    for (let n = 0; n < 100 && !(await connected()); n++) await delay(200);
    assert.equal(await connected(), true);
    assert.equal((await command(f.config, 'status')).recent_starts, 3);
    assert.equal(await launch('stop').ended, 0);
    assert.equal(await third.ended, 0);
    assert.equal(await launch('run').ended, 0);
    assert.equal((await command(f.config, 'status')).enabled, false);
    for (const child of children) assert.ok(!child.output().includes(token));
    const logs = readFileSync(join(f.directory, 'events.log'), 'utf8');
    assert.ok(logs.includes('service_started'));
    assert.ok(logs.includes('runner_restart'));
    const restored = new DatabaseSync(join(f.config.dataDir, 'state.sqlite'));
    try {
        assert.deepEqual(restored.prepare('SELECT id, value FROM auth ORDER BY id').all(), credentials);
        assert.equal(restored.prepare("SELECT value FROM meta WHERE id = 'fingerprint_secret'").get().value, fingerprintSecret);
        assert.equal(restored.prepare('SELECT status FROM sends WHERE id = ?').get(syntheticId).status, 'sent');
    } finally { restored.close(); }
});
