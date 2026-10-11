import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, rmSync, writeFileSync, statSync } from 'node:fs';
import { createServer } from 'node:http';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { initialize, run, inspect, request } from '../deploy/cron-probe.mjs';

async function fixture(t, duration = 5000) {
    const directory = mkdtempSync(join(tmpdir(), 'sga-cron-test-'));
    const server = createServer();
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const port = server.address().port;
    await new Promise(resolve => server.close(resolve));
    initialize(directory, { port, duration });
    t.after(() => rmSync(directory, { recursive: true, force: true }));
    return { directory, port, state: () => JSON.parse(readFileSync(join(directory, 'probe.json'), 'utf8')) };
}

async function started(directory) {
    for (let attempt = 0; attempt < 50; attempt++) {
        const status = await inspect(directory);
        if (status.state === 'running') return status;
        await delay(20);
    }
    throw new Error('probe_did_not_start');
}

test('preparación no sobrescribe el token ni prolonga la prueba, y rechaza carpetas públicas', async t => {
    const f = await fixture(t);
    const original = readFileSync(join(f.directory, 'probe.json'), 'utf8');
    assert.throws(() => initialize(f.directory), error => error.code === 'EEXIST');
    assert.equal(readFileSync(join(f.directory, 'probe.json'), 'utf8'), original);
    assert.throws(() => initialize(join(f.directory, 'public_html', 'test')), /directory/);
    assert.throws(() => initialize(join(f.directory, 'extra'), { duration: 900001 }), /configuration/);
    if (process.platform !== 'win32') {
        assert.equal(statSync(f.directory).mode & 0o777, 0o700);
        assert.equal(statSync(join(f.directory, 'probe.json')).mode & 0o777, 0o600);
        assert.equal(statSync(join(f.directory, 'events.log')).mode & 0o777, 0o600);
    }
});

test('estado exige token, no hay rutas de QR o envío y una segunda ejecución no duplica el proceso', async t => {
    const f = await fixture(t);
    const running = run(f.directory);
    try {
        const status = await started(f.directory);
        assert.equal(status.without_token, 401);
        assert.equal(status.with_token, 200);
        assert.ok(!JSON.stringify(status).includes(f.state().token));
        assert.equal(await run(f.directory), 0);
        assert.equal(f.state().starts, 1);
        for (const route of ['/v1/session/qr', '/v1/messages']) {
            const response = await fetch(`http://127.0.0.1:${f.port}${route}`, { method: 'POST',
                headers: { Authorization: `Bearer ${f.state().token}` } });
            assert.equal(response.status, 404);
        }
    } finally { request(f.directory, 'stop'); await running; }
    assert.equal((await inspect(f.directory)).state, 'finished');
    assert.equal(await run(f.directory), 0);
    assert.equal(f.state().starts, 1);
});

test('caída con código 1 permite que la siguiente ejecución recupere una única instancia', async t => {
    const f = await fixture(t);
    const first = run(f.directory);
    await started(f.directory);
    request(f.directory, 'crash');
    assert.equal(await first, 1);
    const second = run(f.directory);
    try { assert.equal((await started(f.directory)).starts, 2); }
    finally { request(f.directory, 'stop'); assert.equal(await second, 0); }
});

test('caducidad detiene el proceso y las siguientes ejecuciones no renuevan el plazo', async t => {
    const f = await fixture(t, 400);
    const expiresAt = f.state().expiresAt;
    const running = run(f.directory);
    await started(f.directory);
    assert.equal(await running, 0);
    assert.equal((await inspect(f.directory)).state, 'finished');
    assert.equal(await run(f.directory), 0);
    assert.equal(f.state().expiresAt, expiresAt);
    assert.equal(f.state().starts, 1);
});

test('cinco arranques detienen el ciclo aunque todavía quede plazo', async t => {
    const f = await fixture(t);
    const state = f.state();
    state.starts = 5;
    writeFileSync(join(f.directory, 'probe.json'), JSON.stringify(state));
    assert.equal(await run(f.directory), 0);
    assert.equal((await inspect(f.directory)).state, 'finished');
    assert.equal(f.state().starts, 5);
});

test('proceso CLI independiente inicia, informa, falla, recupera y se detiene sin divulgar el token', async t => {
    const f = await fixture(t, 10000);
    const script = fileURLToPath(new URL('../deploy/cron-probe.mjs', import.meta.url));
    const launch = () => {
        const child = spawn(process.execPath, [script, 'run', '--directory', f.directory], { stdio: ['ignore', 'pipe', 'pipe'] });
        let output = '';
        child.stdout.on('data', data => { output += data; });
        child.stderr.on('data', data => { output += data; });
        const ended = new Promise((resolve, reject) => { child.on('error', reject); child.on('close', code => resolve(code)); });
        return { child, ended, output: () => output };
    };
    const first = launch();
    t.after(() => { if (first.child.exitCode === null) first.child.kill(); });
    await started(f.directory);
    const duplicate = launch();
    assert.equal(await duplicate.ended, 0);
    assert.equal(f.state().starts, 1);
    request(f.directory, 'crash');
    assert.equal(await first.ended, 1);
    const second = launch();
    t.after(() => { if (second.child.exitCode === null) second.child.kill(); });
    await started(f.directory);
    assert.equal(f.state().starts, 2);
    request(f.directory, 'stop');
    assert.equal(await second.ended, 0);
    for (const process of [first, duplicate, second]) assert.ok(!process.output().includes(f.state().token));
    assert.ok(first.output().includes('probe_simulated_failure'));
});
