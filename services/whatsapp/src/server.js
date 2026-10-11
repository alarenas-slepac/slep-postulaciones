import { createServer } from 'node:http';
import { createHash, timingSafeEqual } from 'node:crypto';
import { BridgeError } from './session.js';

async function body(request) {
    if (!/^application\/json(?:;|$)/i.test(request.headers['content-type'] ?? '')) throw new BridgeError('invalid_message', 415);
    let size = 0; const chunks = [];
    for await (const chunk of request) {
        size += chunk.length;
        if (size > 16384) throw new BridgeError('invalid_message', 413);
        chunks.push(chunk);
    }
    try {
        const value = JSON.parse(Buffer.concat(chunks).toString('utf8'));
        if (!value || typeof value !== 'object' || Array.isArray(value)) throw new Error('invalid_object');
        return value;
    }
    catch { throw new BridgeError('invalid_message', 422); }
}

export function createApi({ token, session }) {
    const digest = value => createHash('sha256').update(value).digest();
    const server = createServer(async (request, response) => {
        const reply = (status, data) => {
            if (response.destroyed) return;
            response.writeHead(status, { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-store',
                'X-Content-Type-Options': 'nosniff' });
            response.end(JSON.stringify(data));
        };
        try {
            if (request.headers.origin || !timingSafeEqual(digest(request.headers.authorization ?? ''), digest(`Bearer ${token}`))) {
                reply(401, { error: 'authentication' }); return;
            }
            const path = request.url;
            if (request.method === 'GET' && path === '/v1/status') { reply(200, session.status()); return; }
            if (request.method === 'GET' && path === '/v1/qr') { reply(200, session.getQr()); return; }
            if (request.method === 'POST' && path === '/v1/session/qr') { await body(request); reply(202, await session.renewQr()); return; }
            if (request.method === 'POST' && path === '/v1/session/unlink') { await body(request); reply(200, await session.unlink()); return; }
            if (request.method === 'POST' && path === '/v1/messages') { reply(200, await session.send(await body(request))); return; }
            reply(404, { error: 'not_found' });
        } catch (error) {
            reply(error instanceof BridgeError ? error.status : 503, { error: error instanceof BridgeError ? error.code : 'service_unavailable' });
        }
    });
    server.requestTimeout = 10000; server.headersTimeout = 5000; server.maxRequestsPerSocket = 100;
    return server;
}
