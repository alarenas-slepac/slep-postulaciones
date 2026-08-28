import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import '../css/votaciones-admin-rutas.css';

const root = document.querySelector('[data-votaciones-admin-jornada]');

if (root) {
    const escapeHtml = (value = '') => String(value).replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
    const normalize = (value = '') => String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
    const formatKm = (meters) => `${new Intl.NumberFormat('es-CL', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(Number(meters) / 1000)} km`;
    const formatDuration = (seconds) => {
        const minutes = Math.max(1, Math.round(Number(seconds) / 60));
        return minutes < 60 ? `${minutes} min` : `${Math.floor(minutes / 60)} h ${minutes % 60} min`;
    };

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

        const layers = L.layerGroup().addTo(map);
        const colors = ['#0b3d91', '#8a1c1c', '#0f766e', '#6b21a8', '#9a5b00', '#055160'];
        const routingStatus = root.querySelector('[data-votaciones-admin-routing-status]');
        const distanceSummary = root.querySelector('[data-votaciones-admin-distance-summary]');
        const validRoutesCount = groups.reduce((total, group) => total + group.rutas.filter((route) => route.coordenadas_validas).length, 0);
        const invalidCoordinates = groups.reduce((total, group) => total + group.rutas.filter((route) => ! route.coordenadas_validas).length, 0);

        const coordinateStatus = root.querySelector('[data-votaciones-admin-map-status]');
        if (coordinateStatus) {
            coordinateStatus.textContent = invalidCoordinates
                ? `${invalidCoordinates} establecimiento(s) no se muestran porque no tienen coordenadas válidas.`
                : 'Todos los establecimientos de las rutas tienen coordenadas válidas.';
        }

        const directSegments = (routes) => routes.slice(0, -1).map((route, index) => ({
            desde_ruta_id: route.id,
            hasta_ruta_id: routes[index + 1].id,
            distancia_m: null,
            duracion_s: null,
            tipo: 'linea_directa',
            trazado: [
                [route.latitud, route.longitud],
                [routes[index + 1].latitud, routes[index + 1].longitud],
            ],
        }));

        const render = (roadGroups = []) => {
            layers.clearLayers();
            const roadByGroup = new Map(roadGroups.map((group) => [String(group.grupo_id), group]));
            const bounds = [];

            groups.forEach((group, index) => {
                const color = colors[index % colors.length];
                const routes = group.rutas.filter((route) => route.coordenadas_validas);
                const roadGroup = roadByGroup.get(String(group.id));
                const segments = roadGroup?.tramos?.length ? roadGroup.tramos : directSegments(routes);
                const incoming = new Map((roadGroup?.tramos || []).map((segment) => [String(segment.hasta_ruta_id), segment]));

                segments.forEach((segment) => {
                    if (! Array.isArray(segment.trazado) || segment.trazado.length < 2) return;
                    const line = L.polyline(segment.trazado, {
                        color,
                        weight: segment.tipo === 'vial' ? 5 : 4,
                        opacity: segment.tipo === 'vial' ? .82 : .58,
                        dashArray: segment.tipo === 'vial' ? null : '8 6',
                    }).addTo(layers);
                    if (Number.isFinite(segment.distancia_m)) {
                        line.bindTooltip(`${escapeHtml(group.nombre)} · ${formatKm(segment.distancia_m)}`, { sticky: true });
                    }
                });

                routes.forEach((route) => {
                    const point = [route.latitud, route.longitud];
                    bounds.push(point);
                    const segment = incoming.get(String(route.id));
                    const roadDetail = segment && Number.isFinite(segment.distancia_m)
                        ? `<br><strong>Desde parada anterior:</strong> ${formatKm(segment.distancia_m)} · ${formatDuration(segment.duracion_s)}`
                        : '';
                    const icon = L.divIcon({
                        className: 'votaciones-admin-marker',
                        html: `<span style="--votaciones-marker-color:${color}">${route.orden}</span>`,
                        iconSize: [32, 32],
                        iconAnchor: [16, 16],
                    });
                    L.marker(point, { icon })
                        .bindPopup(`<strong>${escapeHtml(group.nombre)} · parada ${route.orden}</strong><br>${escapeHtml(route.nombre)}<br>RBD ${escapeHtml(route.rbd)} · ${escapeHtml(route.comuna || '')}${roadDetail}`)
                        .addTo(layers);
                });
            });

            if (bounds.length) map.fitBounds(bounds, { padding: [28, 28], maxZoom: 14 });

            if (distanceSummary) {
                distanceSummary.innerHTML = groups.map((group) => {
                    const roadGroup = roadByGroup.get(String(group.id));
                    if (roadGroup?.disponible && Number.isFinite(roadGroup.distancia_m)) {
                        return `<div><span style="--votaciones-route-color:${colors[groups.indexOf(group) % colors.length]}"></span><strong>${escapeHtml(group.nombre)}</strong><b>${formatKm(roadGroup.distancia_m)}</b><small>${formatDuration(roadGroup.duracion_s)}</small></div>`;
                    }
                    const routeCount = group.rutas.filter((route) => route.coordenadas_validas).length;
                    return `<div class="is-fallback"><span style="--votaciones-route-color:${colors[groups.indexOf(group) % colors.length]}"></span><strong>${escapeHtml(group.nombre)}</strong><b>${routeCount < 2 ? 'Sin tramos' : 'Sin estimación vial'}</b></div>`;
                }).join('');
            }
        };

        render();

        const loadRoadRoutes = async () => {
            if (! root.dataset.routingUrl || validRoutesCount < 2) {
                if (routingStatus) routingStatus.textContent = 'No hay suficientes establecimientos para calcular recorridos.';
                return;
            }
            try {
                const response = await fetch(root.dataset.routingUrl, { headers: { Accept: 'application/json' } });
                if (! response.ok) throw new Error('No fue posible consultar las rutas');
                const payload = await response.json();
                const roadGroups = Array.isArray(payload.grupos) ? payload.grupos : [];
                render(roadGroups);
                const available = roadGroups.filter((group) => group.disponible).length;
                if (routingStatus) {
                    routingStatus.textContent = available
                        ? `Recorridos estimados por carretera para ${available} grupo(s). Las distancias no consideran tráfico en tiempo real.`
                        : 'No fue posible calcular recorridos viales; se muestran líneas directas como referencia.';
                }
            } catch (error) {
                if (routingStatus) routingStatus.textContent = 'No fue posible calcular recorridos viales; se muestran líneas directas como referencia.';
            }
        };

        loadRoadRoutes();
    }
}
