// Prueba temporal de cron: no importa Baileys ni accede a Laravel o a su sesión.
import { mkdirSync, readFileSync, writeFileSync, renameSync, existsSync, unlinkSync, lstatSync } from 'node:fs';
import { randomBytes, timingSafeEqual } from 'node:crypto';
import { createServer } from 'node:http';
import { homedir } from 'node:os';
import { join, resolve, isAbsolute, parse } from 'node:path';
import { fileURLToPath } from 'node:url';
import lockfile from 'proper-lockfile';

const lifetime = 15 * 60 * 1000;
const maximumStarts = 5;
export const defaultDirectory = join(homedir(), '.local/share/sga-whatsapp-cron-test');
const event = (name, starts) => process.stdout.write(`${JSON.stringify({ event: name, starts, at: new Date().toISOString() })}\n`);

function privateDirectory(directory) {
    if (!isAbsolute(directory) || resolve(directory) === parse(resolve(directory)).root
        || /(?:^|[\\/])(?:public|public_html)(?:[\\/]|$)/i.test(directory)) throw new Error('directory');
    if (existsSync(directory) && lstatSync(directory).isSymbolicLink()) throw new Error('directory');
    return resolve(directory);
}

function load(directory) {
    const state = JSON.parse(readFileSync(join(directory, 'probe.json'), 'utf8'));
    if (state.version !== 1 || !/^[a-f0-9]{64}$/.test(state.token)
        || !Number.isSafeInteger(state.createdAt) || !Number.isSafeInteger(state.expiresAt)
        || state.expiresAt <= state.createdAt || state.expiresAt > state.createdAt + lifetime
        || !Number.isInteger(state.port) || state.port < 1024 || state.port > 65535
        || !Number.isInteger(state.starts) || state.starts < 0 || state.starts > maximumStarts) throw new Error('state');
    return state;
}

function save(directory, state) {
    const temporary = join(directory, `probe-${randomBytes(8).toString('hex')}.tmp`);
    writeFileSync(temporary, JSON.stringify(state), { mode: 0o600, flag: 'wx' });
    renameSync(temporary, join(directory, 'probe.json'));
}

export function initialize(directory, { port = 3211, duration = lifetime } = {}) {
    directory = privateDirectory(directory);
    if (!Number.isInteger(duration) || duration < 1 || duration > lifetime
        || !Number.isInteger(port) || port < 1024 || port > 65535) throw new Error('configuration');
    process.umask(0o077);
    mkdirSync(directory, { recursive: true, mode: 0o700 });
    const createdAt = Date.now();
    writeFileSync(join(directory, 'probe.json'), JSON.stringify({ version: 1, token: randomBytes(32).toString('hex'),
        port, createdAt, expiresAt: createdAt + duration, starts: 0 }), { mode: 0o600, flag: 'wx' });
    writeFileSync(join(directory, 'events.log'), '', { mode: 0o600, flag: 'wx' });
}

export function request(directory, action) {
    directory = privateDirectory(directory);
    load(directory);
    if (!['stop', 'crash'].includes(action)) throw new Error('action');
    const file = join(directory, action);
    if (!existsSync(file)) writeFileSync(file, '', { mode: 0o600, flag: 'wx' });
}

export async function inspect(directory) {
    directory = privateDirectory(directory);
    const state = load(directory);
    const result = { state: 'waiting_for_cron', starts: state.starts,
        expires_at: new Date(state.expiresAt).toISOString() };
    try {
        const url = `http://127.0.0.1:${state.port}/health`;
        const unauthenticated = await fetch(url, { signal: AbortSignal.timeout(3000), redirect: 'error' });
        const authenticated = await fetch(url, { headers: { Authorization: `Bearer ${state.token}` },
            signal: AbortSignal.timeout(3000), redirect: 'error' });
        const body = await authenticated.json();
        if (unauthenticated.status !== 401 || authenticated.status !== 200 || body.state !== 'probe_running'
            || body.starts !== state.starts) throw new Error('authentication');
        result.state = 'running';
        result.without_token = 401;
        result.with_token = 200;
    } catch {
        if (existsSync(join(directory, 'stop')) || Date.now() >= state.expiresAt || state.starts >= maximumStarts) result.state = 'finished';
    }
    return result;
}

export async function run(directory) {
    directory = privateDirectory(directory);
    process.umask(0o077);
    load(directory);
    let api, timer, deadlineTimer, release, ending = false, finish;
    const ended = new Promise(resolve => { finish = resolve; });
    const shutdown = async (code, reason) => {
        if (ending) return;
        ending = true;
        clearInterval(timer);
        clearTimeout(deadlineTimer);
        process.removeListener('SIGINT', interrupt);
        process.removeListener('SIGTERM', interrupt);
        if (api?.listening) {
            const closed = new Promise(resolve => api.close(resolve));
            api.closeAllConnections();
            await closed;
        }
        await release?.().catch(() => {});
        event(reason);
        finish(code);
    };
    const interrupt = () => void shutdown(0, 'probe_interrupted');
    try {
        release = await lockfile.lock(directory, { retries: 0, stale: 30000, update: 10000,
            onCompromised: () => void shutdown(1, 'probe_lock_lost') });
    } catch (error) {
        if (error.code === 'ELOCKED') return 0;
        throw error;
    }
    try {
        const state = load(directory);
        if (Date.now() >= state.expiresAt || existsSync(join(directory, 'stop')) || state.starts >= maximumStarts) {
            await release();
            return 0;
        }
        // Una petición anterior a un proceso ya caído no debe provocar otra caída al recuperar.
        if (existsSync(join(directory, 'crash'))) unlinkSync(join(directory, 'crash'));
        state.starts++;
        save(directory, state);
        const expected = Buffer.from(`Bearer ${state.token}`);
        api = createServer((req, res) => {
            res.setHeader('Cache-Control', 'no-store');
            res.setHeader('Content-Type', 'application/json');
            const provided = Buffer.from(req.headers.authorization ?? '');
            if (provided.length !== expected.length || !timingSafeEqual(provided, expected)) {
                res.writeHead(401).end('{"error":"authentication"}');
            } else if (req.method !== 'GET' || req.url !== '/health') {
                res.writeHead(404).end('{"error":"route"}');
            } else {
                res.end(JSON.stringify({ state: 'probe_running', starts: state.starts }));
            }
        });
        api.headersTimeout = 5000;
        api.requestTimeout = 5000;
        await new Promise((resolve, reject) => { api.once('error', reject); api.listen(state.port, '127.0.0.1', resolve); });
        process.on('SIGINT', interrupt);
        process.on('SIGTERM', interrupt);
        timer = setInterval(() => {
            if (existsSync(join(directory, 'stop'))) void shutdown(0, 'probe_stopped');
            else if (existsSync(join(directory, 'crash'))) {
                unlinkSync(join(directory, 'crash'));
                void shutdown(1, 'probe_simulated_failure');
            }
        }, 250);
        deadlineTimer = setTimeout(() => void shutdown(0, 'probe_expired'), Math.max(1, state.expiresAt - Date.now()));
        event('probe_started', state.starts);
    } catch {
        await shutdown(1, 'probe_start_failed');
    }
    return ended;
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    try {
        const [action, flag, suppliedDirectory, ...extra] = process.argv.slice(2);
        if (extra.length || (flag !== undefined && (flag !== '--directory' || !suppliedDirectory))) throw new Error('arguments');
        const directory = suppliedDirectory ?? defaultDirectory;
        if (action === 'init') { initialize(directory); event('probe_initialized'); }
        else if (action === 'run') process.exitCode = await run(directory);
        else if (action === 'status') process.stdout.write(`${JSON.stringify(await inspect(directory))}\n`);
        else if (['stop', 'crash'].includes(action)) { request(directory, action); event(`probe_${action}_requested`); }
        else throw new Error('action');
    } catch {
        process.stderr.write('Prueba no ejecutada: revisar comando, dependencias, carpeta privada y configuracion; init no sobrescribe una prueba anterior.\n');
        process.exitCode = 1;
    }
}
