import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const root = document.querySelector('#votaciones-publicas');

if (root) {
    const initialNode = document.querySelector('#votaciones-estado-inicial');
    let state = JSON.parse(initialNode?.textContent || '{}');
    let roadRouting = { grupos: [] };
    let routingLoaded = false;
    const map = L.map('vp-map', { scrollWheelZoom: false }).setView([-37.03, -73.08], 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; OpenStreetMap' }).addTo(map);
    const layers = L.layerGroup().addTo(map);
    const colors = ['#0d6efd', '#dc3545', '#198754', '#6f42c1', '#fd7e14', '#0dcaf0'];
    const escapeHtml = (value = '') => String(value).replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
    const normalize = (value = '') => String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
    const formatKm = (meters) => `${new Intl.NumberFormat('es-CL', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(Number(meters) / 1000)} km`;
    const formatDuration = (seconds) => {
        const minutes = Math.max(1, Math.round(Number(seconds) / 60));
        return minutes < 60 ? `${minutes} min` : `${Math.floor(minutes / 60)} h ${minutes % 60} min`;
    };
    const labels = { pendiente: 'Pendiente', en_traslado: 'En traslado', en_votacion: 'En votación', finalizada: 'Finalizada', finalizado: 'Finalizado', suspendida: 'Suspendida', suspendido: 'Suspendido', publicada: 'Publicada', en_curso: 'En curso' };
    const selectors = { commune: root.querySelector('[data-vp-commune]'), group: root.querySelector('[data-vp-group]'), status: root.querySelector('[data-vp-state]'), search: root.querySelector('[data-vp-search]') };
    const validCoordinates = (route) => route.coordenadas_validas === true
        && Number.isFinite(route.latitud) && route.latitud >= -90 && route.latitud <= 90
        && Number.isFinite(route.longitud) && route.longitud >= -180 && route.longitud <= 180;
    const roadByGroup = () => new Map((roadRouting.grupos || []).map((group) => [String(group.grupo_id), group]));

    const filteredRoutes = () => state.grupos.flatMap((group) => group.rutas.map((route) => ({ ...route, group }))).filter(({ group, ...route }) => {
        return (! selectors.commune.value || route.comuna === selectors.commune.value)
            && (! selectors.group.value || String(group.id) === selectors.group.value)
            && (! selectors.status.value || route.estado === selectors.status.value)
            && (! selectors.search.value || normalize(`${route.nombre} ${route.rbd} ${route.comuna || ''}`).includes(normalize(selectors.search.value)));
    });

    const incomingSegment = (groupId, routeId) => roadByGroup().get(String(groupId))?.tramos?.find((segment) => String(segment.hasta_ruta_id) === String(routeId));

    const groupProgress = (group) => {
        const ordered = [...group.rutas].sort((a, b) => a.orden - b.orden);
        const current = ordered.find((route) => route.estado === 'en_votacion');
        const destination = ordered.find((route) => route.estado === 'en_traslado');
        const next = destination || ordered.find((route) => route.estado !== 'finalizada' && (! current || route.orden > current.orden));

        return { ordered, current, next };
    };

    const renderSearchResults = (routes) => {
        const panel = root.querySelector('[data-vp-search-results]');
        const term = selectors.search.value.trim();
        panel.hidden = term === '';
        if (term === '') return;

        if (! routes.length) {
            panel.innerHTML = `<h2>Resultado de búsqueda</h2><div class="vp-empty">No se encontró un establecimiento para “${escapeHtml(term)}”.</div>`;
            return;
        }

        panel.innerHTML = `<h2>Estado del establecimiento</h2>${routes.slice(0, 10).map(({ group, ...route }) => {
            const progress = groupProgress(group);
            const position = progress.ordered.findIndex((candidate) => candidate.id === route.id) + 1;
            const pendingBefore = progress.ordered.filter((candidate) => candidate.orden < route.orden && candidate.estado !== 'finalizada').length;
            const currentText = progress.current?.nombre || (progress.next ? 'Grupo en traslado' : (group.estado === 'finalizado' ? 'Recorrido completado' : 'Aún no iniciado'));
            const nextText = progress.next?.nombre || 'Sin establecimientos pendientes';
            const segment = incomingSegment(group.id, route.id);
            const distance = segment && Number.isFinite(segment.distancia_m) ? ` · ${formatKm(segment.distancia_m)} desde la parada anterior` : '';

            return `<article class="vp-search-result"><div><small>${escapeHtml(group.nombre)} · ${escapeHtml(route.comuna || '')}</small><h3>${escapeHtml(route.nombre)}</h3><p>RBD ${escapeHtml(route.rbd)} · <strong>${labels[route.estado] || escapeHtml(route.estado)}</strong>${distance}</p></div><dl><dt>Posición en ruta</dt><dd>${position} de ${progress.ordered.length}</dd></dl><dl><dt>Atención actual</dt><dd>${escapeHtml(currentText)}</dd></dl><dl><dt>Próximo destino</dt><dd>${escapeHtml(nextText)}</dd><small>${pendingBefore} pendiente(s) antes de este establecimiento</small></dl></article>`;
        }).join('')}`;
    };

    const fillFilters = () => {
        const communeValue = selectors.commune.value;
        const groupValue = selectors.group.value;
        const communes = [...new Set(state.grupos.flatMap((group) => group.rutas.map((route) => route.comuna)).filter(Boolean))].sort();
        selectors.commune.innerHTML = '<option value="">Todas</option>' + communes.map((commune) => `<option value="${escapeHtml(commune)}">${escapeHtml(commune)}</option>`).join('');
        selectors.group.innerHTML = '<option value="">Todos</option>' + state.grupos.map((group) => `<option value="${group.id}">${escapeHtml(group.nombre)}</option>`).join('');
        selectors.commune.value = communeValue;
        selectors.group.value = groupValue;
    };

    const renderDistanceSummary = () => {
        const container = root.querySelector('[data-vp-distance-summary]');
        if (! routingLoaded) {
            container.innerHTML = '<span>Calculando distancias por carretera...</span>';
            return;
        }

        const selectedGroup = selectors.group.value;
        const groups = state.grupos.filter((group) => ! selectedGroup || String(group.id) === selectedGroup);
        const roads = roadByGroup();
        container.innerHTML = groups.map((group) => {
            const road = roads.get(String(group.id));
            if (road?.disponible && Number.isFinite(road.distancia_m)) {
                return `<div><strong>${escapeHtml(group.nombre)}</strong><b>${formatKm(road.distancia_m)}</b><small>${formatDuration(road.duracion_s)} estimados</small></div>`;
            }
            return `<div class="is-fallback"><strong>${escapeHtml(group.nombre)}</strong><b>${group.rutas.length < 2 ? 'Sin tramos' : 'Distancia no disponible'}</b><small>Se muestra una referencia directa</small></div>`;
        }).join('');
    };

    const directSegments = (routes) => routes.slice(0, -1).map((route, index) => ({
        desde_ruta_id: route.id,
        hasta_ruta_id: routes[index + 1].id,
        distancia_m: null,
        tipo: 'linea_directa',
        trazado: [[route.latitud, route.longitud], [routes[index + 1].latitud, routes[index + 1].longitud]],
    }));

    const render = (refreshFilters = false) => {
        if (refreshFilters) fillFilters();
        root.querySelector('[data-vp-title]').textContent = state.jornada.nombre;
        const date = new Date(`${state.jornada.fecha}T12:00:00`);
        root.querySelector('[data-vp-meta]').textContent = `${date.toLocaleDateString('es-CL')} · ${state.jornada.procesos.map((process) => process.nombre).join(' · ')}`;
        root.querySelector('[data-vp-description]').textContent = state.jornada.descripcion || state.jornada.procesos.map((process) => process.nombre).join(' · ');
        root.querySelector('[data-vp-status]').innerHTML = `<span class="vp-badge vp-badge--${escapeHtml(state.jornada.estado)}">${labels[state.jornada.estado] || state.jornada.estado}</span>`;
        root.querySelector('[data-vp-updated]').textContent = `Actualizado ${new Date(state.actualizado_at).toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`;
        const incidents = root.querySelector('[data-vp-incidents]');
        incidents.hidden = ! state.incidencias.length;
        incidents.innerHTML = state.incidencias.length ? `<strong>Incidencias informadas:</strong> ${state.incidencias.map((incident) => escapeHtml(incident.mensaje)).join(' · ')}` : '';
        const allRoutes = state.grupos.flatMap((group) => group.rutas);
        const metrics = [['Grupos totales', state.grupos.length], ['En votación', state.grupos.filter((group) => group.estado === 'en_votacion').length], ['En traslado', state.grupos.filter((group) => group.estado === 'en_traslado').length], ['Grupos finalizados', state.grupos.filter((group) => group.estado === 'finalizado').length], ['Establecimientos atendidos', allRoutes.filter((route) => route.estado === 'finalizada').length], ['Establecimientos pendientes', allRoutes.filter((route) => route.estado !== 'finalizada').length]];
        root.querySelector('[data-vp-summary]').innerHTML = metrics.map(([label, value]) => `<div><strong>${value}</strong><span>${label}</span></div>`).join('');
        const routes = filteredRoutes();
        renderSearchResults(routes);
        renderDistanceSummary();
        root.querySelector('[data-vp-routes]').innerHTML = routes.length ? routes.map(({ group, ...route }) => {
            const segment = incomingSegment(group.id, route.id);
            const roadDetail = segment && Number.isFinite(segment.distancia_m)
                ? `<small class="vp-route-distance">Desde parada anterior: ${formatKm(segment.distancia_m)} · ${formatDuration(segment.duracion_s)}</small>`
                : '';
            return `<article class="vp-route vp-route--${route.estado}"><div class="vp-route__number">${route.orden}</div><div><small>${escapeHtml(group.nombre)} · ${escapeHtml(route.comuna || '')}</small><h3>${escapeHtml(route.nombre)}</h3><p>RBD ${escapeHtml(route.rbd)} · <strong>${labels[route.estado] || route.estado}</strong>${route.inicio_votacion ? ` · Inicio ${escapeHtml(route.inicio_votacion)}` : ''}${route.fin_votacion ? ` · Término ${escapeHtml(route.fin_votacion)}` : ''}</p>${roadDetail}${route.coordenadas_validas ? '' : '<small class="vp-coordinate-warning">Disponible en el listado, sin ubicación en el mapa.</small>'}</div></article>`;
        }).join('') : '<div class="vp-empty">No hay establecimientos para estos filtros.</div>';

        layers.clearLayers();
        const bounds = [];
        const visibleIds = new Set(routes.map((route) => String(route.id)));
        const roads = roadByGroup();
        state.grupos.forEach((group, index) => {
            const validRoutes = group.rutas.filter((route) => validCoordinates(route));
            const road = roads.get(String(group.id));
            const segments = road?.tramos?.length ? road.tramos : directSegments(validRoutes);
            segments.filter((segment) => visibleIds.has(String(segment.desde_ruta_id)) && visibleIds.has(String(segment.hasta_ruta_id))).forEach((segment) => {
                if (! Array.isArray(segment.trazado) || segment.trazado.length < 2) return;
                const fromRoute = group.rutas.find((route) => String(route.id) === String(segment.desde_ruta_id));
                const completed = fromRoute?.estado === 'finalizada';
                const line = L.polyline(segment.trazado, {
                    color: completed ? '#198754' : colors[index % colors.length],
                    weight: completed ? 5 : 4,
                    opacity: segment.tipo === 'vial' ? .85 : .6,
                    dashArray: segment.tipo === 'vial' ? null : '8 8',
                }).addTo(layers);
                if (Number.isFinite(segment.distancia_m)) line.bindTooltip(formatKm(segment.distancia_m), { sticky: true });
            });
            validRoutes.filter((route) => visibleIds.has(String(route.id))).forEach((route) => {
                const point = [route.latitud, route.longitud];
                bounds.push(point);
                const logo = route.logo_url ? `<img class="vp-popup-logo" src="${escapeHtml(route.logo_url)}" alt="">` : '';
                const next = group.rutas.find((candidate) => candidate.orden > route.orden && candidate.estado !== 'finalizada');
                const segment = incomingSegment(group.id, route.id);
                const roadDetail = segment && Number.isFinite(segment.distancia_m) ? `<br>Desde parada anterior: ${formatKm(segment.distancia_m)}` : '';
                const symbol = route.estado === 'finalizada' ? '✓' : (route.estado === 'en_votacion' ? '▶' : (route.estado === 'en_traslado' ? '→' : route.orden));
                const icon = L.divIcon({ className: `vp-map-marker vp-map-marker--${route.estado}`, html: `<span>${symbol}</span>`, iconSize: [34, 34], iconAnchor: [17, 17] });
                L.marker(point, { icon }).bindPopup(`${logo}<strong>${escapeHtml(route.nombre)}</strong><br>${escapeHtml(route.comuna || '')}<br>${escapeHtml(group.nombre)}<br>Estado: ${labels[route.estado] || route.estado}${route.inicio_votacion ? `<br>Inicio: ${escapeHtml(route.inicio_votacion)}` : ''}${roadDetail}${next ? `<br><br>Próximo establecimiento:<br>${escapeHtml(next.nombre)}` : ''}`).addTo(layers);
            });
        });
        if (bounds.length) map.fitBounds(bounds, { padding: [30, 30], maxZoom: 14 });
    };

    Object.values(selectors).forEach((control) => control.addEventListener(control.type === 'search' ? 'input' : 'change', () => render(false)));

    const loadRoadRoutes = async () => {
        try {
            const response = await fetch(root.dataset.routingUrl, { headers: { Accept: 'application/json' } });
            if (! response.ok) throw new Error('No fue posible calcular las rutas');
            roadRouting = await response.json();
        } catch (error) {
            roadRouting = { grupos: [] };
        } finally {
            routingLoaded = true;
            render(false);
        }
    };

    const refresh = async () => {
        if (document.hidden) return;
        try {
            const response = await fetch(root.dataset.stateUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (! response.ok) throw new Error('No fue posible actualizar');
            state = await response.json();
            root.querySelector('[data-vp-error]').hidden = true;
            render(true);
        } catch (error) {
            const alert = root.querySelector('[data-vp-error]');
            alert.hidden = false;
            alert.textContent = 'No se pudo actualizar en este momento. Se conserva la última información disponible.';
        }
    };

    render(true);
    loadRoadRoutes();
    setInterval(refresh, Number(root.dataset.pollingMs) || 10000);
    document.addEventListener('visibilitychange', () => { if (! document.hidden) refresh(); });
}
