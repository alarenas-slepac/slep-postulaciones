import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

function mostrarMapa(element) {
    if (element.dataset.mapReady === '1') return;
    // Un atributo vacío no es una coordenada cero válida.
    const { latitude, longitude, name, rbd } = element.dataset;
    const lat = latitude?.trim() ? Number(latitude) : NaN;
    const lng = longitude?.trim() ? Number(longitude) : NaN;
    const notice = element.closest('.cv-location')?.querySelector('[data-cv-map-error]');
    if (!Number.isFinite(lat) || !Number.isFinite(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180 || (lat === 0 && lng === 0)) {
        if (notice) notice.hidden = false;
        return;
    }

    try {
        const map = L.map(element, { scrollWheelZoom: false, zoomControl: false }).setView([lat, lng], 16);
        element.dataset.mapReady = '1';
        L.control.zoom({ zoomInTitle: 'Acercar mapa', zoomOutTitle: 'Alejar mapa' }).addTo(map);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a> contributors',
            // Identifica sólo el origen del sitio; no envía ruta, RUT ni parámetros.
            referrerPolicy: 'origin',
        }).on('tileerror', () => { if (notice) notice.hidden = false; }).addTo(map);

        const icon = L.divIcon({
            className: 'cv-map-marker',
            html: '<span><i class="bi bi-geo-alt-fill" aria-hidden="true"></i></span>',
            iconSize: [42, 42], iconAnchor: [21, 38], popupAnchor: [0, -32],
        });
        const label = document.createElement('div');
        label.className = 'cv-map-popup';
        const title = document.createElement('strong');
        title.textContent = name || 'Local de votación';
        const detail = document.createElement('div');
        detail.textContent = `RBD ${rbd || ''}`;
        label.append(title, detail);
        const marker = L.marker([lat, lng], { icon, title: name || 'Local de votación', alt: `Local de votación RBD ${rbd || ''}` })
            .addTo(map).bindPopup(label);
        marker.getElement()?.setAttribute('aria-label', `Ver ubicación de ${name || 'local de votación'}, RBD ${rbd || ''}`);
    } catch {
        if (notice) notice.hidden = false;
    }
}

const elements = document.querySelectorAll('[data-cv-map]');
if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(({ isIntersecting, target }) => {
            if (isIntersecting) {
                mostrarMapa(target);
                observer.unobserve(target);
            }
        });
    }, { rootMargin: '200px' });
    elements.forEach((element) => observer.observe(element));
} else {
    elements.forEach(mostrarMapa);
}
