import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Planned = dashed sand, actual = solid light fern (readable on satellite imagery). Keep in sync with the legend in route-map.blade.php.
const styles = {
    planned: { color: '#eee8d8', weight: 3, dashArray: '6 8', opacity: 0.95 },
    actual: { color: '#a6cf92', weight: 5, opacity: 1 },
};

// Rough view of the whole direction (Netherlands → Vietnam) for maps without data.
const fallbackView = { center: [40, 60], zoom: 3 };

// Starting point of the walk (Lisse), shown on the overview and Dutch maps until there is a location.
const home = [52.2575, 4.5570];

// NASA Blue Marble satellite imagery (public domain: no credit needed), no place names.
// Native detail up to zoom 8 (~500 m/pixel); closer zoom levels are upscaled.
const satellite = {
    url: 'https://gibs.earthdata.nasa.gov/wmts/epsg3857/best/BlueMarble_NextGeneration/default/GoogleMapsCompatible_Level8/{z}/{y}/{x}.jpeg',
    options: { maxZoom: 18, maxNativeZoom: 8 },
};

// Zoomed in far (beyond NASA detail): switch to sharp Esri imagery with place names, and roads/street
// names when zoomed in further. Esri requires a credit, shown only then.
function sharpWhenZoomed(map) {
    const esri = (service) => L.tileLayer(`https://server.arcgisonline.com/ArcGIS/rest/services/${service}/MapServer/tile/{z}/{y}/{x}`, { maxZoom: 18 });
    const sharp = L.layerGroup([esri('World_Imagery'), esri('Reference/World_Boundaries_and_Places')]);
    // Esri tiles are pre-rendered (highways and railways can't be switched off), so roads are faded and only shown close up.
    const streets = esri('Reference/World_Transportation').setOpacity(0.35);
    const credit = L.control.attribution({ prefix: false }).addAttribution('Imagery &amp; labels &copy; Esri, Maxar, Earthstar Geographics');
    const show = (layer, visible) => (visible ? !map.hasLayer(layer) && layer.addTo(map) : layer.remove());
    const update = () => {
        const zoom = map.getZoom();
        show(sharp, zoom > 8);
        show(streets, zoom >= 14);
        zoom > 8 ? credit.addTo(map) : credit.remove();
    };
    map.on('zoomend', update);
    map.whenReady(update);
}

const area = (bounds) => (bounds.getNorth() - bounds.getSouth()) * (bounds.getEast() - bounds.getWest());

function initMap(figure) {
    const data = JSON.parse(figure.querySelector('script[type="application/json"]').textContent);
    const interactive = figure.dataset.interactive === 'true';

    const map = L.map(figure.querySelector('[data-map-canvas]'), {
        scrollWheelZoom: false,
        attributionControl: false, // Leaflet and NASA imagery need no credit; see sharpWhenZoomed()
        zoomSnap: 0.25, // lets a country fill its map instead of jumping a whole zoom level
        zoomControl: interactive,
        dragging: interactive,
        touchZoom: interactive,
        doubleClickZoom: interactive,
        boxZoom: interactive,
        keyboard: interactive,
    });


    const borderData = figure.querySelector('script[data-border]');

    if (!borderData) {
        L.tileLayer(satellite.url, satellite.options).addTo(map);
        if (interactive) sharpWhenZoomed(map);
    }

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

    // Only one country: satellite imagery without place names, sharp inside the border
    // and blurred/faded around it, zoomed to the country.
    if (borderData) {
        const polygons = JSON.parse(borderData.textContent)
            .map((polygon) => polygon[0].map(([lng, lat]) => [lat, lng]));

        // Two copies of the imagery: the blurred one fills the map, the sharp one is clipped to the country.
        map.createPane('countryPane').style.zIndex = 250;
        L.tileLayer(satellite.url, { ...satellite.options, className: 'map-surroundings' }).addTo(map);
        L.tileLayer(satellite.url, { ...satellite.options, pane: 'countryPane' }).addTo(map);

        L.polygon(polygons, { color: '#ffffff', weight: 1.5, opacity: 0.8, fill: false, interactive: false }).addTo(map);
        layer.bringToFront();

        const clip = () => {
            const path = polygons.map((ring) => 'M' + ring.map((latlng) => {
                const p = map.latLngToLayerPoint(latlng);
                return `${Math.round(p.x)},${Math.round(p.y)}`;
            }).join('L') + 'Z').join('');
            map.getPane('countryPane').style.clipPath = `path(evenodd, '${path}')`;
        };
        map.on('zoomend viewreset', clip);

        // Zoom to the mainland (largest part), so overseas territories don't shrink the country.
        const mainland = polygons.map((p) => L.latLngBounds(p)).sort((a, b) => area(b) - area(a))[0];
        map.fitBounds(mainland, { padding: [12, 12], animate: false });
        clip();
    } else if (figure.dataset.startHome === 'true' && !data.features.some((f) => f.properties.type === 'position')) {
        // No (visible) location yet: start at home, Lisse.
        map.setView(home, 6.5);
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
