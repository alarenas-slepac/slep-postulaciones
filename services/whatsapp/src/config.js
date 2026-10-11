import { resolve, isAbsolute, parse } from 'node:path';
import { fileURLToPath } from 'node:url';

export function configuration(env = process.env) {
    const token = env.WHATSAPP_SERVICE_TOKEN ?? '';
    const dataDir = env.WHATSAPP_DATA_DIR ?? fileURLToPath(new URL('../../../storage/app/private/whatsapp', import.meta.url));
    const port = Number(env.WHATSAPP_PORT ?? 3210);
    const documentRoot = env.WHATSAPP_DOCUMENT_ROOT || null;
    if (!/^[A-Za-z0-9_-]{32,256}$/.test(token) || !isAbsolute(dataDir) || resolve(dataDir) === parse(resolve(dataDir)).root
        || /(?:^|[\\/])(?:public|public_html)(?:[\\/]|$)/i.test(dataDir)
        || !Number.isInteger(port) || port < 1024 || port > 65535
        || (documentRoot && (!isAbsolute(documentRoot) || resolve(documentRoot) === parse(resolve(documentRoot)).root
            || /(?:^|[\\/])(?:public|public_html)(?:[\\/]|$)/i.test(documentRoot)))) {
        throw new Error('configuration');
    }
    return { token, dataDir: resolve(dataDir), documentRoot: documentRoot ? resolve(documentRoot) : null, host: '127.0.0.1', port };
}
