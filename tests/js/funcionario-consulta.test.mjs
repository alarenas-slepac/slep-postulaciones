import test from 'node:test';
import assert from 'node:assert/strict';
import { normalizeRutInput } from '../../resources/js/funcionario-consulta.js';

test('prepara un RUT pegado con formato sin cambiar su DV', () => {
    assert.equal(normalizeRutInput(' 99.000.001-8 '), '990000018');
    assert.equal(normalizeRutInput('99.000.010-k'), '99000010K');
});

test('no inventa un DV ni elimina letras arbitrarias para formar otra identidad', () => {
    assert.equal(normalizeRutInput('99000001'), '99000001');
    assert.equal(normalizeRutInput('99X0000018'), '99X0000018');
});
