import { configuration } from './config.js';
import { Store } from './store.js';
import { Session } from './session.js';
import { createApi } from './server.js';
import lockfile from 'proper-lockfile';
import pino from 'pino';
import QRCode from 'qrcode';

// Algunas dependencias escriben detalles criptográficos mediante console: suprimirlos.
for (const name of ['log', 'info', 'warn', 'error', 'debug', 'trace', 'dir']) console[name] = () => {};
const log = event => process.stdout.write(`${JSON.stringify({ event, at: new Date().toISOString() })}\n`);
process.umask(0o077);
let store, session, api, release;
let exiting = false;
async function shutdown(code = 0) {
    if (exiting) return;
    exiting = true;
    api?.close();
    await session?.close().catch(() => {});
    // Una petición aceptada puede seguir enviando: guardar la reserva como incierta al reiniciar.
    store?.close();
    await release?.().catch(() => {});
    process.exit(code);
}

try {
    const config = configuration();
    // Crear sólo el directorio antes del bloqueo; no abrir SQLite desde dos instancias.
    const { mkdirSync, realpathSync } = await import('node:fs');
    mkdirSync(config.dataDir, { recursive: true, mode: 0o700 });
    const directory = realpathSync(config.dataDir);
    release = await lockfile.lock(directory, { retries: 0, stale: 30000, update: 10000,
        onCompromised: () => { log('session_lock_lost'); void shutdown(1); } });
    store = new Store(directory);
    const baileys = await import('@whiskeysockets/baileys');
    session = new Session({
        store, documentRoot: config.documentRoot, authFactory: () => store.auth(baileys), reasons: baileys.DisconnectReason,
        qrImage: qr => QRCode.toDataURL(qr, { errorCorrectionLevel: 'M', margin: 2, width: 320 }), logger: log,
        createSocket: auth => baileys.default({ auth, logger: pino({ enabled: false }),
            browser: baileys.Browsers.ubuntu('SGA'), markOnlineOnConnect: false, syncFullHistory: false,
            shouldSyncHistoryMessage: () => false, getMessage: async () => undefined,
            connectTimeoutMs: 20000, defaultQueryTimeoutMs: 10000, maxMsgRetryCount: 0,
            enableAutoSessionRecreation: false, enableRecentMessageCache: false }),
    });
    api = createApi({ token: config.token, session });
    await new Promise((resolve, reject) => { api.once('error', reject); api.listen(config.port, config.host, resolve); });
    // Una sesión nueva sólo conecta tras la acción explícita "Generar QR".
    await session.start();
    log('service_started');
    process.on('SIGINT', () => void shutdown());
    process.on('SIGTERM', () => void shutdown());
    process.on('uncaughtException', () => { log('runtime_failed'); void shutdown(1); });
    process.on('unhandledRejection', () => { log('runtime_failed'); void shutdown(1); });
} catch {
    log('startup_failed');
    await shutdown(1);
}
