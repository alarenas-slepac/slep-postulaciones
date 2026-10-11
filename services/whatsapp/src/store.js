import { DatabaseSync } from 'node:sqlite';
import { mkdirSync, chmodSync, realpathSync } from 'node:fs';
import { join } from 'node:path';
import { createHmac, randomBytes } from 'node:crypto';

export class Store {
    constructor(directory) {
        mkdirSync(directory, { recursive: true, mode: 0o700 });
        this.directory = realpathSync(directory);
        if (/(?:^|[\\/])(?:public|public_html)(?:[\\/]|$)/i.test(this.directory)) throw new Error('public_storage');
        chmodSync(this.directory, 0o700);
        const path = join(this.directory, 'state.sqlite');
        this.db = new DatabaseSync(path);
        chmodSync(path, 0o600);
        this.db.exec(`PRAGMA journal_mode=WAL; PRAGMA synchronous=FULL; PRAGMA busy_timeout=5000;
            CREATE TABLE IF NOT EXISTS auth (id TEXT PRIMARY KEY, value TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS meta (id TEXT PRIMARY KEY, value TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS sends (id TEXT PRIMARY KEY, fingerprint TEXT NOT NULL, status TEXT NOT NULL,
                provider_id TEXT, created_at TEXT NOT NULL);`);
        if (!this.meta('fingerprint_secret')) this.setMeta('fingerprint_secret', randomBytes(32).toString('hex'));
        // Un proceso que terminó después de reservar podría haber enviado: jamás reenviar.
        this.db.exec("UPDATE sends SET status='unknown' WHERE status='sending'");
    }

    meta(id) { return this.db.prepare('SELECT value FROM meta WHERE id=?').get(id)?.value; }
    setMeta(id, value) { this.db.prepare('INSERT OR REPLACE INTO meta (id,value) VALUES (?,?)').run(id, value); }
    clearAuth() { this.db.exec('DELETE FROM auth'); }

    auth(baileys) {
        const read = id => {
            const value = this.db.prepare('SELECT value FROM auth WHERE id=?').get(id)?.value;
            return value ? JSON.parse(value, baileys.BufferJSON.reviver) : undefined;
        };
        const write = (id, value) => {
            if (value == null) this.db.prepare('DELETE FROM auth WHERE id=?').run(id);
            else this.db.prepare('INSERT OR REPLACE INTO auth (id,value) VALUES (?,?)')
                .run(id, JSON.stringify(value, baileys.BufferJSON.replacer));
        };
        const creds = read('creds') ?? baileys.initAuthCreds();
        write('creds', creds);
        return {
            state: {
                creds,
                keys: {
                    get: async (type, ids) => Object.fromEntries(ids.map(id => {
                        let value = read(`${type}:${id}`);
                        if (type === 'app-state-sync-key' && value) value = baileys.proto.Message.AppStateSyncKeyData.fromObject(value);
                        return [id, value];
                    })),
                    set: async data => {
                        this.db.exec('BEGIN IMMEDIATE');
                        try {
                            for (const [type, keys] of Object.entries(data)) {
                                for (const [id, value] of Object.entries(keys)) write(`${type}:${id}`, value);
                            }
                            this.db.exec('COMMIT');
                        } catch (error) { this.db.exec('ROLLBACK'); throw error; }
                    },
                },
            },
            save: update => { Object.assign(creds, update ?? {}); write('creds', creds); },
        };
    }

    fingerprint(to, text) { return createHmac('sha256', this.meta('fingerprint_secret')).update(JSON.stringify([to, text])).digest('hex'); }
    find(id) { return this.db.prepare('SELECT * FROM sends WHERE id=?').get(id); }
    reserve(id, fingerprint) {
        this.db.prepare("INSERT INTO sends (id,fingerprint,status,created_at) VALUES (?,?,'sending',?)")
            .run(id, fingerprint, new Date().toISOString());
    }
    finish(id, status, providerId = null) { this.db.prepare('UPDATE sends SET status=?,provider_id=? WHERE id=?').run(status, providerId, id); }
    close() { this.db.close(); }
}
