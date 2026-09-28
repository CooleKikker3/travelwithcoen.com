import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Planned = dashed sand, actual = solid light fern (readable on satellite imagery). Keep in sync with the legend in route-map.blade.php.
const styles = {
    planned: { color: '#eee8d8', weight: 3, dashArray: '6 8', opacity: 0.95 },
    actual: { color: '#a6cf92', weight: 5, opacity: 1 },
    // Home map: straight line from the end of the planned route to the destination, still to be planned. Dotted.
    open: { color: '#c2c07a', weight: 3, dashArray: '1 9', lineCap: 'round', opacity: 0.95 },
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

// Zoomed in far (beyond NASA detail): sharp Esri imagery, with place names (and street names close up)
// from OpenFreeMap vector tiles. Being data instead of images, only the names are drawn: no municipal
// borders, no road lines. The label code (MapLibre, ~250 kB) is only downloaded on the first zoom-in.
const LABEL_STYLE = 'https://tiles.openfreemap.org/styles/liberty';
const LABEL_LAYERS = /^(label_(village|town|city|city_capital|state)|water_name_point_label|highway-name-(path|minor|major))$/;

async function placeNames(map) {
    const [style] = await Promise.all([
        fetch(LABEL_STYLE).then((response) => response.json()),
        import('maplibre-gl/dist/maplibre-gl.css'),
    ]);
    const { maplibreGL } = await import('@maplibre/maplibre-gl-leaflet');

    // Only the vector source the names come from (no shaded relief), and only names;
    // white with a dark halo so they read on satellite imagery. Street names from zoom 14.
    style.sources = { openmaptiles: style.sources.openmaptiles };
    style.layers = style.layers
        .filter((layer) => LABEL_LAYERS.test(layer.id))
        .map((layer) => ({
            ...layer,
            // Street names only close up (MapLibre zoom 13 = map zoom 14).
            ...(layer.id.startsWith('highway-name') ? { minzoom: Math.max(layer.minzoom ?? 0, 13) } : {}),
            // Names in the language of the page (Dutch/English), else the local name in Latin letters.
            layout: { ...layer.layout, 'text-field': ['coalesce', ['get', `name:${document.documentElement.lang || 'en'}`], ['get', 'name:latin'], ['get', 'name']] },
            paint: { ...layer.paint, 'text-color': '#ffffff', 'text-halo-color': 'rgba(14, 28, 19, 0.85)', 'text-halo-width': 1.6 },
        }));

    if (!map.getPane('labels')) {
        map.createPane('labels').style.zIndex = 350; // above the imagery, below the route lines
        map.getPane('labels').style.pointerEvents = 'none';
    }

    return maplibreGL({ style, pane: 'labels', interactive: false });
}

function sharpWhenZoomed(map) {
    const imagery = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 18, className: 'map-sharp' });
    const credit = L.control.attribution({ prefix: false })
        // The names layer adds its own credit (OpenFreeMap, OpenMapTiles, OpenStreetMap).
        .addAttribution('Imagery &copy; Esri, Maxar, Earthstar Geographics');
    let labels = null;

    const update = async () => {
        const sharp = map.getZoom() > 8;
        if (sharp && !map.hasLayer(imagery)) {
            imagery.addTo(map);
            credit.addTo(map);
            // No names without a connection (or WebGL); the map still works.
            labels ??= await placeNames(map).catch((error) => { console.warn('Place names unavailable', error); return null; });
            if (labels && map.getZoom() > 8 && !map.hasLayer(labels)) labels.addTo(map);
        }
        if (!sharp && map.hasLayer(imagery)) {
            imagery.remove();
            credit.remove();
            labels?.remove();
        }
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
        // Points: last (visible) location (pulsing), start/destination (handwritten label) and journey events.
        pointToLayer: (feature, latlng) => {
            const type = feature.properties.type;
            if (type === 'position') {
                return L.marker(latlng, { icon: L.divIcon({ className: 'map-position', html: '<span></span>', iconSize: [18, 18] }), keyboard: false });
            }
            if (type === 'start' || type === 'destination') {
                const icon = L.divIcon({ className: `map-endpoint map-endpoint--${type}`, html: '<span class="map-endpoint__dot"></span><span class="map-endpoint__label"></span>', iconSize: [12, 12] });
                const marker = L.marker(latlng, { icon, keyboard: false, interactive: false });
                marker.on('add', () => { marker.getElement().querySelector('.map-endpoint__label').textContent = feature.properties.label; }); // text only
                return marker;
            }
            return L.circleMarker(latlng, { radius: 5, color: '#5b4631', weight: 2, fillColor: '#eee8d8', fillOpacity: 1 });
        },
        onEachFeature: (feature, marker) => {
            if (feature.properties.label && interactive && !['start', 'destination'].includes(feature.properties.type)) {
                const label = document.createElement('span');
                label.textContent = feature.properties.label; // text only, never HTML
                marker.bindPopup(label);
            }
        },
    });

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
    } else if (figure.dataset.startHome === 'true') {
        // Always centred on the latest (visible) GPS location, or on Lisse without one; zoomed in to
        // region level, so a route is a clear line and not a blob.
        const position = data.features.find((f) => f.properties.type === 'position');
        map.setView(position ? [position.geometry.coordinates[1], position.geometry.coordinates[0]] : home, 8);
    } else if (layer.getLayers().length) {
        map.fitBounds(layer.getBounds(), { padding: [24, 24], maxZoom: 8 });
    } else {
        map.setView(fallbackView.center, fallbackView.zoom);
    }

    // Lines and markers only once the map has a view: Leaflet cannot draw them on a map without one.
    // Dark outline under every line, so light route colours stay visible on light fields and cities.
    L.geoJSON(data, {
        filter: (feature) => feature.geometry.type.endsWith('LineString'),
        style: (feature) => ({ color: '#0e1c13', opacity: 0.55, weight: (styles[feature.properties.type]?.weight ?? 3) + 3, lineCap: 'round', lineJoin: 'round', interactive: false }),
    }).addTo(map);
    layer.addTo(map);

    // Zoomed out, a dashed route turns into a blob: planned routes are a thin solid line until zoom 10.
    const restyle = () => layer.setStyle((feature) => (feature.properties.type === 'planned' && map.getZoom() < 10
        ? { ...styles.planned, dashArray: null, weight: 2 }
        : styles[feature.properties.type]));
    restyle();
    map.on('zoomend', restyle);
}

// Only build maps when they scroll into view: the journey page can have many.
const observer = new IntersectionObserver((entries) => {
    entries.filter((entry) => entry.isIntersecting).forEach((entry) => {
        observer.unobserve(entry.target);
        initMap(entry.target);
    });
}, { rootMargin: '200px' });

document.querySelectorAll('[data-route-map]').forEach((figure) => observer.observe(figure));
