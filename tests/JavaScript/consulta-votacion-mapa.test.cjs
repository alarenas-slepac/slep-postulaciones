const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const script = fs.readFileSync(path.join(__dirname, '../../resources/js/consulta-votacion-mapa.js'), 'utf8').replace(/^import .*;\r?\n/gm, '');

function setup(datasets, { lazy = false, fail = false } = {}) {
    const maps = [], tiles = [], markers = [], observed = [], removed = [], controls = [];
    const elements = datasets.map(dataset => {
        const notice = { hidden: true };
        return { dataset: { ...dataset }, notice, closest() { return { querySelector() { return notice; } }; } };
    });
    let intersect;
    const window = {};
    class Observer {
        constructor(callback) { intersect = callback; }
        observe(element) { observed.push(element); }
        unobserve(element) { removed.push(element); }
    }
    if (lazy) window.IntersectionObserver = Observer;
    const L = {
        control: { zoom(options) { controls.push(options); return { addTo() { return this; } }; } },
        map(element, options) {
            if (fail) throw new Error('Mapa no disponible');
            const map = { element, options, setView(point, zoom) { this.point = point; this.zoom = zoom; return this; } };
            maps.push(map); return map;
        },
        tileLayer(url, options) {
            const layer = { url, options, listeners: {}, on(event, cb) { this.listeners[event] = cb; return this; }, addTo(map) { this.map = map; return this; } };
            tiles.push(layer); return layer;
        },
        divIcon(options) { return options; },
        marker(point, options) {
            const marker = { point, options, attrs: {}, getElement() { return { setAttribute: (key, value) => { this.attrs[key] = value; } }; }, addTo(map) { this.map = map; return this; }, bindPopup(popup) { this.popup = popup; return this; } };
            markers.push(marker); return marker;
        },
    };
    vm.runInNewContext(script, { L, window, IntersectionObserver: lazy ? Observer : undefined,
        document: { querySelectorAll() { return elements; }, createElement() { return { children: [], append(...nodes) { this.children.push(...nodes); } }; } },
    });
    return { maps, tiles, markers, elements, observed, removed, controls, reveal(index, visible = true) { intersect([{ target: elements[index], isIntersecting: visible }]); } };
}

const location = { latitude: '-37.1', longitude: '-73.1', name: 'Local de prueba', rbd: '9501' };

test('marca el destino correcto y mantiene texto del popup como texto, sin HTML del catalogo', () => {
    const ui = setup([{ ...location, name: '<img src=x onerror=prueba()>' }]);
    assert.deepEqual(Array.from(ui.maps[0].point), [-37.1, -73.1]);
    assert.deepEqual(Array.from(ui.markers[0].point), [-37.1, -73.1]);
    assert.equal(ui.markers[0].popup.children[0].textContent, '<img src=x onerror=prueba()>');
    assert.equal(ui.markers[0].popup.children[0].innerHTML, undefined);
    assert.equal(ui.markers[0].popup.children[1].textContent, 'RBD 9501');
    assert.equal(ui.maps[0].options.scrollWheelZoom, false);
    assert.match(ui.markers[0].attrs['aria-label'], /Ver ubicación de/);
    assert.equal(ui.controls[0].zoomInTitle, 'Acercar mapa');
    assert.equal(ui.controls[0].zoomOutTitle, 'Alejar mapa');
});

test('solicita teselas OSM con atribucion y solo el origen como referer', () => {
    const ui = setup([location]);
    assert.equal(ui.tiles[0].url, 'https://tile.openstreetmap.org/{z}/{x}/{y}.png');
    assert.equal(ui.tiles[0].options.referrerPolicy, 'origin');
    assert.match(ui.tiles[0].options.attribution, /openstreetmap.org\/copyright/);
    assert.doesNotMatch(ui.tiles[0].url, /rut|nombre|jornada/i);
});

test('rechaza coordenadas vacias, no numericas, fuera de rango y cero sin crear marcadores', () => {
    for (const [latitude, longitude] of [['', '-73.1'], ['-37.1', ''], ['abc', '-73.1'], ['91', '0'], ['0', '181'], ['0', '0']]) {
        const ui = setup([{ ...location, latitude, longitude }]);
        assert.equal(ui.maps.length, 0);
        assert.equal(ui.elements[0].notice.hidden, false);
    }
});

test('carga el mapa al acercarse al area visible y evita inicializarlo dos veces', () => {
    const ui = setup([location], { lazy: true });
    assert.equal(ui.maps.length, 0);
    assert.equal(ui.observed.length, 1);
    ui.reveal(0, false);
    assert.equal(ui.maps.length, 0);
    ui.reveal(0);
    ui.reveal(0);
    assert.equal(ui.maps.length, 1);
    assert.ok(ui.removed.includes(ui.elements[0]));
});

test('un error de teselas se informa solo en el mapa afectado', () => {
    const ui = setup([location, { ...location, rbd: '9502' }]);
    ui.tiles[0].listeners.tileerror();
    assert.equal(ui.elements[0].notice.hidden, false);
    assert.equal(ui.elements[1].notice.hidden, true);
    assert.equal(ui.maps.length, 2);
});

test('el fallo de inicializacion muestra una alternativa sin propagar excepciones', () => {
    const ui = setup([location], { fail: true });
    assert.equal(ui.elements[0].notice.hidden, false);
    assert.equal(ui.markers.length, 0);
});
