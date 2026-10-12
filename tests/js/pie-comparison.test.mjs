import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { count, preview, summarize, formatHours, difference, hourFields } from '../../resources/js/pie-comparison-calculation.js';

const result = spawnSync(process.env.SGA_TEST_PHP_BINARY || 'php', ['tests/Support/pie-comparison-fixture.php'], { encoding: 'utf8', shell: false });
assert.equal(result.status, 0, result.stderr || result.error?.message);
const fixture = JSON.parse(result.stdout);
const original = { neet: 5, neep: 0, matricula: 100, rule_key: 'con_jec',
    calculation: { total_crono_minutos: 500, prof_educ_dif_minutos: 350, pae_minutos: 150 } };

for (const item of fixture.cases) {
    test(`${item.key}: NEET ${item.neet}, NEEP ${item.neep} coincide con Laravel`, () => {
        const value = preview({ ...original, rule_key: item.key }, String(item.neet), String(item.neep), fixture.matrix);
        assert.equal(value.valid, true); assert.equal(value.total, item.neet + item.neep);
        for (const field of hourFields) assert.equal(value.calculation[field], item.expected[field]);
        assert.equal(value.calculation.calculo_observacion, item.expected.calculo_observacion);
    });
}

test('preserva horas guardadas cuando no hay cambios y al deshacer la edición', () => {
    assert.deepEqual(preview(original, '5', '0', fixture.matrix).calculation, original.calculation);
    assert.equal(preview(original, '5', '0', fixture.matrix).changed, false);
    assert.equal(preview(original, '0', '2', fixture.matrix).calculation.total_crono_minutos, 360);
    assert.deepEqual(preview(original, '5', '0', fixture.matrix).calculation, original.calculation);
});

test('rechaza vacíos, decimales, negativos, notación científica y cantidades fuera del límite', () => {
    for (const value of ['', '-1', '1.2', '1e2', '10000', 'abc', ' 5 ']) {
        assert.equal(count(value), null); assert.equal(preview(original, value, '0', fixture.matrix).valid, false);
    }
    assert.equal(count('0'), 0); assert.equal(count('9999'), 9999);
    assert.equal(preview({ ...original, matricula: 10 }, '9', '2', fixture.matrix).valid, false);
});

test('distingue horas desconocidas de cero y conserva una suma parcial identificada', () => {
    const unknown = preview(original, '1', '39', fixture.matrix);
    const zero = preview(original, '0', '0', fixture.matrix);
    const known = preview(original, '0', '2', fixture.matrix);
    const total = summarize([unknown, zero, known]);
    assert.equal(unknown.calculation.total_crono_minutos, null); assert.equal(zero.calculation.total_crono_minutos, 0);
    assert.equal(total.unknown, 1); assert.equal(total.total_crono_minutos, 360); assert.equal(total.total, 42);
    assert.equal(formatHours(null), '—'); assert.equal(formatHours(0), '00:00');
});

test('suma cambios de varios cursos y detecta cantidades inválidas', () => {
    const total = summarize([preview(original, '0', '2', fixture.matrix), preview(original, '10', '0', fixture.matrix)]);
    assert.equal(total.neet, 10); assert.equal(total.neep, 2); assert.equal(total.total, 12);
    assert.equal(total.changed, 2); assert.equal(total.total_crono_minutos, 960);
    assert.equal(summarize([preview(original, '', '0', fixture.matrix)]).invalid, 1);
});

test('formatea diferencias positivas, negativas y mayores de 24 horas', () => {
    assert.equal(difference(5, 8), '+3'); assert.equal(difference(8, 5), '−3'); assert.equal(difference(5, 5), '0');
    assert.equal(difference(600, 720, true), '+02:00'); assert.equal(difference(720, 600, true), '−02:00');
    assert.equal(formatHours(1800), '30:00'); assert.equal(difference(null, 600, true), '—');
});

test('no inventa un cálculo si falta la matriz de reglas', () => {
    assert.equal(preview(original, '6', '1', {}).valid, false);
});
