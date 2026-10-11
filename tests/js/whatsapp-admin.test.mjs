import test from 'node:test';
import assert from 'node:assert/strict';
import { qrIsValid } from '../../resources/js/whatsapp-admin.js';

test('QR acepta sólo PNG en memoria con fecha vigente', () => {
    assert.ok(qrIsValid('data:image/png;base64,aGVsbG8=', '2030-01-01T00:00:00Z', 0));
    assert.ok(!qrIsValid('https://example.test/qr.png', '2030-01-01T00:00:00Z', 0));
    assert.ok(!qrIsValid('data:image/svg+xml;base64,aGVsbG8=', '2030-01-01T00:00:00Z', 0));
});
test('QR expirado o fecha inválida no se muestra', () => {
    assert.ok(!qrIsValid('data:image/png;base64,aGVsbG8=', 'invalid', 0));
    assert.ok(!qrIsValid('data:image/png;base64,aGVsbG8=', '2000-01-01T00:00:00Z', Date.now()));
});
