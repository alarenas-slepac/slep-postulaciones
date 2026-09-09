const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const blade = fs.readFileSync(path.join(__dirname, '../../resources/views/reemplazos/personal/revision.blade.php'), 'utf8');
const script = blade.match(/<script>([\s\S]*?)<\/script>/)[1];
const flush = () => new Promise(resolve => setImmediate(resolve));

function setup(fetch, forms = []) {
    const listeners = {};
    const panel = {
        innerHTML: '', attributes: {}, children: [], batchError: {textContent: ''}, submitted: [],
        querySelector() { return this.batchError; },
        querySelectorAll() { return forms; },
        setAttribute(key, value) { this.attributes[key] = value; },
        removeAttribute(key) { delete this.attributes[key]; },
        replaceChildren(...children) { this.children = children; },
        scrollIntoView() {},
        addEventListener(type, listener) { listeners['panel:' + type] = listener; },
    };
    vm.runInNewContext(script, {
        document: {
            getElementById() { return panel; },
            createElement(tag) { return {tag, dataset: {}, children: [],
                appendChild(child) { this.children.push(child); },
                submit() { panel.submitted.push(this); },
            }; },
            body: {appendChild() {}},
            addEventListener(type, listener) { listeners[type] = listener; },
        },
        window: {location: {href: 'https://example.test/revision?revision=1'}, history: {replaceState(_state, _title, href) { panel.url = href; }}},
        URL, URLSearchParams, FormData, AbortController, setTimeout, clearTimeout, fetch,
    });
    function click(href, changes = {}) {
        let prevented = false;
        listeners.click({target: {closest() { return {href}; }}, button: 0,
            preventDefault() { prevented = true; }, ...changes});
        return prevented;
    }
    return {panel, listeners, click};
}
function response(html, changes = {}) {
    return {ok: true, redirected: false, headers: {get() { return '1'; }}, text: async () => html, ...changes};
}

test('loads only the requested fragment and releases the busy indicator', async () => {
    let request;
    const ui = setup(async (url, options) => { request = {url: new URL(url.href), options}; return response('<div>Selected RUT</div>'); });
    assert.equal(ui.click('https://example.test/revision?revision=1&q=111111111#filas-padron'), true);
    await flush();
    assert.equal(ui.panel.url.includes('solo_filas'), false);
    assert.equal(request.url.searchParams.get('solo_filas'), '1');
    assert.equal(request.url.searchParams.get('q'), '111111111');
    assert.equal(request.options.credentials, 'same-origin');
    assert.equal(ui.panel.innerHTML, '<div>Selected RUT</div>');
    assert.equal(ui.panel.attributes['aria-busy'], undefined);
});

function decisionForm(id, selection, rut = '111111111', valid = true) {
    const values = {_token: 'synthetic-csrf', revision: '1', fila: String(id), personal_id: selection,
        justificacion: 'Decisión sintética justificada.', decision_anterior: '0', q: rut, conflictos_page: '2'};
    return {dataset: {rut}, action: 'https://example.test/import',
        elements: {namedItem(name) { return name in values ? {value: values[name]} : null; }},
        closest() { return {}; }, reportValidity() { return valid; }};
}

function batchClick(ui) {
    const button = {};
    ui.listeners['panel:click']({target: {closest() { return button; }}});
    return button;
}

test('batch submits only explicit selections, preserves individual reasons and context', () => {
    const ui = setup(() => {}, [decisionForm(1, '101'), decisionForm(2, ''), decisionForm(3, '0')]);
    const button = batchClick(ui);
    assert.equal(ui.panel.submitted.length, 1);
    const fields = Object.fromEntries(ui.panel.submitted[0].children.map(input => [input.name, input.value]));
    assert.equal(fields.accion, 'resolver_varias');
    assert.equal(fields._token, 'synthetic-csrf');
    assert.equal(fields.rut, '111111111');
    assert.equal(fields.conflictos_page, '2');
    assert.equal(fields['decisiones[0][fila]'], '1');
    assert.equal(fields['decisiones[1][fila]'], '3');
    assert.equal(fields['decisiones[1][personal_id]'], '0');
    assert.equal(fields['decisiones[0][justificacion]'], 'Decisión sintética justificada.');
    assert.equal(fields['decisiones[2][fila]'], undefined);
    assert.equal(button.disabled, true);
    batchClick(ui);
    assert.equal(ui.panel.submitted.length, 1);
});

test('batch rejects empty selection, different RUTs, duplicate IDs and invalid justification', () => {
    for (const forms of [[decisionForm(1, '')],
        [decisionForm(1, '101'), decisionForm(2, '102', '222222222')],
        [decisionForm(1, '101'), decisionForm(2, '101')],
        [decisionForm(1, '101'), decisionForm(2, '102', '111111111', false)]]) {
        const ui = setup(() => {}, forms);
        batchClick(ui);
        assert.equal(ui.panel.submitted.length, 0);
    }
});

test('batch normalizes RUT formatting but does not preselect any contract', () => {
    const ui = setup(() => {}, [decisionForm(1, '101', '11.111.111-1'), decisionForm(2, '102')]);
    batchClick(ui);
    assert.equal(ui.panel.submitted.length, 1);
});

test('an older response cannot overwrite the most recently selected RUT', async () => {
    const requests = [];
    const ui = setup((url, options) => new Promise(resolve => requests.push({resolve, options})));
    ui.click('https://example.test/revision?q=111111111');
    ui.click('https://example.test/revision?q=222222222');
    assert.equal(requests[0].options.signal.aborted, true);
    requests[1].resolve(response('Second RUT'));
    await flush();
    requests[0].resolve(response('First RUT'));
    await flush();
    assert.equal(ui.panel.innerHTML, 'Second RUT');
});

test('expired session and server errors offer retry without inserting an unrelated page', async () => {
    for (const changes of [{ok: false}, {redirected: true}, {headers: {get() { return null; }}}]) {
        const ui = setup(async () => response('Unexpected page', changes));
        ui.click('https://example.test/revision?q=111111111');
        await flush();
        assert.equal(ui.panel.innerHTML, '');
        assert.equal(ui.panel.children[1].textContent, 'Reintentar');
        assert.equal(ui.panel.children[2].textContent, 'Abrir revisión completa');
    }
});

test('preserves modified clicks and normal POST submission for server revalidation', () => {
    const ui = setup(() => { throw new Error('Should not fetch'); });
    assert.equal(ui.click('https://example.test/revision?q=111111111', {ctrlKey: true}), false);
    ui.listeners['panel:submit']({target: {method: 'post'}, preventDefault() { assert.fail('POST intercepted'); }});
});
