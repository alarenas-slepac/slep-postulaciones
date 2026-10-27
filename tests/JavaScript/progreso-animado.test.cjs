const { test } = require('node:test');
const assert = require('node:assert/strict');
const { pathToFileURL } = require('node:url');
const path = require('node:path');
const modulo = import(pathToFileURL(path.join(__dirname, '../../resources/js/progreso-animado.js')).href);

async function entorno({ reducido = false, observer = true, raf = true, observarFalla = false, porcentaje = 60 } = {}) {
    const cifra = { dataset: { slepCount: '1500' }, textContent: '1.500' };
    const percent = { dataset: { slepCount: String(porcentaje), slepDecimals: '1', slepSuffix: ' %' }, textContent: `${porcentaje},0 %` };
    const barra = { dataset: { slepFill: String(porcentaje) }, style: { width: `${porcentaje}%` } };
    const grupo = { querySelectorAll: selector => selector.includes('count') ? [cifra, percent] : [barra] };
    const root = { querySelectorAll: () => [grupo] };
    const frames = new Map(), eventos = {};
    let mediaListener, callback, instancias = 0, secuencia = 0, desobservados = 0;
    const media = { matches: reducido, addEventListener: (_, fn) => { mediaListener = fn; }, removeEventListener: () => { mediaListener = null; } };
    const browser = {
        matchMedia: () => media,
        cancelAnimationFrame: id => frames.delete(id),
        addEventListener: (tipo, fn) => { eventos[tipo] = fn; }, removeEventListener: tipo => { delete eventos[tipo]; },
    };
    if (raf) browser.requestAnimationFrame = fn => { const id = ++secuencia; frames.set(id, fn); return id; };
    if (observer) browser.IntersectionObserver = class {
        constructor(fn, options) { callback = fn; instancias++; assert.equal(options.threshold, .2); }
        observe() { if (observarFalla) throw new Error('Observador no disponible'); }
        unobserve() { desobservados++; }
        disconnect() {}
    };
    const { iniciarProgresoAnimado } = await modulo;
    const limpiar = iniciarProgresoAnimado(root, browser);
    return { cifra, percent, barra, frames, eventos, limpiar,
        inicializar: () => iniciarProgresoAnimado(root, browser), instancias: () => instancias, desobservados: () => desobservados,
        visible: (isIntersecting = true, intersectionRatio = .5) => callback([{ target: grupo, isIntersecting, intersectionRatio }]),
        tick: tiempo => { const pendientes = [...frames.values()]; frames.clear(); pendientes.forEach(fn => fn(tiempo)); },
        reducir: () => mediaListener({ matches: true }),
    };
}

test('espera a que el bloque entre en pantalla y anima cifras, porcentaje y barra juntos', async () => {
    const ui = await entorno();
    assert.equal(ui.cifra.textContent, '0'); assert.equal(ui.barra.style.width, '0%');
    ui.visible(false); ui.visible(true, .1); assert.equal(ui.frames.size, 0);
    ui.visible(); ui.tick(0); ui.tick(500);
    assert.equal(ui.cifra.textContent, '1.313'); assert.equal(ui.percent.textContent, '52,5 %');
    assert.equal(ui.barra.style.width, '52.5%');
    ui.tick(1000);
    assert.equal(ui.cifra.textContent, '1.500'); assert.equal(ui.percent.textContent, '60,0 %');
    assert.equal(ui.barra.style.width, '60%'); assert.equal(ui.frames.size, 0);
});

test('la animacion ocurre una vez aunque se vuelva a desplazar o inicializar', async () => {
    const ui = await entorno(); ui.visible(); ui.tick(0); ui.tick(1000);
    ui.visible(); ui.inicializar();
    assert.equal(ui.instancias(), 1); assert.equal(ui.frames.size, 0);
    assert.equal(ui.cifra.textContent, '1.500'); assert.ok(ui.desobservados() >= 1);
});

test('movimiento reducido o navegador sin observador o RAF conserva los datos finales', async () => {
    for (const opciones of [{ reducido: true }, { observer: false }, { raf: false }]) {
        const ui = await entorno(opciones);
        assert.equal(ui.cifra.textContent, '1.500'); assert.equal(ui.percent.textContent, '60,0 %');
        assert.equal(ui.barra.style.width, '60%'); assert.equal(ui.frames.size, 0);
    }
});

test('activar movimiento reducido durante la animacion finaliza pendientes y activos', async () => {
    const ui = await entorno(); ui.visible(); ui.tick(0); ui.tick(200); ui.reducir();
    assert.equal(ui.frames.size, 0); assert.equal(ui.cifra.textContent, '1.500');
    assert.equal(ui.barra.style.width, '60%'); ui.visible(); assert.equal(ui.frames.size, 0);
});

test('imprimir o desmontar muestra los valores completos aunque sigan fuera de pantalla', async () => {
    const ui = await entorno(); ui.eventos.beforeprint();
    assert.equal(ui.percent.textContent, '60,0 %'); assert.equal(ui.barra.style.width, '60%');
    ui.limpiar(); assert.equal(ui.eventos.beforeprint, undefined); assert.equal(ui.frames.size, 0);
});

test('cero y cien llegan al valor exacto y la barra no excede su pista', async () => {
    for (const porcentaje of [0, 100, 125]) {
        const ui = await entorno({ porcentaje }); ui.visible(); ui.tick(0); ui.tick(1000);
        assert.equal(ui.percent.textContent, `${porcentaje},0 %`);
        assert.equal(ui.barra.style.width, `${Math.min(100, porcentaje)}%`);
    }
});

test('un fallo al observar recupera los valores finales', async () => {
    const ui = await entorno({ observarFalla: true });
    assert.equal(ui.cifra.textContent, '1.500'); assert.equal(ui.barra.style.width, '60%');
});

test('se puede iniciar en una pagina sin medidores', async () => {
    const { iniciarProgresoAnimado } = await modulo;
    assert.doesNotThrow(() => iniciarProgresoAnimado({ querySelectorAll: () => [] }, {}));
});
