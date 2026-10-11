// Cron ejecuta este lanzador; el puente vive en el mismo proceso, sin hijos huérfanos.
import { mkdirSync, chmodSync, realpathSync, existsSync, lstatSync, readFileSync,
    writeFileSync, renameSync, unlinkSync, appendFileSync, statSync } from 'node:fs';
import { randomBytes } from 'node:crypto';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import lockfile from 'proper-lockfile';
import { configuration } from '../src/config.js';

const windowMs = 10 * 60 * 1000;
const maximumStarts = 5;
const lockOptions = { retries: 0, stale: 30000, update: 10000 };
const events = new Set(['runner_started', 'runner_stopped', 'runner_restart', 'runner_limit', 'runner_lock_lost',
    'runner_failed', 'service_started', 'startup_failed', 'runtime_failed', 'session_lock_lost', 'connected',
    'session_paused', 'reconnect_limit', 'storage_failed', 'connection_failed']);

function prepare(config) {
    const { dataDir } = configuration({ WHATSAPP_DATA_DIR: config.dataDir, WHATSAPP_PORT: String(config.port),
        WHATSAPP_SERVICE_TOKEN: config.token });
    process.umask(0o077);
    mkdirSync(dataDir, { recursive: true, mode: 0o700 });
    const canonical = realpathSync(dataDir);
    if (/(?:^|[\\/])(?:public|public_html)(?:[\\/]|$)/i.test(canonical)) throw new Error('directory');
    const directory = join(canonical, 'cron-control');
    if (existsSync(directory) && lstatSync(directory).isSymbolicLink()) throw new Error('directory');
    mkdirSync(directory, { recursive: true, mode: 0o700 });
    chmodSync(directory, 0o700);
    return directory;
}

function recentStarts(directory, now = Date.now()) {
    const file = join(directory, 'attempts.json');
    if (!existsSync(file)) return [];
    const entries = JSON.parse(readFileSync(file, 'utf8'));
    if (!Array.isArray(entries) || entries.length > maximumStarts || entries.some(n => !Number.isSafeInteger(n) || n < 0)) {
        throw new Error('attempts');
    }
    return entries.filter(n => n > now - windowMs);
}

function atomicWrite(directory, name, value) {
    const temporary = join(directory, `.control-${randomBytes(8).toString('hex')}.tmp`);
    writeFileSync(temporary, value, { flag: 'wx', mode: 0o600 });
    renameSync(temporary, join(directory, name));
}

function marker(directory, name) { atomicWrite(directory, name, ''); }
function remove(directory, name) { if (existsSync(join(directory, name))) unlinkSync(join(directory, name)); }
function restartId(directory) {
    const file = join(directory, 'restart');
    return existsSync(file) ? readFileSync(file, 'utf8') : '';
}

export function recordEvent(directory, name) {
    if (!events.has(name)) return;
    const file = join(directory, 'events.log');
    if (existsSync(file) && statSync(file).size >= 65536) renameSync(file, join(directory, 'events.previous.log'));
    appendFileSync(file, `${JSON.stringify({ event: name, at: new Date().toISOString() })}\n`, { mode: 0o600 });
}

// Sólo nombres conocidos: nunca persistir cuerpos, errores, QR ni otros campos de dependencias.
export function recordBridgeOutput(directory, chunk) {
    const value = String(chunk);
    if (value.length > 2048) return;
    try { recordEvent(directory, JSON.parse(value).event); } catch { /* Descartar salida no estructurada. */ }
}

export async function command(config, action) {
    const directory = prepare(config);
    if (action === 'stop') marker(directory, 'disabled');
    else if (action === 'restart') atomicWrite(directory, 'restart', randomBytes(16).toString('hex'));
    else if (action === 'start') {
        // Serializar la reapertura del límite con run; nunca tocar un proceso ya activo.
        if (await lockfile.check(directory, lockOptions)) throw new Error('already_running');
        const release = await lockfile.lock(directory, lockOptions);
        try {
            atomicWrite(directory, 'attempts.json', '[]');
            remove(directory, 'blocked');
            remove(directory, 'disabled');
        } finally { await release(); }
    } else if (action !== 'status') throw new Error('action');
    return { enabled: !existsSync(join(directory, 'disabled')), blocked: existsSync(join(directory, 'blocked')),
        runner_running: await lockfile.check(directory, lockOptions), recent_starts: recentStarts(directory).length };
}

export async function runService(config, { boot = () => import('../src/index.js'),
    stop = () => new Promise(() => {
        // Señal del mismo proceso: nunca matar un PID leído de un archivo o una instancia ajena.
        if (!process.emit('SIGTERM')) process.exit(1);
        setTimeout(() => process.exit(1), 30000).unref();
    }), captureOutput = false } = {}) {
    const directory = prepare(config);
    let release, timer, closing = false;
    const close = async () => {
        clearInterval(timer);
        await release?.().catch(() => {});
    };
    const halt = async reason => {
        if (closing) return;
        closing = true;
        recordEvent(directory, reason);
        // Conservar el bloqueo hasta que el puente cierre; process.exit libera ambos bloqueos.
        await stop();
        await close();
    };
    try {
        release = await lockfile.lock(directory, { ...lockOptions, onCompromised: () => void halt('runner_lock_lost') });
    } catch (error) {
        if (error.code === 'ELOCKED') return null;
        throw error;
    }
    try {
        if (existsSync(join(directory, 'disabled')) || existsSync(join(directory, 'blocked'))) {
            await close(); return null;
        }
        const starts = recentStarts(directory);
        if (starts.length >= maximumStarts) {
            marker(directory, 'blocked'); recordEvent(directory, 'runner_limit');
            await close(); return null;
        }
        atomicWrite(directory, 'attempts.json', JSON.stringify([...starts, Date.now()]));
        const baseline = restartId(directory);
        recordEvent(directory, 'runner_started');
        if (captureOutput) {
            process.stdout.write = (chunk, encoding, callback) => {
                try { recordBridgeOutput(directory, chunk); } catch { void halt('runner_failed'); }
                const done = typeof encoding === 'function' ? encoding : callback;
                if (typeof done === 'function') done();
                return true;
            };
            process.stderr.write = (chunk, encoding, callback) => {
                const done = typeof encoding === 'function' ? encoding : callback;
                if (typeof done === 'function') done();
                return true;
            };
        }
        await boot();
        timer = setInterval(() => {
            try {
                if (existsSync(join(directory, 'disabled'))) void halt('runner_stopped');
                else if (restartId(directory) !== baseline) void halt('runner_restart');
            } catch { void halt('runner_failed'); }
        }, 1000);
        return { directory, close };
    } catch {
        recordEvent(directory, 'runner_failed');
        await close();
        throw new Error('runner');
    }
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    try {
        const [action, ...extra] = process.argv.slice(2);
        if (extra.length) throw new Error('arguments');
        const config = configuration();
        if (action === 'run') await runService(config, { captureOutput: true });
        else process.stdout.write(`${JSON.stringify(await command(config, action))}\n`);
    } catch {
        process.stderr.write('No se pudo ejecutar el control: revisar accion, configuracion privada, permisos y si existe una instancia activa.\n');
        process.exitCode = 1;
    }
}
