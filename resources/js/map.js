import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Planned = dashed olive, actual = solid forest green. Keep in sync with the legend in route-map.blade.php.
const styles = {
    planned: { color: '#8a8a3e', weight: 3, dashArray: '6 8', opacity: 0.9 },
    actual: { color: '#264d33', weight: 5, opacity: 0.95 },
};

// Rough view of the whole direction (Netherlands → Vietnam) for maps without data.
const fallbackView = { center: [40, 60], zoom: 3 };

const area = (bounds) => (bounds.getNorth() - bounds.getSouth()) * (bounds.getEast() - bounds.getWest());

function initMap(figure) {
    const data = JSON.parse(figure.querySelector('script[type="application/json"]').textContent);
    const interactive = figure.dataset.interactive === 'true';

    const map = L.map(figure.querySelector('[data-map-canvas]'), {
        scrollWheelZoom: false,
        zoomSnap: 0.25, // lets a country fill its map instead of jumping a whole zoom level
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

    const layer = L.geoJSON(data, {
        style: (feature) => styles[feature.properties.type],
        // Points: last (visible) location and journey events.
        pointToLayer: (feature, latlng) => L.circleMarker(latlng, feature.properties.type === 'position'
            ? { radius: 8, color: '#ffffff', weight: 3, fillColor: '#6a8a3c', fillOpacity: 1 }
            : { radius: 5, color: '#5b4631', weight: 2, fillColor: '#eee8d8', fillOpacity: 1 }),
        onEachFeature: (feature, marker) => {
            if (feature.properties.label && interactive) {
                const label = document.createElement('span');
                label.textContent = feature.properties.label; // text only, never HTML
                marker.bindPopup(label);
            }
        },
    }).addTo(map);

    // Only one country: cover everything around it and zoom to its outline.
    const borderData = figure.querySelector('script[data-border]');
    if (borderData) {
        const polygons = JSON.parse(borderData.textContent)
            .map((polygon) => polygon[0].map(([lng, lat]) => [lat, lng]));
        const world = [[-90, -360], [-90, 360], [90, 360], [90, -360]];

        L.polygon([world, ...polygons], {
            stroke: false, fillColor: '#e6eedc', fillOpacity: 1, fillRule: 'evenodd', interactive: false,
        }).addTo(map);
        L.polygon(polygons, { color: '#264d33', weight: 1.5, fill: false, interactive: false }).addTo(map);
        layer.bringToFront();

        // Zoom to the mainland (largest part), so overseas territories don't shrink the country.
        const mainland = polygons.map((p) => L.latLngBounds(p)).sort((a, b) => area(b) - area(a))[0];
        map.fitBounds(mainland, { padding: [12, 12] });
    } else if (layer.getLayers().length) {
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
