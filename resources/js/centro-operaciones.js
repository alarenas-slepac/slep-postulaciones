import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster';
import 'leaflet.markercluster/dist/MarkerCluster.css';

const formatNumber = (value) => new Intl.NumberFormat('es-CL').format(Number(value || 0));
const formatPercent = (value) => new Intl.NumberFormat('es-CL', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(Number(value || 0));
const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[char]);
const normalizeCommune = (value) => String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim()
    .toLocaleLowerCase('es-CL');
const communeAliases = new Map([
    [normalizeCommune('SAN PEDRO'), normalizeCommune('San Pedro de la Paz')],
    [normalizeCommune('STA. JUANA'), normalizeCommune('Santa Juana')],
]);
const communeKey = (value) => {
    const normalized = normalizeCommune(value);
    return communeAliases.get(normalized) ?? normalized;
};
const communeMapFallbacks = new Map([
    [normalizeCommune('Lota'), { center: [-37.0894, -73.1579], zoom: 12 }],
    [normalizeCommune('Coronel'), { center: [-37.0164, -73.1335], zoom: 12 }],
    [normalizeCommune('San Pedro de la Paz'), { center: [-36.8403, -73.1031], zoom: 12 }],
    [normalizeCommune('Santa Juana'), { center: [-37.1742, -72.9428], zoom: 12 }],
]);
const stateLabel = { operativo: 'Operativo', alerta: 'Alerta', critico: 'Crítico', sin_reporte: 'Sin reporte' };
const statePriority = { sin_reporte: 0, operativo: 1, alerta: 2, critico: 3 };

const markerIcon = (state) => {
    const size = state === 'critico' ? 20 : 17;

    return L.divIcon({
        className: `co-map-marker co-map-marker--${state}`,
        html: '<span></span>',
        iconSize: L.point(size, size),
        iconAnchor: L.point(size / 2, size / 2),
        popupAnchor: L.point(0, -(size / 2)),
    });
};

const clusterIcon = (cluster) => {
    const markers = cluster.getAllChildMarkers();
    const state = markers.reduce((selected, marker) => {
        const candidate = marker.options.coState || 'sin_reporte';
        return statePriority[candidate] > statePriority[selected] ? candidate : selected;
    }, 'sin_reporte');

    return L.divIcon({
        className: `co-marker-cluster co-marker-cluster--${state}`,
        html: `<span>${markers.length}</span>`,
        iconSize: L.point(36, 36),
        iconAnchor: L.point(18, 18),
    });
};

function initReportForm() {
    document.querySelectorAll('[data-attendance]').forEach((input) => {
        const result = input.closest('.co-attendance-card')?.querySelector('[data-attendance-result]');
        const refresh = () => {
            const total = Number(input.dataset.total || 0);
            const present = Number(input.value || 0);
            if (result) result.textContent = total > 0 && input.value !== '' ? `${formatPercent((present / total) * 100)}%` : '—';
        };
        input.addEventListener('input', refresh);
        refresh();
    });

    document.querySelectorAll('[data-incident-toggle]').forEach((checkbox) => {
        const code = checkbox.dataset.incidentToggle;
        const detail = document.querySelector(`[data-incident-detail="${code}"]`);
        const refresh = () => {
            if (!detail) return;
            detail.disabled = !checkbox.checked;
            detail.required = checkbox.checked;
        };
        checkbox.addEventListener('change', refresh);
        refresh();
    });
}

function initPanel(root) {
    if (root.dataset.tv === '1') document.body.classList.add('co-tv-mode');
    const raw = document.getElementById('co-dashboard-data');
    let data = raw ? JSON.parse(raw.textContent) : null;
    const mapElement = document.getElementById('co-map');
    const mapCommuneButtons = Array.from(root.querySelectorAll('[data-map-commune]'));
    let activeMapCommune = '';
    let territoryMapPoints = [];
    let mapPointsByCommune = new Map();
    let map;
    let markerLayer;

    if (mapElement) {
        map = L.map(mapElement, { zoomControl: true, attributionControl: true }).setView([-37.02, -73.1], 10);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; OpenStreetMap',
        }).addTo(map);
        markerLayer = L.markerClusterGroup({
            showCoverageOnHover: false,
            zoomToBoundsOnClick: true,
            spiderfyOnMaxZoom: true,
            disableClusteringAtZoom: 15,
            maxClusterRadius: 8,
            iconCreateFunction: clusterIcon,
        }).addTo(map);
    }

    const focusMapPoints = (points, maxZoom) => {
        if (!map || !points.length) return;
        if (points.length === 1) {
            map.setView(points[0], maxZoom);
            return;
        }
        map.fitBounds(points, { padding: [35, 35], maxZoom });
    };

    const refreshMapCommuneButtons = () => {
        mapCommuneButtons.forEach((button) => {
            const commune = communeKey(button.dataset.mapCommune);
            const active = commune === activeMapCommune;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
            button.disabled = commune !== ''
                && !mapPointsByCommune.has(commune)
                && !communeMapFallbacks.has(commune);
        });
    };

    const focusMapCommune = (commune) => {
        activeMapCommune = communeKey(commune);
        refreshMapCommuneButtons();
        const points = activeMapCommune
            ? (mapPointsByCommune.get(activeMapCommune) ?? [])
            : territoryMapPoints;
        const fallback = communeMapFallbacks.get(activeMapCommune);
        if (points.length) {
            focusMapPoints(points, activeMapCommune ? 14 : 12);
        } else if (fallback && map) {
            map.setView(fallback.center, fallback.zoom);
        }
    };

    mapCommuneButtons.forEach((button) => {
        button.addEventListener('click', () => focusMapCommune(button.dataset.mapCommune));
    });

    const renderMap = (payload) => {
        if (!map || !markerLayer) return;
        markerLayer.clearLayers();
        mapPointsByCommune = new Map();
        const bounds = [];
        payload.establecimientos.forEach((item) => {
            if (!Number.isFinite(item.latitud) || !Number.isFinite(item.longitud)) return;
            const point = [item.latitud, item.longitud];
            bounds.push(point);
            const commune = communeKey(item.comuna);
            if (!mapPointsByCommune.has(commune)) mapPointsByCommune.set(commune, []);
            mapPointsByCommune.get(commune).push(point);
            const reportUrl = item.reporte_id ? root.dataset.reportUrl.replace('__ID__', item.reporte_id) : '';
            const reportLink = reportUrl ? `<a href="${escapeHtml(reportUrl)}">Ver reporte</a>` : '';
            const logo = item.logo_url
                ? `<div class="co-leaflet-popup-logo"><img src="${escapeHtml(item.logo_url)}" alt="Logo de ${escapeHtml(item.nombre)}"></div>`
                : '<div class="co-leaflet-popup-logo co-leaflet-popup-logo--fallback"><i class="bi bi-building" aria-hidden="true"></i></div>';
            L.marker(point, {
                icon: markerIcon(item.estado),
                coState: item.estado,
                title: `${item.nombre} · ${stateLabel[item.estado]}`,
            }).bindPopup(`<div class="co-leaflet-popup"><div class="co-leaflet-popup-header">${logo}<div class="co-leaflet-popup-copy"><strong>${escapeHtml(item.nombre)}</strong><span>${escapeHtml(item.comuna)} · ${escapeHtml(stateLabel[item.estado] ?? item.estado)}</span></div></div>${reportLink}</div>`).addTo(markerLayer);
        });
        territoryMapPoints = bounds;
        if (activeMapCommune
            && !mapPointsByCommune.has(activeMapCommune)
            && !communeMapFallbacks.has(activeMapCommune)) activeMapCommune = '';
        refreshMapCommuneButtons();
        focusMapCommune(activeMapCommune);
    };

    const renderMetrics = (payload) => {
        Object.entries(payload.metricas).forEach(([key, value]) => {
            document.querySelectorAll(`[data-metric="${key}"]`).forEach((node) => {
                node.textContent = ['cobertura_reportes', 'asistencia_estudiantes', 'asistencia_funcionarios'].includes(key) ? formatPercent(value) : formatNumber(value);
            });
        });
        const updated = document.querySelector('[data-co-updated]');
        if (updated) updated.textContent = new Intl.DateTimeFormat('es-CL', { hour: '2-digit', minute: '2-digit' }).format(new Date(payload.actualizado_en));
    };

    const renderCommunes = (payload) => {
        const target = document.querySelector('[data-co-communes]');
        if (!target) return;
        target.innerHTML = payload.comunas.map((item) => `<tr><td class="fw-semibold">${escapeHtml(item.comuna)}</td><td>${item.establecimientos}</td><td class="co-col-reportados">${item.reportados}</td><td><span class="co-dot co-dot--operativo"></span>${item.operativos}</td><td><span class="co-dot co-dot--alerta"></span>${item.alertas}</td><td><span class="co-dot co-dot--critico"></span>${item.criticos}</td><td><span class="co-dot co-dot--sin_reporte"></span>${item.sin_reporte}</td><td>${formatPercent(item.asistencia)}%</td></tr>`).join('');
    };

    const renderServices = (payload) => {
        const target = document.querySelector('[data-co-services]');
        if (!target) return;
        target.innerHTML = payload.servicios.map((item) => `<div class="co-service-row co-service--${escapeHtml(item.codigo)}"><div class="co-service-label"><i class="bi ${escapeHtml(item.icon)}"></i><span>${escapeHtml(item.label)}</span></div><div class="co-progress"><span style="width:${Number(item.porcentaje_operativo)}%"></span></div><strong>${formatPercent(item.porcentaje_operativo)}%</strong></div>`).join('');
    };

    const renderAlerts = (payload) => {
        const target = document.querySelector('[data-co-alerts]');
        if (!target) return;
        target.innerHTML = payload.alertas.length ? payload.alertas.slice(0, 7).map((item) => {
            const href = item.reporte_id ? root.dataset.reportUrl.replace('__ID__', item.reporte_id) : '#';
            return `<a href="${escapeHtml(href)}" class="co-list-item"><span class="co-status-bar co-status-bar--${item.estado}"></span><span><strong>${escapeHtml(item.nombre)}</strong><small>${escapeHtml(item.comuna)} · ${stateLabel[item.estado]}</small></span><i class="bi bi-chevron-right"></i></a>`;
        }).join('') : '<div class="co-empty"><i class="bi bi-check-circle"></i>No hay establecimientos en alerta o estado crítico.</div>';
    };

    const renderIncidents = (payload) => {
        const target = document.querySelector('[data-co-incidents]');
        if (!target) return;
        target.innerHTML = payload.incidencias_activas.length ? payload.incidencias_activas.slice(0, 7).map((item) => `<div class="co-list-item"><span class="co-status-bar co-status-bar--${item.severidad}"></span><span><strong>${escapeHtml(item.label)}</strong><small>${escapeHtml(item.establecimiento)} · ${escapeHtml(item.comuna)}</small></span></div>`).join('') : '<div class="co-empty"><i class="bi bi-shield-check"></i>No existen incidencias activas.</div>';
    };

    const render = (payload) => { data = payload; renderMetrics(payload); renderCommunes(payload); renderServices(payload); renderAlerts(payload); renderIncidents(payload); renderMap(payload); };
    if (data) render(data);

    window.setInterval(async () => {
        if (document.visibilityState === 'hidden') return;
        try {
            const response = await fetch(root.dataset.url, { headers: { Accept: 'application/json' } });
            if (response.ok) render(await response.json());
        } catch (_) {
            // Se conserva la última información visible si la conexión se interrumpe.
        }
    }, 60000);
}

document.addEventListener('DOMContentLoaded', () => {
    initReportForm();
    const panel = document.querySelector('[data-co-panel]');
    if (panel) initPanel(panel);
});
