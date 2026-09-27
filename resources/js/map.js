import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Planned = dashed olive, actual = solid forest green. Keep in sync with the legend in route-map.blade.php.
const styles = {
    planned: { color: '#8a8a3e', weight: 3, dashArray: '6 8', opacity: 0.9 },
    actual: { color: '#264d33', weight: 5, opacity: 0.95 },
};

// Rough view of the whole direction (Netherlands → Vietnam) for maps without data.
const fallbackView = { center: [40, 60], zoom: 3 };

function initMap(figure) {
    const data = JSON.parse(figure.querySelector('script[type="application/json"]').textContent);
    const interactive = figure.dataset.interactive === 'true';

    const map = L.map(figure.querySelector('[data-map-canvas]'), {
        scrollWheelZoom: false,
        zoomControl: interactive,
        dragging: interactive,
        touchZoom: interactive,
        doubleClickZoom: interactive,
        boxZoom: interactive,
        keyboard: interactive,
    });

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);

    const layer = L.geoJSON(data, { style: (feature) => styles[feature.properties.type] }).addTo(map);

    if (layer.getLayers().length) {
        map.fitBounds(layer.getBounds(), { padding: [24, 24] });
    } else {
        map.setView(fallbackView.center, fallbackView.zoom);
    }
}

// Only build maps when they scroll into view: the journey page can have many.
const observer = new IntersectionObserver((entries) => {
    entries.filter((entry) => entry.isIntersecting).forEach((entry) => {
        observer.unobserve(entry.target);
        initMap(entry.target);
    });
}, { rootMargin: '200px' });

document.querySelectorAll('[data-route-map]').forEach((figure) => observer.observe(figure));
