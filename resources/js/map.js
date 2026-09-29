import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Planned = thin yellow, actual = bright green (readable on satellite imagery). Keep in sync with the legend in route-map.blade.php.
const styles = {
    planned: { color: '#facc15', weight: 2, opacity: 0.95, casing: 4 },
    actual: { color: '#4ade80', weight: 5, opacity: 1 },
    // A route piece highlighted from its card (country page).
    highlight: { color: '#ffffff' },
    // Home map: straight line from the end of the planned route to the destination, still to be planned. Dotted.
    open: { color: '#fde68a', weight: 3, dashArray: '1 9', lineCap: 'round', opacity: 0.95 },
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

// Cards with [data-route-piece="id"] highlight that piece on every map of the page (hover, keyboard focus, tap).
const highlighters = [];
const highlight = (routeId) => highlighters.forEach((fn) => fn(routeId));
let tapped = null;
document.addEventListener('mouseover', (event) => {
    const card = event.target.closest('[data-route-piece]');
    if (card && !card.contains(event.relatedTarget)) highlight(Number(card.dataset.routePiece));
});
document.addEventListener('mouseout', (event) => {
    const card = event.target.closest('[data-route-piece]');
    if (card && !card.contains(event.relatedTarget) && tapped === null) highlight(null);
});
document.addEventListener('focusin', (event) => {
    const card = event.target.closest('[data-route-piece]');
    if (card) highlight(Number(card.dataset.routePiece));
});
document.addEventListener('click', (event) => {
    // Phones have no hover: a tap toggles the highlight.
    const card = event.target.closest('[data-route-piece]');
    const id = card ? Number(card.dataset.routePiece) : null;
    tapped = id === tapped ? null : id;
    if (card || tapped === null) highlight(tapped);
});

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

    // Zoom with Ctrl (⌘ on a Mac) + scroll wheel, or a trackpad pinch (sends Ctrl + wheel); plain scrolling keeps
    // scrolling the page and shows a short hint.
    if (interactive) {
        const mac = /Mac|iPhone|iPad/.test(navigator.platform);
        const hint = Object.assign(document.createElement('div'), {
            className: 'map-zoom-hint',
            textContent: (figure.dataset.zoomHint || '').replace('Ctrl', mac ? '⌘' : 'Ctrl'),
        });
        figure.append(hint);
        let hintTimer;
        const wheel = map.scrollWheelZoom;
        const zoomOnWheel = wheel._onWheelScroll;
        wheel._onWheelScroll = function (e) {
            if (e.ctrlKey || e.metaKey) {
                hint.classList.remove('is-visible');
                return zoomOnWheel.call(this, e);
            }
            hint.classList.add('is-visible');
            clearTimeout(hintTimer);
            hintTimer = setTimeout(() => hint.classList.remove('is-visible'), 1500);
        };
        wheel.enable();
    }
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
            if (type === 'exit') {
                // The hand-drawn arrow from the home page, starting at the border and pointing north before rotating.
                const arrow = `<svg viewBox="0 0 44 44" width="44" height="44" fill="none" style="transform:rotate(${Number(feature.properties.bearing) || 0}deg)"><g stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 31c-4-8 3-12 2-26"/><path d="M16 11 22 5l5.5 6.5"/></g></svg>`;
                return L.marker(latlng, { icon: L.divIcon({ className: 'map-exit', html: arrow, iconSize: [44, 44] }), keyboard: false });
            }
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
    const isLine = (feature) => feature?.geometry.type.endsWith('LineString');
    const casingStyle = (feature) => ({ color: '#0e1c13', opacity: 0.55, weight: (styles[feature.properties.type]?.weight ?? 3) + (styles[feature.properties.type]?.casing ?? 3), lineCap: 'round', lineJoin: 'round', interactive: false });
    const casing = L.geoJSON(data, { filter: isLine, style: casingStyle }).addTo(map);
    layer.addTo(map);

    // Planned routes look the same at every zoom level (dashes turned into a blob zoomed out).
    // A highlighted route piece (hover over its card on the country page) gets another colour and is on top.
    let highlighted = null;
    let baseHidden = false; // the embedded lines, while more detail for the area in view is shown (below)
    let detailLines = null;
    const styleFor = (feature) => {
        const style = styles[feature.properties.type];
        // Highlighted: same line, only another colour.
        return highlighted && feature.properties.route === highlighted ? { ...style, color: styles.highlight.color, opacity: 1 } : style;
    };
    const restyle = () => {
        layer.setStyle((feature) => (baseHidden && isLine(feature) ? { opacity: 0 } : styleFor(feature)));
        casing.setStyle((feature) => ({ ...casingStyle(feature), opacity: baseHidden ? 0 : 0.55 }));
        detailLines?.setStyle(styleFor);
    };
    restyle();

    // Walked route by level of detail: the page embeds a coarse level; zooming in fetches more detail for the
    // visible area (GET /api/track, App\Support\WalkedTrack), from level 3 also the route pieces with every bend
    // of the paths, like in the route planner. Delay and privacy are applied by the server.
    if (interactive && figure.dataset.trackUrl) {
        const baseLevel = Number(figure.dataset.trackLevel || 2);
        const levelFor = (zoom) => (zoom <= 5 ? 1 : zoom <= 8 ? 2 : zoom <= 11 ? 3 : 4);
        const cache = new Map();
        let detail = null;
        let current = null;
        let timer;

        // Route pieces only come with the detail from level 3: below that only the walked route is replaced.
        const showBase = (visible, routesToo) => {
            if (routesToo) {
                baseHidden = !visible;
                restyle();
            }
            layer.eachLayer((line) => line.feature?.properties.type === 'actual' && line.setStyle({ opacity: visible ? styles.actual.opacity : 0 }));
            casing.eachLayer((line) => line.feature?.properties.type === 'actual' && line.setStyle({ opacity: visible ? 0.55 : 0 }));
        };

        const update = async () => {
            const level = levelFor(map.getZoom());
            if (level <= baseLevel) {
                detail?.remove();
                detail = null;
                detailLines = null;
                current = null;
                showBase(true, true);
                return;
            }
            const b = map.getBounds().pad(0.3);
            const bbox = [b.getWest(), b.getSouth(), b.getEast(), b.getNorth()].map((v) => v.toFixed(3)).join(',');
            const key = `${level}:${bbox}`;
            if (key === current) return;
            current = key;

            let lines = cache.get(key);
            if (!lines) {
                try {
                    const params = new URLSearchParams({ level, bbox });
                    if (figure.dataset.trackCountry) params.set('country', figure.dataset.trackCountry);
                    lines = await (await fetch(`${figure.dataset.trackUrl}?${params}`, { headers: { Accept: 'application/json' } })).json();
                    cache.set(key, lines);
                } catch {
                    return; // no connection: keep what is shown
                }
            }
            if (key !== current) return; // the map moved on meanwhile

            detail?.remove();
            detailLines = L.geoJSON(lines, { style: styleFor, interactive: false });
            detail = L.featureGroup([L.geoJSON(lines, { style: casingStyle, interactive: false }), detailLines]).addTo(map);
            showBase(false, level >= 3);
        };

        map.on('moveend', () => {
            clearTimeout(timer);
            timer = setTimeout(update, 250);
        });
        update();
    }
    highlighters.push((routeId) => {
        highlighted = routeId;
        restyle();
        if (routeId) layer.eachLayer((line) => line.feature?.properties.route === routeId && line.bringToFront?.());
    });
}

// Only build maps when they scroll into view: the journey page can have many.
const observer = new IntersectionObserver((entries) => {
    entries.filter((entry) => entry.isIntersecting).forEach((entry) => {
        observer.unobserve(entry.target);
        initMap(entry.target);
    });
}, { rootMargin: '200px' });

document.querySelectorAll('[data-route-map]').forEach((figure) => observer.observe(figure));
