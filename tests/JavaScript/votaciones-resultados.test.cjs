const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const script = fs.readFileSync(path.join(__dirname, '../../resources/js/votaciones-resultados.js'), 'utf8');

function setup(values) {
    const ballots = values.map(items => ({
        inputs: items.map(([value, valid]) => ({ value: String(value), dataset: { validVote: valid ? '1' : '0' } })),
        outputs: { validos: {}, emitidos: {} },
        querySelectorAll() { return this.inputs; },
        querySelector(selector) { return this.outputs[selector.includes('validos') ? 'validos' : 'emitidos']; },
    }));
    const button = { disabled: false, attrs: {}, setAttribute(k, v) { this.attrs[k] = v; } };
    const publish = { disabled: false }, notice = { hidden: true }, events = {};
    const form = { querySelectorAll(selector) { return selector.includes('ballot') ? ballots : [button]; },
        querySelector() { return notice; }, addEventListener(type, callback) { events[type] = callback; } };
    vm.runInNewContext(script, { Intl, document: { querySelector() { return form; }, querySelectorAll() { return [publish]; } } });
    return { ballots, button, publish, notice, events };
}

test('cada papeleta conserva sus totales y excluye nulos y blancos del voto valido', () => {
    const ui = setup([[[10, true], [5, true], [1, false], [2, false]], [[8, true], [3, false]]]);
    assert.equal(ui.ballots[0].outputs.validos.textContent, '15');
    assert.equal(ui.ballots[0].outputs.emitidos.textContent, '18');
    assert.equal(ui.ballots[1].outputs.validos.textContent, '8');
    assert.equal(ui.ballots[1].outputs.emitidos.textContent, '11');
});

test('editar recalcula sin sumar otras papeletas y exige guardar antes de publicar', () => {
    const ui = setup([[[10, true], [2, false]], [[20, true]]]);
    ui.ballots[0].inputs[0].value = '15'; ui.events.input();
    assert.equal(ui.ballots[0].outputs.emitidos.textContent, '17');
    assert.equal(ui.ballots[1].outputs.emitidos.textContent, '20');
    assert.equal(ui.publish.disabled, true); assert.equal(ui.notice.hidden, false);
});

test('campos vacios decimales negativos y fuera de limite no muestran un total aparentemente valido', () => {
    for (const value of ['', '-1', '1.5', '1e3', '1000001', 'texto']) {
        const ui = setup([[[value, true], [2, false]]]);
        assert.equal(ui.ballots[0].outputs.validos.textContent, '—');
        assert.equal(ui.ballots[0].outputs.emitidos.textContent, '—');
    }
    const cero = setup([[[0, true], [0, false]]]);
    assert.equal(cero.ballots[0].outputs.emitidos.textContent, '0');
});

test('al enviar se bloquea el boton para evitar doble clic', () => {
    const ui = setup([[[10, true]]]); ui.events.submit();
    assert.equal(ui.button.disabled, true); assert.equal(ui.button.attrs['aria-busy'], 'true');
});

test('el script se puede cargar sin formulario de resultados', () => {
    assert.doesNotThrow(() => vm.runInNewContext(script, { document: { querySelector() { return null; } } }));
});
