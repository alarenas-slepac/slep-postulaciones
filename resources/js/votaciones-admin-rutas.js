import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import '../css/votaciones-admin-rutas.css';

const root = document.querySelector('[data-votaciones-admin-jornada]');

if (root) {
    const escapeHtml = (value = '') => String(value).replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
    const normalize = (value = '') => String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();

    root.querySelectorAll('[data-votacion-establecimiento-search]').forEach((search) => {
        const form = search.closest('form');
        const select = form?.querySelector('[data-votacion-establecimiento-select]');
        if (! select) return;

        search.addEventListener('input', () => {
            const term = normalize(search.value);
            Array.from(select.options).forEach((option, index) => {
                if (index === 0) return;
                option.hidden = term !== '' && ! normalize(option.dataset.search).includes(term);
            });
        });
    });

    const mapElement = root.querySelector('[data-votaciones-admin-map]');
    const stateNode = root.querySelector('#votaciones-admin-rutas-data');
    if (mapElement && stateNode) {
        const groups = JSON.parse(stateNode.textContent || '[]');
        const map = L.map(mapElement, { scrollWheelZoom: false }).setView([-37.03, -73.08], 10);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; OpenStreetMap',
        }).addTo(map);

        const colors = ['#0b3d91', '#8a1c1c', '#0f766e', '#6b21a8', '#9a5b00', '#055160'];
        const bounds = [];
        let invalidCoordinates = 0;

        groups.forEach((group, index) => {
            const color = colors[index % colors.length];
            const validRoutes = group.rutas.filter((route) => route.coordenadas_validas);
            invalidCoordinates += group.rutas.length - validRoutes.length;
            const points = validRoutes.map((route) => [route.latitud, route.longitud]);

            if (points.length > 1) {
                L.polyline(points, { color, weight: 4, opacity: .7, dashArray: '8 6' }).addTo(map);
            }

            validRoutes.forEach((route) => {
                const point = [route.latitud, route.longitud];
                bounds.push(point);
                const icon = L.divIcon({
                    className: 'votaciones-admin-marker',
                    html: `<span style="--votaciones-marker-color:${color}">${route.orden}</span>`,
                    iconSize: [32, 32],
                    iconAnchor: [16, 16],
                });
                L.marker(point, { icon })
                    .bindPopup(`<strong>${escapeHtml(group.nombre)} · parada ${route.orden}</strong><br>${escapeHtml(route.nombre)}<br>RBD ${escapeHtml(route.rbd)} · ${escapeHtml(route.comuna || '')}`)
                    .addTo(map);
            });
        });

        if (bounds.length) {
            map.fitBounds(bounds, { padding: [28, 28], maxZoom: 14 });
        }

        const status = root.querySelector('[data-votaciones-admin-map-status]');
        if (status) {
            status.textContent = invalidCoordinates
                ? `${invalidCoordinates} establecimiento(s) no se muestran porque no tienen coordenadas válidas.`
                : 'Todos los establecimientos de las rutas tienen coordenadas válidas.';
        }
    }
}
