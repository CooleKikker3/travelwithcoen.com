import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Route planner in the CMS (app/Filament/Pages/RoutePlanner.php).
// Waypoints are clicked on the map; each segment between two waypoints is either straight or
// routed over walking paths by the server (Livewire method "segment").

const el = document.querySelector('[data-route-planner]');

if (el) {
    const id = el.closest('[wire\\:id]').getAttribute('wire:id');
    const component = window.Livewire?.find(id);
    component ? init(component) : document.addEventListener('livewire:initialized', () => init(window.Livewire.find(id)));
}

function init(wire) {
    const data = JSON.parse(el.dataset.initial);
    const $ = (selector) => document.querySelector(selector);

    const map = L.map(el);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);

    const reference = L.geoJSON({ type: 'FeatureCollection', features: data.reference }, {
        style: { color: '#6b7280', weight: 3, dashArray: '4 6', opacity: 0.7 },
    }).addTo(map);
    const line = L.polyline([], { color: '#4f6b2e', weight: 4 }).addTo(map);
    const markers = L.layerGroup().addTo(map);

    // Saved waypoints are [lat, lng, index into the saved line], so segments can be restored without re-routing.
    let waypoints = data.waypoints.map(([lat, lng]) => L.latLng(lat, lng));
    let segments = data.waypoints.slice(1).map((w, i) => data.line.slice(data.waypoints[i][2], w[2] + 1));
    let queue = Promise.resolve();

    const run = (task) => (queue = queue.then(async () => {
        $('[data-status]').textContent = 'Berekenen…';
        try { await task(); } finally { $('[data-status]').textContent = ''; render(); }
    }));

    const route = async (a, b) => $('[data-routing]').value === 'straight'
        ? [[a.lat, a.lng], [b.lat, b.lng]]
        : await wire.segment([a.lat, a.lng], [b.lat, b.lng]);

    function fullLine() {
        return segments.flatMap((segment, i) => (i === 0 ? segment : segment.slice(1)));
    }

    function render() {
        markers.clearLayers();
        waypoints.forEach((point, i) => {
            const marker = L.marker(point, {
                draggable: true,
                icon: L.divIcon({ className: '', html: `<div style="width:22px;height:22px;border-radius:50%;background:#264d33;color:#fff;font:600 11px/22px sans-serif;text-align:center;border:2px solid #fff;box-shadow:0 1px 3px #0005">${i + 1}</div>`, iconSize: [22, 22], iconAnchor: [11, 11] }),
            });
            marker.on('click', () => removeWaypoint(i));
            marker.on('dragend', () => moveWaypoint(i, marker.getLatLng()));
            markers.addLayer(marker);
        });

        const points = fullLine();
        line.setLatLngs(points);
        const km = points.reduce((sum, p, i) => (i ? sum + L.latLng(points[i - 1]).distanceTo(L.latLng(p)) : 0), 0) / 1000;
        $('[data-distance]').textContent = `${km.toFixed(1)} km · ${waypoints.length} punten`;
    }

    function addWaypoint(latlng) {
        run(async () => {
            waypoints.push(latlng);
            if (waypoints.length > 1) {
                segments.push(await route(waypoints.at(-2), latlng));
            }
        });
    }

    function moveWaypoint(i, latlng) {
        run(async () => {
            waypoints[i] = latlng;
            if (i > 0) segments[i - 1] = await route(waypoints[i - 1], latlng);
            if (i < waypoints.length - 1) segments[i] = await route(latlng, waypoints[i + 1]);
        });
    }

    function removeWaypoint(i) {
        run(async () => {
            waypoints.splice(i, 1);
            if (i === 0) segments.shift();
            else if (i === waypoints.length) segments.pop();
            else segments.splice(i - 1, 2, await route(waypoints[i - 1], waypoints[i]));
        });
    }

    function rerouteAll() {
        run(async () => {
            for (let i = 0; i < waypoints.length - 1; i++) {
                segments[i] = await route(waypoints[i], waypoints[i + 1]);
            }
        });
    }

    map.on('click', (event) => addWaypoint(event.latlng));
    $('[data-routing]').addEventListener('change', () => waypoints.length > 1 && confirm('De hele route opnieuw berekenen met deze instelling?') && rerouteAll());
    $('[data-action="undo"]').addEventListener('click', () => waypoints.length && removeWaypoint(waypoints.length - 1));
    $('[data-action="clear"]').addEventListener('click', () => {
        if (confirm('Alle punten wissen?')) run(async () => { waypoints = []; segments = []; });
    });
    $('[data-action="save"]').addEventListener('click', () => run(async () => {
        let index = 0;
        const saved = waypoints.map((w, i) => {
            if (i > 0) index += segments[i - 1].length - 1;
            return [w.lat, w.lng, index];
        });
        await wire.save(saved, fullLine());
    }));

    // Initial view: this route, else the reference routes, else Europe → Asia.
    const bounds = waypoints.length ? L.latLngBounds(waypoints) : reference.getBounds();
    bounds.isValid() ? map.fitBounds(bounds, { padding: [30, 30] }) : map.setView([45, 30], 4);
    render();
}
