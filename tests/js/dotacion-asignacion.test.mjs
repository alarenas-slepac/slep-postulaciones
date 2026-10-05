import test from 'node:test';
import assert from 'node:assert/strict';
import { matchesCatalog, matchesNeed, matchesRevision, normalizeSearch } from '../../resources/js/dotacion-asignacion.js';

const need = { section: 'plan_estudio', course: 'NT1 + NT2', pending: true, search: 'NT1 + NT2 · Exploración del entorno natural · Libre disposición' };
const all = { q: '', section: '', course: '', pending: false };

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
