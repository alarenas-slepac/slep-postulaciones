import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const root = document.querySelector('#votaciones-publicas');

if (root) {
    const initialNode = document.querySelector('#votaciones-estado-inicial');
    let state = JSON.parse(initialNode?.textContent || '{}');
    let roadRouting = { grupos: [] };
    let routingLoaded = false;
    let selectedGroupId = null;
    let selectedRouteId = null;
    let initialMapFit = false;
    let previousSnapshot = '';
    const markersByRoute = new Map();

    const map = L.map('vp-map', {
        scrollWheelZoom: false,
        zoomControl: true,
        preferCanvas: true,
    }).setView([-37.03, -73.08], 10);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    const layers = L.layerGroup().addTo(map);
    const dashboard = root.querySelector('[data-vp-dashboard]');
    const selectors = {
        commune: root.querySelector('[data-vp-commune]'),
        group: root.querySelector('[data-vp-group]'),
        status: root.querySelector('[data-vp-state]'),
        search: root.querySelector('[data-vp-search]'),
    };

    const labels = {
        pendiente: 'Pendiente',
        en_traslado: 'En traslado',
        en_votacion: 'Votación en curso',
        finalizada: 'Finalizada',
        finalizado: 'Finalizado',
        suspendida: 'Suspendida',
        suspendido: 'Suspendido',
        publicada: 'Publicada',
        en_curso: 'Jornada en desarrollo',
    };

    const symbols = {
        pendiente: '○',
        en_traslado: '→',
        en_votacion: '●',
        finalizada: '✓',
        finalizado: '✓',
        suspendida: '!',
        suspendido: '!',
    };

    const icons = {
        groups: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 19c.5-4 2.2-6 5-6s4.5 2 5 6M14 14c3-.4 5.3 1.3 6 4"/></svg>',
        voting: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 9h14v11H5zM8 9l2-5h4l2 5M9 14h6"/></svg>',
        transit: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 17h16M6 17l1-7h10l1 7M8 10l1-4h6l1 4"/><circle cx="8" cy="18" r="1.5"/><circle cx="16" cy="18" r="1.5"/></svg>',
        finished: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg>',
        pending: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    };

    const escapeHtml = (value = '') => String(value).replace(/[&<>'"]/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#039;',
        '"': '&quot;',
    }[char]));

    const normalize = (value = '') => String(value)
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

    const formatKm = (meters) => `${new Intl.NumberFormat('es-CL', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    }).format(Number(meters) / 1000)} km`;

    const formatDuration = (seconds) => {
        const minutes = Math.max(1, Math.round(Number(seconds) / 60));
        return minutes < 60 ? `${minutes} min` : `${Math.floor(minutes / 60)} h ${minutes % 60} min`;
    };

    const formatGroupNumber = (group) => String(group.numero || group.id).padStart(2, '0');
    const groupName = (group) => group.nombre || `Grupo ${formatGroupNumber(group)}`;
    const statusLabel = (status) => labels[status] || String(status || 'Pendiente').replaceAll('_', ' ');
    const statusSymbol = (status) => symbols[status] || '○';

    const validCoordinates = (route) => route.coordenadas_validas === true
        && Number.isFinite(route.latitud) && route.latitud >= -90 && route.latitud <= 90
        && Number.isFinite(route.longitud) && route.longitud >= -180 && route.longitud <= 180;

    const roadByGroup = () => new Map((roadRouting.grupos || []).map((group) => [String(group.grupo_id), group]));
    const groupById = (id) => state.grupos.find((group) => String(group.id) === String(id));
    const routeById = (group, id) => group?.rutas.find((route) => String(route.id) === String(id));

    const groupProgress = (group) => {
        const ordered = [...group.rutas].sort((left, right) => left.orden - right.orden);
        const completed = ordered.filter((route) => route.estado === 'finalizada');
        const current = ordered.find((route) => route.estado === 'en_votacion');
        const destination = ordered.find((route) => route.estado === 'en_traslado');
        const next = destination || ordered.find((route) => route.estado !== 'finalizada' && (! current || route.orden > current.orden));
        const lastCompleted = [...completed].sort((left, right) => right.orden - left.orden)[0];
        const percent = ordered.length ? Math.round((completed.length / ordered.length) * 100) : 0;

        return { ordered, completed, current, destination, next, lastCompleted, percent };
    };

    const groupIncidents = (group) => state.incidencias.filter((incident) => String(incident.grupo_id) === String(group.id));
    const hasCriticalIncident = (group) => groupIncidents(group).some((incident) => incident.tipo === 'proceso_suspendido');

    const chooseInitialGroup = () => {
        const preferred = state.grupos.find((group) => ['en_votacion', 'en_traslado'].includes(group.estado)) || state.grupos[0];
        selectedGroupId = preferred?.id ?? null;
    };

    const filteredRoutes = () => state.grupos
        .flatMap((group) => group.rutas.map((route) => ({ ...route, group })))
        .filter(({ group, ...route }) => (! selectors.commune.value || route.comuna === selectors.commune.value)
            && (! selectors.group.value || String(group.id) === selectors.group.value)
            && (! selectors.status.value || route.estado === selectors.status.value)
            && (! selectors.search.value || normalize(`${route.nombre} ${route.rbd} ${route.comuna || ''}`).includes(normalize(selectors.search.value))));

    const incomingSegment = (groupId, routeId) => roadByGroup()
        .get(String(groupId))
        ?.tramos?.find((segment) => String(segment.hasta_ruta_id) === String(routeId));

    const logoMarkup = (route, className) => route?.logo_url
        ? `<span class="${className}"><img src="${escapeHtml(route.logo_url)}" alt="Logo de ${escapeHtml(route.nombre)}"></span>`
        : `<span class="${className}" aria-hidden="true">AC</span>`;

    const fillFilters = () => {
        const communeValue = selectors.commune.value;
        const groupValue = selectors.group.value;
        const communes = [...new Set(state.grupos.flatMap((group) => group.rutas.map((route) => route.comuna)).filter(Boolean))].sort();

        selectors.commune.innerHTML = '<option value="">Todas las comunas</option>'
            + communes.map((commune) => `<option value="${escapeHtml(commune)}">${escapeHtml(commune)}</option>`).join('');
        selectors.group.innerHTML = '<option value="">Todos los grupos</option>'
            + state.grupos.map((group) => `<option value="${group.id}">${escapeHtml(groupName(group))}</option>`).join('');
        selectors.commune.value = communes.includes(communeValue) ? communeValue : '';
        selectors.group.value = state.grupos.some((group) => String(group.id) === groupValue) ? groupValue : '';
    };

    const renderHeader = () => {
        const date = new Date(`${state.jornada.fecha}T12:00:00`);
        root.querySelector('[data-vp-title]').textContent = state.jornada.nombre;
        root.querySelector('[data-vp-meta]').textContent = `${date.toLocaleDateString('es-CL')} · ${state.jornada.procesos.map((process) => process.nombre).join(' · ')}`;
        root.querySelector('[data-vp-description]').textContent = state.jornada.descripcion || state.jornada.procesos.map((process) => process.nombre).join(' · ');
        root.querySelector('[data-vp-status]').innerHTML = `<span class="vp-badge vp-badge--${escapeHtml(state.jornada.estado)}"><i></i>${escapeHtml(statusLabel(state.jornada.estado))}</span>`;

        const updated = new Date(state.actualizado_at);
        const formattedTime = Number.isNaN(updated.getTime())
            ? 'Hora no disponible'
            : updated.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        root.querySelector('[data-vp-updated]').textContent = `Actualización en tiempo real · ${formattedTime}`;
        root.querySelector('[data-vp-map-updated]').textContent = `Actualizado ${formattedTime}`;
    };

    const renderAlerts = () => {
        const incidents = root.querySelector('[data-vp-incidents]');
        incidents.hidden = state.incidencias.length === 0;
        incidents.innerHTML = state.incidencias.length
            ? `<strong>Incidencias informadas:</strong> ${state.incidencias.map((incident) => `${escapeHtml(incident.mensaje)} (${escapeHtml(incident.creada_at)} hrs.)`).join(' · ')}`
            : '';
    };

    const renderSummary = () => {
        const allRoutes = state.grupos.flatMap((group) => group.rutas);
        const metrics = [
            ['groups', 'Grupos desplegados', state.grupos.length, 'groups'],
            ['voting', 'Grupos votando', state.grupos.filter((group) => group.estado === 'en_votacion').length, 'voting'],
            ['transit', 'Grupos en traslado', state.grupos.filter((group) => group.estado === 'en_traslado').length, 'transit'],
            ['finished', 'Establecimientos atendidos', allRoutes.filter((route) => route.estado === 'finalizada').length, 'finished'],
            ['pending', 'Establecimientos pendientes', allRoutes.filter((route) => route.estado !== 'finalizada').length, 'pending'],
        ];

        root.querySelector('[data-vp-summary]').innerHTML = metrics.map(([key, label, value, icon]) => `
            <article class="vp-kpi vp-kpi--${key}">
                <span class="vp-kpi__icon">${icons[icon]}</span>
                <div><strong>${value}</strong><span>${label}</span></div>
            </article>
        `).join('');
    };

    const renderSearchResults = (routes) => {
        const panel = root.querySelector('[data-vp-search-results]');
        const clearButton = root.querySelector('[data-vp-clear-search]');
        const term = selectors.search.value.trim();
        clearButton.hidden = term === '';
        panel.hidden = term === '';
        if (term === '') return;

        if (routes.length === 0) {
            panel.innerHTML = `<div class="vp-search-results__head"><h2>Resultado de búsqueda</h2></div><div class="vp-empty">No encontramos un establecimiento para “${escapeHtml(term)}”. Revisa el nombre, RBD o comuna.</div>`;
            return;
        }

        panel.innerHTML = `<div class="vp-search-results__head"><h2>¿Este es tu establecimiento?</h2><span>${routes.length} resultado(s)</span></div>${routes.slice(0, 10).map(({ group, ...route }) => {
            const progress = groupProgress(group);
            const position = progress.ordered.findIndex((candidate) => candidate.id === route.id) + 1;
            const pendingBefore = progress.ordered.filter((candidate) => candidate.orden < route.orden && candidate.estado !== 'finalizada').length;
            const currentText = progress.current?.nombre || (progress.destination ? `En traslado a ${progress.destination.nombre}` : (group.estado === 'finalizado' ? 'Recorrido completado' : 'Aún no iniciado'));
            const segment = incomingSegment(group.id, route.id);
            const distance = segment && Number.isFinite(segment.distancia_m) ? ` · ${formatKm(segment.distancia_m)} desde la parada anterior` : '';

            return `<button type="button" class="vp-search-result" data-vp-select-route="${route.id}" data-vp-group-id="${group.id}">
                ${logoMarkup(route, 'vp-search-result__logo')}
                <div><small>${escapeHtml(groupName(group))} · ${escapeHtml(route.comuna || 'Comuna no informada')}</small><h3>${escapeHtml(route.nombre)}</h3><p>RBD ${escapeHtml(route.rbd)} · <strong>${escapeHtml(statusLabel(route.estado))}</strong>${distance}</p></div>
                <dl><dt>Posición en ruta</dt><dd>${position} de ${progress.ordered.length}</dd></dl>
                <dl><dt>Atención actual</dt><dd>${escapeHtml(currentText)}</dd></dl>
                <dl><dt>Faltan antes</dt><dd>${pendingBefore} establecimiento(s)</dd></dl>
                <span class="vp-search-result__arrow" aria-hidden="true">›</span>
            </button>`;
        }).join('')}`;
    };

    const renderGroupCards = () => {
        const container = root.querySelector('[data-vp-group-cards]');
        if (state.grupos.length === 0) {
            container.innerHTML = '<div class="vp-empty">No existen grupos configurados para esta jornada.</div>';
            return;
        }

        container.innerHTML = state.grupos.map((group) => {
            const progress = groupProgress(group);
            const incident = groupIncidents(group).length > 0;
            const currentText = progress.current?.nombre
                || (progress.destination ? `En traslado a ${progress.destination.nombre}` : (progress.lastCompleted?.nombre || 'Recorrido aún no iniciado'));
            const stateClass = incident ? (hasCriticalIncident(group) ? 'suspendido' : 'incidencia') : group.estado;
            const stateText = incident ? 'Incidencia informada' : statusLabel(group.estado);

            return `<button type="button" class="vp-group-card${String(selectedGroupId) === String(group.id) ? ' is-selected' : ''}" data-vp-select-group="${group.id}" aria-pressed="${String(selectedGroupId) === String(group.id)}">
                <div class="vp-group-card__top"><div class="vp-group-card__name"><small>Grupo ${formatGroupNumber(group)}</small><strong>${escapeHtml(groupName(group))}</strong></div><span class="vp-badge vp-badge--${escapeHtml(stateClass)}"><i></i>${escapeHtml(stateText)}</span></div>
                <div class="vp-group-card__school"><span>${progress.current ? 'Establecimiento actual' : (progress.destination ? 'Próximo destino' : 'Último registro')}</span><strong>${escapeHtml(currentText)}</strong></div>
                <div class="vp-group-card__progress"><i style="width:${progress.percent}%"></i></div>
                <div class="vp-group-card__progress-meta"><span>${progress.completed.length} / ${progress.ordered.length} establecimientos</span><strong>${progress.percent}%</strong></div>
            </button>`;
        }).join('');
    };

    const renderRoutePanel = () => {
        const panel = root.querySelector('[data-vp-route-panel]');
        let group = groupById(selectedGroupId);
        if (! group) {
            chooseInitialGroup();
            group = groupById(selectedGroupId);
        }

        if (! group) {
            panel.innerHTML = '<div class="vp-empty">No existe un recorrido para mostrar.</div>';
            return;
        }

        const progress = groupProgress(group);
        const focusRoute = progress.current || progress.destination || progress.lastCompleted || progress.ordered[0];
        const current = progress.current || progress.destination || focusRoute;
        const next = progress.current
            ? progress.ordered.find((route) => route.orden > progress.current.orden && route.estado !== 'finalizada')
            : (progress.destination
                ? progress.ordered.find((route) => route.orden > progress.destination.orden && route.estado !== 'finalizada')
                : progress.next);
        const nextLabel = progress.destination && ! progress.current ? 'Después de esta visita' : 'Próximo establecimiento';
        const road = roadByGroup().get(String(group.id));
        const distance = road?.disponible && Number.isFinite(road.distancia_m)
            ? `${formatKm(road.distancia_m)} · ${formatDuration(road.duracion_s)}`
            : 'Estimación vial no disponible';
        const incidents = groupIncidents(group);

        panel.innerHTML = `
            <header class="vp-route-panel__head"><div><p>Grupo ${formatGroupNumber(group)}</p><h2>${escapeHtml(groupName(group))}</h2></div><span class="vp-badge vp-badge--${escapeHtml(incidents.length ? 'incidencia' : group.estado)}"><i></i>${escapeHtml(incidents.length ? 'Incidencia' : statusLabel(group.estado))}</span></header>
            <div class="vp-route-panel__body">
                <div class="vp-route-panel__primary">
                    <p class="vp-route-panel__section-label">${progress.current ? 'Establecimiento actual' : (progress.destination ? 'Destino del traslado' : 'Último establecimiento')}</p>
                    <div class="vp-current-school">${logoMarkup(current, 'vp-current-school__logo')}<div><strong>${escapeHtml(current?.nombre || 'Sin establecimiento activo')}</strong><span>${escapeHtml(current?.comuna || 'Comuna no informada')}${current?.inicio_votacion ? ` · Inicio ${escapeHtml(current.inicio_votacion)} hrs.` : ''}</span></div></div>
                    <div class="vp-next-school"><span>${nextLabel}</span><strong>${escapeHtml(next?.nombre || 'Sin establecimientos pendientes')}</strong></div>
                    <div class="vp-route-panel__metric"><span>Recorrido planificado</span><strong>${escapeHtml(distance)}</strong></div>
                    ${incidents.length ? `<div class="vp-alert vp-alert--warning"><strong>Incidencia:</strong> ${incidents.map((incident) => escapeHtml(incident.mensaje)).join(' · ')}</div>` : ''}
                </div>
                <div class="vp-route-panel__timeline">
                    <p class="vp-route-panel__section-label">Recorrido</p>
                    <ol class="vp-timeline">${progress.ordered.map((route) => {
                        const isNext = next && String(next.id) === String(route.id) && route.estado === 'pendiente';
                        const incidentClass = incidents.length && ['en_votacion', 'en_traslado'].includes(route.estado) ? ' vp-route--incidencia' : '';
                        return `<li class="vp-route vp-route--${escapeHtml(route.estado)}${isNext ? ' vp-route--next' : ''}${String(selectedRouteId) === String(route.id) ? ' vp-route--selected' : ''}${incidentClass}">
                            <span class="vp-route__marker" aria-hidden="true">${isNext ? '◇' : escapeHtml(statusSymbol(route.estado))}</span>
                            <button type="button" class="vp-route__button" data-vp-select-route="${route.id}" data-vp-group-id="${group.id}"><strong>${escapeHtml(route.nombre)}</strong><span>${escapeHtml(isNext ? 'Próximo' : statusLabel(route.estado))} · ${escapeHtml(route.comuna || '')}</span>${route.inicio_votacion ? `<small>Inicio ${escapeHtml(route.inicio_votacion)} hrs.</small>` : ''}</button>
                        </li>`;
                    }).join('')}</ol>
                </div>
            </div>`;
    };

    const renderDistanceSummary = () => {
        const container = root.querySelector('[data-vp-distance-summary]');
        if (! routingLoaded) {
            container.innerHTML = '<span class="vp-skeleton">Calculando distancias por carretera...</span>';
            return;
        }

        const selectedGroup = selectors.group.value;
        const groups = state.grupos.filter((group) => ! selectedGroup || String(group.id) === selectedGroup);
        const roads = roadByGroup();
        container.innerHTML = groups.map((group) => {
            const road = roads.get(String(group.id));
            if (road?.disponible && Number.isFinite(road.distancia_m)) {
                return `<div><strong>${escapeHtml(groupName(group))}</strong><b>${formatKm(road.distancia_m)}</b><small>${formatDuration(road.duracion_s)} estimados por carretera</small></div>`;
            }
            return `<div class="is-fallback"><strong>${escapeHtml(groupName(group))}</strong><b>${group.rutas.length < 2 ? 'Sin tramos' : 'Sin estimación'}</b><small>Referencia directa entre establecimientos</small></div>`;
        }).join('') || '<div class="is-fallback"><strong>Sin recorridos visibles</strong><small>Ajusta los filtros para consultar distancias.</small></div>';
    };

    const directSegments = (routes) => routes.slice(0, -1).map((route, index) => ({
        desde_ruta_id: route.id,
        hasta_ruta_id: routes[index + 1].id,
        distancia_m: null,
        duracion_s: null,
        tipo: 'linea_directa',
        trazado: [[route.latitud, route.longitud], [routes[index + 1].latitud, routes[index + 1].longitud]],
    }));

    const segmentStage = (fromRoute, toRoute) => {
        if (fromRoute?.estado === 'finalizada' && toRoute?.estado === 'finalizada') return 'completed';
        if (['en_traslado', 'en_votacion'].includes(fromRoute?.estado) || ['en_traslado', 'en_votacion'].includes(toRoute?.estado)) return 'current';
        return 'pending';
    };

    const segmentOptions = (stage, type) => {
        if (stage === 'completed') return { color: '#138a52', weight: 6, opacity: .9 };
        if (stage === 'current') return { color: '#0865c8', weight: 6, opacity: .92, dashArray: type === 'vial' ? null : '8 6' };
        return { color: '#8091a3', weight: 4, opacity: .62, dashArray: '7 8' };
    };

    const popupMarkup = (group, route, next, incident) => {
        const segment = incomingSegment(group.id, route.id);
        const distance = segment && Number.isFinite(segment.distancia_m) ? formatKm(segment.distancia_m) : 'Primera parada';
        const incidentLabel = incident ? 'Incidencia informada' : statusLabel(route.estado);
        return `<article class="vp-popup">
            <div class="vp-popup__head">${logoMarkup(route, 'vp-popup__logo')}<div><small>Grupo ${formatGroupNumber(group)}</small><strong>${escapeHtml(route.nombre)}</strong></div></div>
            <div class="vp-popup__body"><span class="vp-popup__status"><i></i>${escapeHtml(incidentLabel)}</span><dl><div><dt>Inicio</dt><dd>${escapeHtml(route.inicio_votacion ? `${route.inicio_votacion} hrs.` : 'No registrado')}</dd></div><div><dt>Desde parada anterior</dt><dd>${escapeHtml(distance)}</dd></div></dl><div class="vp-popup__next">Próximo establecimiento<strong>${escapeHtml(next?.nombre || 'Fin del recorrido')}</strong></div></div>
        </article>`;
    };

    const renderMap = (fitBounds = false) => {
        layers.clearLayers();
        markersByRoute.clear();
        const bounds = [];
        const routes = filteredRoutes();
        const visibleIds = new Set(routes.map((route) => String(route.id)));
        const searchedGroupIds = new Set(routes.map((route) => String(route.group.id)));
        const hasSearch = selectors.search.value.trim() !== '';
        const roads = roadByGroup();

        state.grupos.forEach((group) => {
            const validRoutes = group.rutas.filter((route) => validCoordinates(route));
            const road = roads.get(String(group.id));
            const segments = road?.tramos?.length ? road.tramos : directSegments(validRoutes);
            const showGroupContext = hasSearch && searchedGroupIds.has(String(group.id));

            segments.filter((segment) => showGroupContext || (visibleIds.has(String(segment.desde_ruta_id)) && visibleIds.has(String(segment.hasta_ruta_id)))).forEach((segment) => {
                if (! Array.isArray(segment.trazado) || segment.trazado.length < 2) return;
                const fromRoute = routeById(group, segment.desde_ruta_id);
                const toRoute = routeById(group, segment.hasta_ruta_id);
                const stage = segmentStage(fromRoute, toRoute);
                const line = L.polyline(segment.trazado, segmentOptions(stage, segment.tipo)).addTo(layers);
                const stageLabel = stage === 'completed' ? 'Tramo completado' : (stage === 'current' ? 'Tramo actual' : 'Tramo pendiente');
                line.bindTooltip(`${escapeHtml(groupName(group))} · ${stageLabel}${Number.isFinite(segment.distancia_m) ? ` · ${formatKm(segment.distancia_m)}` : ''}`, { sticky: true });
            });

            validRoutes.filter((route) => showGroupContext || visibleIds.has(String(route.id))).forEach((route) => {
                const point = [route.latitud, route.longitud];
                const routeIndex = group.rutas.findIndex((candidate) => candidate.id === route.id);
                const next = group.rutas.slice(routeIndex + 1).find((candidate) => candidate.estado !== 'finalizada');
                const incident = groupIncidents(group).length > 0 && ['en_votacion', 'en_traslado'].includes(route.estado);
                const critical = incident && hasCriticalIncident(group);
                const markerClass = critical ? 'critica' : (incident ? 'incidencia' : route.estado);
                const symbol = critical || incident ? '!' : statusSymbol(route.estado);
                const icon = L.divIcon({
                    className: `vp-map-marker vp-map-marker--${markerClass}`,
                    html: `<span><b>${escapeHtml(symbol)}</b></span>`,
                    iconSize: [38, 38],
                    iconAnchor: [19, 35],
                    popupAnchor: [0, -32],
                });
                const marker = L.marker(point, {
                    icon,
                    title: `${route.nombre}: ${incident ? 'Incidencia informada' : statusLabel(route.estado)}`,
                    keyboard: true,
                }).bindPopup(popupMarkup(group, route, next, incident), { maxWidth: 280 }).addTo(layers);

                marker.on('click', () => {
                    selectedGroupId = group.id;
                    selectedRouteId = route.id;
                    renderGroupCards();
                    renderRoutePanel();
                });
                markersByRoute.set(String(route.id), marker);
                bounds.push(point);
            });
        });

        if (bounds.length && (! initialMapFit || fitBounds)) {
            map.fitBounds(bounds, { padding: [36, 36], maxZoom: 14 });
            initialMapFit = true;
        }
        root.querySelector('[data-vp-map-loading]').hidden = true;
        requestAnimationFrame(() => map.invalidateSize({ animate: false }));
    };

    const focusRoute = (routeId) => {
        const marker = markersByRoute.get(String(routeId));
        if (! marker) return;
        map.flyTo(marker.getLatLng(), Math.max(map.getZoom(), 14), { duration: .45 });
        marker.openPopup();
    };

    const render = ({ refreshFilters = false, fitBounds = false, updated = false } = {}) => {
        if (refreshFilters) fillFilters();
        if (! groupById(selectedGroupId)) chooseInitialGroup();
        renderHeader();
        renderAlerts();
        renderSummary();
        const routes = filteredRoutes();
        renderSearchResults(routes);
        renderGroupCards();
        renderRoutePanel();
        renderDistanceSummary();
        renderMap(fitBounds);

        if (updated) {
            dashboard.classList.remove('is-fresh');
            requestAnimationFrame(() => dashboard.classList.add('is-fresh'));
            window.setTimeout(() => dashboard.classList.remove('is-fresh'), 900);
        }
    };

    Object.values(selectors).forEach((control) => {
        control.addEventListener(control.type === 'search' ? 'input' : 'change', () => {
            if (control === selectors.group && control.value) selectedGroupId = Number(control.value);
            render({ fitBounds: true });
        });
    });

    root.addEventListener('click', (event) => {
        const clearSearch = event.target.closest('[data-vp-clear-search]');
        if (clearSearch) {
            selectors.search.value = '';
            selectors.search.focus();
            render({ fitBounds: true });
            return;
        }

        const resetFilters = event.target.closest('[data-vp-reset-filters]');
        if (resetFilters) {
            Object.values(selectors).forEach((control) => { control.value = ''; });
            chooseInitialGroup();
            selectedRouteId = null;
            render({ fitBounds: true });
            return;
        }

        const groupButton = event.target.closest('[data-vp-select-group]');
        if (groupButton) {
            selectedGroupId = Number(groupButton.dataset.vpSelectGroup);
            selectedRouteId = null;
            selectors.search.value = '';
            selectors.commune.value = '';
            selectors.status.value = '';
            selectors.group.value = String(selectedGroupId);
            render({ fitBounds: true });
            document.querySelector('.vp-monitor-grid')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }

        const routeButton = event.target.closest('[data-vp-select-route]');
        if (routeButton) {
            selectedGroupId = Number(routeButton.dataset.vpGroupId);
            selectedRouteId = Number(routeButton.dataset.vpSelectRoute);
            selectors.search.value = '';
            selectors.commune.value = '';
            selectors.status.value = '';
            selectors.group.value = String(selectedGroupId);
            render({ fitBounds: true });
            window.setTimeout(() => focusRoute(selectedRouteId), 80);
        }
    });

    const loadRoadRoutes = async () => {
        try {
            const response = await fetch(root.dataset.routingUrl, { headers: { Accept: 'application/json' } });
            if (! response.ok) throw new Error('No fue posible calcular las rutas');
            roadRouting = await response.json();
        } catch (error) {
            roadRouting = { grupos: [] };
        } finally {
            routingLoaded = true;
            renderRoutePanel();
            renderDistanceSummary();
            renderMap(false);
        }
    };

    const snapshot = (payload) => JSON.stringify({
        jornada: payload.jornada.estado,
        grupos: payload.grupos.map((group) => [group.id, group.estado, group.rutas.map((route) => [route.id, route.estado, route.inicio_votacion, route.fin_votacion])]),
        incidencias: payload.incidencias.map((incident) => [incident.id, incident.tipo, incident.mensaje]),
    });

    const refresh = async () => {
        if (document.hidden) return;
        try {
            const response = await fetch(root.dataset.stateUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (! response.ok) throw new Error('No fue posible actualizar');
            const nextState = await response.json();
            const nextSnapshot = snapshot(nextState);
            const changed = previousSnapshot !== '' && previousSnapshot !== nextSnapshot;
            state = nextState;
            previousSnapshot = nextSnapshot;
            root.querySelector('[data-vp-error]').hidden = true;
            render({ refreshFilters: true, updated: changed });
        } catch (error) {
            const alert = root.querySelector('[data-vp-error]');
            alert.hidden = false;
            alert.textContent = 'No pudimos actualizar en este momento. Conservamos la última información disponible.';
        }
    };

    chooseInitialGroup();
    previousSnapshot = snapshot(state);
    render({ refreshFilters: true, fitBounds: true });
    loadRoadRoutes();
    window.setInterval(refresh, Number(root.dataset.pollingMs) || 10000);
    document.addEventListener('visibilitychange', () => { if (! document.hidden) refresh(); });
    window.addEventListener('resize', () => map.invalidateSize({ animate: false }));
}
