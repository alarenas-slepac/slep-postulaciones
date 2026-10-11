import { openSync, closeSync, fstatSync, readSync, realpathSync, constants } from 'node:fs';
import { resolve, relative, isAbsolute, basename } from 'node:path';
import { createHash } from 'node:crypto';

export class DocumentError extends Error {
    constructor(code) { super(code); this.code = code; }
}

export function validateDocument(value) {
    if (!value || typeof value !== 'object' || Array.isArray(value)
        || typeof value.path !== 'string' || !/^[1-9][0-9]*\/ORDEN_TRABAJO_[A-Za-z0-9_-]{1,100}\.pdf$/.test(value.path)
        || typeof value.sha256 !== 'string' || !/^[a-f0-9]{64}$/.test(value.sha256)) throw new DocumentError('invalid_document');
    const result = { path: value.path, sha256: value.sha256 };
    if (value.location !== undefined) {
        const l = value.location;
        if (!l || typeof l !== 'object' || !Number.isFinite(l.latitude) || !Number.isFinite(l.longitude)
            || Math.abs(l.latitude) > 90 || Math.abs(l.longitude) > 180 || (l.latitude === 0 && l.longitude === 0)
            || typeof l.name !== 'string' || !l.name.trim() || [...l.name].length > 120
            || typeof l.address !== 'string' || [...l.address].length > 140
            || /[\x00-\x1f\x7f]/.test(l.name + l.address)) throw new DocumentError('invalid_document');
        result.location = { latitude: l.latitude, longitude: l.longitude, name: l.name, address: l.address };
    }
    return result;
}

export function readDocument(document, directory) {
    let descriptor;
    try {
        if (!directory) throw new DocumentError('document_unavailable');
        const root = realpathSync(directory);
        const requested = resolve(root, document.path);
        const target = realpathSync(requested);
        const inside = relative(root, target);
        if (target !== requested || !inside || inside.startsWith('..') || isAbsolute(inside)
            || /(?:^|[\\/])(?:public|public_html)(?:[\\/]|$)/i.test(target)) throw new DocumentError('invalid_document');
        // Leer desde el descriptor verificado, sin URLs, enlaces finales ni cargas públicas.
        descriptor = openSync(target, constants.O_RDONLY | (constants.O_NOFOLLOW ?? 0));
        const info = fstatSync(descriptor);
        if (!info.isFile() || info.size < 5 || info.size > 5 * 1024 * 1024) throw new DocumentError('invalid_document');
        // Acotar la lectura aunque el archivo crezca después de fstat.
        const bounded = Buffer.alloc(info.size + 1);
        let size = 0;
        while (size < bounded.length) {
            const count = readSync(descriptor, bounded, size, bounded.length - size, null);
            if (!count) break;
            size += count;
        }
        if (size !== info.size) throw new DocumentError('document_changed');
        const buffer = bounded.subarray(0, size);
        if (buffer.subarray(0, 5).toString() !== '%PDF-') throw new DocumentError('invalid_document');
        if (createHash('sha256').update(buffer).digest('hex') !== document.sha256) throw new DocumentError('document_changed');
        return { document: buffer, mimetype: 'application/pdf', fileName: basename(document.path) };
    } catch (error) {
        throw error instanceof DocumentError ? error : new DocumentError('document_unavailable');
    } finally {
        if (descriptor !== undefined) closeSync(descriptor);
    }
}
