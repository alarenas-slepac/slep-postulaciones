import { configuration } from './config.js';

// Diagnóstico local: no solicita QR, no abre WhatsApp y no imprime el token.
try {
    const config = configuration();
    const response = await fetch(`http://${config.host}:${config.port}/v1/status`, {
        headers: { Authorization: `Bearer ${config.token}` }, signal: AbortSignal.timeout(5000),
    });
    const result = await response.json();
    const allowed = ['idle', 'connecting', 'qr', 'connected', 'reconnecting', 'paused', 'logged_out', 'unlinked'];
    if (!response.ok || !allowed.includes(result.state)) throw new Error('status_failed');
    process.stdout.write(`Estado: ${result.state}\n`);
} catch {
    process.stderr.write('No se pudo consultar el servicio. Revisa proceso, puerto y configuración privada.\n');
    process.exitCode = 1;
}
