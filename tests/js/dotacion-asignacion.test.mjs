import test from 'node:test';
import assert from 'node:assert/strict';
import { hydratePersonalOptions, matchesCatalog, matchesNeed, matchesRevision, normalizeSearch } from '../../resources/js/dotacion-asignacion.js';

const need = { section: 'plan_estudio', course: 'NT1 + NT2', pending: true, search: 'NT1 + NT2 · Exploración del entorno natural · Libre disposición' };
const all = { q: '', section: '', course: '', pending: false };

// Doble del select nativo: cambiar sus grupos restablece la selección como en el DOM.
function deferredSelect(template, selected = '') {
    let value = selected;
    let pending = true;
    const select = {
        options: [{ value: '' }],
        ownerDocument: { getElementById: () => template },
        querySelectorAll: () => pending ? [{
            dataset: { personalCatalogo: 'autorizados' },
            replaceWith(fragment) { select.options = [{ value: '' }, ...fragment.options]; value = ''; pending = false; },
        }] : [],
        get value() { return value; },
        set value(next) { value = select.options.some(option => option.value === next) ? next : ''; },
    };
    return select;
}

test('el catálogo conserva selección, orden y metadatos sin compartir opciones entre formularios', () => {
    let clones = 0;
    const authorized = [
        { value: 'docente', dataset: { titulo: 'Educación Diferencial', prioridadLabel: 'Titular', titularDisponible: '4' } },
        { value: 'sin-titulo', dataset: { titulo: '', prioridadLabel: 'Contrata', contrataDisponible: '12' } },
    ];
    const template = { content: { cloneNode(deep) { assert.equal(deep, true); clones++; return { options: structuredClone(authorized) }; } } };
    const first = deferredSelect(template, 'sin-titulo');
    const second = deferredSelect(template);
    hydratePersonalOptions(first);
    hydratePersonalOptions(second);
    assert.equal(first.value, 'sin-titulo');
    assert.equal(second.value, '');
    assert.deepEqual(first.options.slice(1), authorized);
    first.options[1].dataset.titulo = 'Edición local';
    assert.equal(second.options[1].dataset.titulo, 'Educación Diferencial');
    assert.equal(authorized[0].dataset.titulo, 'Educación Diferencial');
    first.value = 'docente';
    hydratePersonalOptions(first);
    assert.equal(first.value, 'docente');
    assert.equal(clones, 2, 'Reabrir el formulario no vuelve a clonar su catálogo.');
});

test('una referencia ausente o una selección ajena no agrega candidatos', () => {
    const missing = deferredSelect(null);
    hydratePersonalOptions(missing);
    assert.deepEqual(missing.options, [{ value: '' }]);
    const select = deferredSelect({ content: { cloneNode: () => ({ options: [{ value: 'autorizado' }] }) } }, 'ajeno');
    hydratePersonalOptions(select);
    assert.equal(select.value, '');
    assert.deepEqual(select.options.map(option => option.value), ['', 'autorizado']);
});

test('la inicialización sin fragmento conserva los eventos de navegación', async () => {
    const events = [];
    globalThis.document = { readyState: 'complete', querySelector: () => null, querySelectorAll: () => [] };
    globalThis.window = { location: { hash: '' }, addEventListener: name => events.push(name) };
    try {
        await import('../../resources/js/dotacion-asignacion.js?regression-empty-hash');
        assert.ok(events.includes('hashchange'));
        assert.ok(events.includes('load'));
        assert.ok(events.includes('pageshow'));
    } finally {
        delete globalThis.window;
        delete globalThis.document;
    }
});

test('los catálogos filtran por título, curso, RUT y estados simultáneos', () => {
    const item = { search: 'Artes Visuales 1° Básico Docente 99.000.001-K Pedagogía en Educación Básica', states: 'declarada observado' };
    assert.ok(matchesCatalog(item, { q: 'educacion basica 99000001k', state: 'observado' }));
    assert.ok(matchesCatalog(item, { q: 'artes basico', state: 'declarada' }));
    assert.ok(!matchesCatalog(item, { q: 'artes', state: 'validado_uatp' }));
    assert.ok(!matchesCatalog(item, { q: 'matematica', state: '' }));
    assert.ok(matchesCatalog(item, { q: '', state: '' }));
});

test('la búsqueda ignora tildes, mayúsculas y espacios externos', () => {
    assert.equal(normalizeSearch('  EDUCACIÓN  '), 'educacion');
    assert.ok(matchesNeed(need, { ...all, q: '  EXPLORACION entorno  ' }));
    assert.ok(!matchesNeed(need, { ...all, q: 'matematica' }));
});
test('combina búsqueda, sección, curso y pendiente sin mezclar unidades o secciones', () => {
    assert.ok(matchesNeed(need, { q: 'entorno', section: 'plan_estudio', course: 'NT1 + NT2', pending: true }));
    assert.ok(!matchesNeed(need, { ...all, section: 'pie_colaborativo' }));
    assert.ok(!matchesNeed(need, { ...all, course: '1° Básico' }));
    assert.ok(!matchesNeed({ ...need, pending: false }, { ...all, pending: true }));
    assert.ok(matchesNeed({ ...need, pending: false }, all));
});
test('una combinación de cursos se encuentra por cualquiera de sus integrantes', () => {
    assert.ok(matchesNeed(need, { ...all, q: 'nt2' }));
    assert.ok(matchesNeed(need, { ...all, q: 'libre disposicion nt1' }));
});

test('la revisión combina bloque, estado y búsqueda sin excluir contratos mixtos', () => {
    const person = { blocks: 'plan_estudio pie', states: 'saldo reserva', search: 'Docente de prueba 99000001-K Pedagogía en Educación Básica' };
    assert.ok(matchesRevision(person, { block: 'pie', state: 'reserva', q: 'educacion basica' }));
    assert.ok(matchesRevision(person, { block: 'plan_estudio', state: 'saldo', q: 'docente' }));
    assert.ok(!matchesRevision(person, { block: 'parvularia', state: '', q: '' }));
    assert.ok(!matchesRevision(person, { block: '', state: 'cuadra', q: '' }));
});

test('las búsquedas de revisión y asignación reconocen el RUT con o sin puntos y guion', () => {
    const person = { blocks: 'plan_estudio', states: 'saldo', search: 'Docente 99.000.001-K' };
    for (const q of ['99000001k', '99.000.001-k', '99000001-K']) {
        assert.ok(matchesRevision(person, { block: '', state: '', q }));
        assert.ok(matchesNeed({ ...need, search: person.search }, { ...all, q }));
    }
    assert.ok(!matchesRevision(person, { block: '', state: '', q: '99000002k' }));
});

test('justificaciones y funciones revisables admiten estados simultáneos', () => {
    const person = { blocks: 'plan_estudio', states: 'saldo justificacion ajuste', search: 'Docente de prueba' };
    assert.ok(matchesRevision(person, { block: '', state: 'justificacion', q: '' }));
    assert.ok(matchesRevision(person, { block: 'plan_estudio', state: 'ajuste', q: 'PRUEBA' }));
    assert.ok(!matchesRevision(person, { block: '', state: 'sobrecarga', q: '' }));
});
