import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/*
 * Route editor in the CMS (app/Filament/Pages/RoutePlanner.php).
 *
 * A route piece is built from parts, in order:
 *  - GPX files: read here in the browser; only a simplified line is sent when saving (small on a weak connection);
 *  - drawn parts: tap points on the map; between points a straight line or walking paths (BRouter via the server).
 * All editing is local. Every change is kept as a draft on this device, so nothing is lost without signal;
 * only "Opslaan" needs a connection. Lines are sent as encoded polylines (see App\Support\Polyline).
 */

const root = document.querySelector('[data-route-editor]');

if (root) {
    const id = root.closest('[wire\\:id]').getAttribute('wire:id');
    const start = () => init(window.Livewire.find(id));
    window.Livewire?.find(id) ? start() : document.addEventListener('livewire:initialized', start);
}

// Colours per part, so the parts are easy to tell apart on the map and in the list.
const COLOURS = ['#2563eb', '#db2777', '#ea580c', '#16a34a', '#9333ea', '#0891b2', '#ca8a04', '#dc2626'];
const GAP_METRES = 50;
const SIMPLIFY_DEG = 0.00004; // ~4 m: keeps the shape, drops most GPS noise
const DRAFT_PREFIX = 'twc-route:';

/* ---------- small helpers ---------- */

const metres = (a, b) => L.latLng(a).distanceTo(L.latLng(b));
const lineKm = (line) => line.reduce((sum, p, i) => (i ? sum + metres(line[i - 1], p) : 0), 0) / 1000;
const formatKm = (km) => `${km.toLocaleString('nl-NL', { maximumFractionDigits: 1, minimumFractionDigits: 1 })} km`;
const escapeHtml = (text) => String(text ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const storage = {
    get: (key) => { try { return JSON.parse(localStorage.getItem(key)); } catch { return null; } },
    set: (key, value) => { try { localStorage.setItem(key, JSON.stringify(value)); } catch { /* full or blocked */ } },
    remove: (key) => { try { localStorage.removeItem(key); } catch { /* blocked */ } },
};

// Encoded polyline, precision 5 (same as App\Support\Polyline).
function encode(points) {
    let result = '';
    let previous = [0, 0];
    for (const point of points) {
        for (const i of [0, 1]) {
            const value = Math.round(point[i] * 1e5);
            let delta = value - previous[i];
            previous[i] = value;
            delta = delta < 0 ? ~(delta << 1) : delta << 1;
            while (delta >= 0x20) {
                result += String.fromCharCode((0x20 | (delta & 0x1f)) + 63);
                delta >>= 5;
            }
            result += String.fromCharCode(delta + 63);
        }
    }
    return result;
}

function decode(encoded) {
    const points = [];
    const current = [0, 0];
    let index = 0;
    while (index < encoded.length) {
        for (const i of [0, 1]) {
            let shift = 0;
            let result = 0;
            let byte;
            do {
                byte = encoded.charCodeAt(index++) - 63;
                result |= (byte & 0x1f) << shift;
                shift += 5;
            } while (byte >= 0x20 && index < encoded.length);
            current[i] += result & 1 ? ~(result >> 1) : result >> 1;
        }
        points.push([current[0] / 1e5, current[1] / 1e5]);
    }
    return points;
}

// Douglas–Peucker simplification on [lat, lng] (iterative, safe for long tracks).
function simplify(points, tolerance) {
    if (points.length < 3) return points;
    const keep = new Uint8Array(points.length);
    keep[0] = keep[points.length - 1] = 1;
    const stack = [[0, points.length - 1]];
    while (stack.length) {
        const [first, last] = stack.pop();
        let maxDistance = 0;
        let index = 0;
        const [ay, ax] = points[first];
        const [by, bx] = points[last];
        const dx = bx - ax;
        const dy = by - ay;
        const lengthSq = dx * dx + dy * dy;
        for (let i = first + 1; i < last; i++) {
            const [py, px] = points[i];
            const t = lengthSq ? Math.max(0, Math.min(1, ((px - ax) * dx + (py - ay) * dy) / lengthSq)) : 0;
            const distance = Math.hypot(px - (ax + t * dx), py - (ay + t * dy));
            if (distance > maxDistance) { maxDistance = distance; index = i; }
        }
        if (maxDistance > tolerance) {
            keep[index] = 1;
            stack.push([first, index], [index, last]);
        }
    }
    return points.filter((_, i) => keep[i]);
}

// GPX → { label, line, time } (track points, else route points).
async function readGpx(file) {
    const doc = new DOMParser().parseFromString(await file.text(), 'application/xml');
    let nodes = [...doc.getElementsByTagNameNS('*', 'trkpt')];
    if (!nodes.length) nodes = [...doc.getElementsByTagNameNS('*', 'rtept')];
    const line = nodes
        .map((n) => [parseFloat(n.getAttribute('lat')), parseFloat(n.getAttribute('lon'))])
        .filter(([lat, lng]) => Number.isFinite(lat) && Number.isFinite(lng) && !(lat === 0 && lng === 0));
    const time = nodes[0]?.getElementsByTagNameNS('*', 'time')[0]?.textContent ?? null;
    return { label: file.name.replace(/\.gpx$/i, ''), line: simplify(line, SIMPLIFY_DEG), time };
}

/* ---------- editor ---------- */

function init(wire) {
    const initial = JSON.parse(root.dataset.initial);
    const $ = (selector) => root.querySelector(selector);
    const draftKey = `${DRAFT_PREFIX}${initial.routeId ?? 'new'}`;

    // State: meta (texts, country, type) and parts. Parts hold lines as [lat, lng] arrays while editing.
    let state = {
        meta: { ...initial.meta },
        segments: initial.segments.map((s) => ({ ...s, line: decode(s.line), waypoints: s.waypoints ?? [] })),
    };
    let active = null; // index of the drawn part that receives map taps
    let dirty = false;
    const history = [];

    /* ----- map ----- */
    const map = L.map($('[data-map]'));
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);
    const reference = L.geoJSON({ type: 'FeatureCollection', features: initial.reference }, {
        style: { color: '#6b7280', weight: 3, dashArray: '4 6', opacity: 0.6 },
        interactive: false,
    }).addTo(map);
    const drawing = L.layerGroup().addTo(map);
    new ResizeObserver(() => map.invalidateSize()).observe($('[data-map]'));

    /* ----- state changes: undo, draft, redraw ----- */
    function change(mutate) {
        history.push(JSON.stringify({ state, active }));
        if (history.length > 40) history.shift();
        mutate();
        dirty = true;
        storage.set(draftKey, { savedAt: Date.now(), state: serialise() });
        render();
    }

    function serialise() {
        return { meta: state.meta, segments: state.segments.map((s) => ({ ...s, line: encode(s.line) })) };
    }

    function undo() {
        const previous = history.pop();
        if (!previous) return;
        ({ state, active } = JSON.parse(previous));
        dirty = true;
        storage.set(draftKey, { savedAt: Date.now(), state: serialise() });
        render();
    }

    /* ----- routing between two drawn points (server; straight line when offline) ----- */
    let queue = Promise.resolve();
    const status = (text) => { $('[data-status]').textContent = text; };

    function routeBetween(a, b, routing) {
        if (routing === 'straight') return Promise.resolve([a, b]);
        return wire.segment(a, b, routing).catch(() => {
            status('Geen verbinding: rechte lijn gebruikt.');
            return [a, b];
        });
    }

    // Recalculate the line of a drawn part from its waypoints (only the legs that changed when `legs` is given).
    function rebuildDrawn(segment, legs = null) {
        queue = queue.then(async () => {
            status('Berekenen…');
            const points = segment.waypoints;
            const oldLegs = legsOf(segment);
            const newLegs = [];
            for (let i = 0; i < points.length - 1; i++) {
                const reuse = legs && !legs.includes(i) && oldLegs[i];
                newLegs.push(reuse || await routeBetween(points[i].slice(0, 2), points[i + 1].slice(0, 2), segment.routing));
            }
            setLegs(segment, newLegs);
            status('');
            storage.set(draftKey, { savedAt: Date.now(), state: serialise() });
            render();
        });
        return queue;
    }

    // A drawn part's line split per leg, using the line index stored with each waypoint.
    function legsOf(segment) {
        const w = segment.waypoints;
        return w.slice(1).map((point, i) => (w[i][2] != null && point[2] != null ? segment.line.slice(w[i][2], point[2] + 1) : null));
    }

    function setLegs(segment, legs) {
        let index = 0;
        segment.line = legs.length ? legs.flatMap((leg, i) => (i ? leg.slice(1) : leg)) : segment.waypoints.map((w) => w.slice(0, 2));
        segment.waypoints = segment.waypoints.map((w, i) => {
            if (i > 0) index += legs[i - 1].length - 1;
            return [w[0], w[1], index];
        });
    }

    /* ----- actions ----- */
    const routing = () => $('[data-routing]').value;

    function startDrawing(at = null) {
        change(() => {
            const index = at ?? (active !== null ? active + 1 : state.segments.length);
            const previous = state.segments[index - 1];
            const first = previous?.line.at(-1);
            state.segments.splice(index, 0, {
                kind: 'drawn',
                label: 'Getekend stuk',
                routing: routing(),
                waypoints: first ? [[first[0], first[1], 0]] : [],
                line: first ? [first] : [],
            });
            active = index;
        });
    }

    function addPoint(latlng) {
        const segment = state.segments[active];
        const first = segment.waypoints.length === 0;
        change(() => {
            segment.waypoints.push([latlng.lat, latlng.lng, first ? 0 : null]);
            if (first) segment.line = [[latlng.lat, latlng.lng]];
        });
        if (!first) rebuildDrawn(segment, [segment.waypoints.length - 2]);
    }

    function movePoint(segment, i, latlng) {
        change(() => { segment.waypoints[i] = [latlng.lat, latlng.lng, segment.waypoints[i][2]]; });
        rebuildDrawn(segment, [i - 1, i].filter((leg) => leg >= 0 && leg < segment.waypoints.length - 1));
    }

    function removePoint(segment, i) {
        change(() => { segment.waypoints.splice(i, 1); });
        // Only the leg that now joins the neighbours of the removed point is new; the rest is reused.
        rebuildDrawn(segment, i > 0 && i < segment.waypoints.length ? [i - 1] : []);
    }

    function move(i, direction) {
        const j = i + direction;
        if (j < 0 || j >= state.segments.length) return;
        change(() => {
            [state.segments[i], state.segments[j]] = [state.segments[j], state.segments[i]];
            if (active === i) active = j; else if (active === j) active = i;
        });
    }

    function reverse(i) {
        change(() => {
            const segment = state.segments[i];
            segment.line = [...segment.line].reverse();
            const last = segment.line.length - 1;
            segment.waypoints = [...segment.waypoints].reverse().map((w) => [w[0], w[1], w[2] == null ? null : last - w[2]]);
        });
    }

    function remove(i) {
        if (!confirm(`Stuk ${i + 1} (${state.segments[i].label || 'zonder naam'}) verwijderen?`)) return;
        change(() => {
            state.segments.splice(i, 1);
            if (active === i) active = null; else if (active > i) active--;
        });
    }

    // Close a gap: a drawn part from the end of part i to the start of part i + 1.
    function connect(i) {
        const from = state.segments[i].line.at(-1);
        const to = state.segments[i + 1].line[0];
        const segment = { kind: 'drawn', label: 'Verbinding', routing: routing(), waypoints: [[from[0], from[1], null], [to[0], to[1], null]], line: [from, to] };
        change(() => { state.segments.splice(i + 1, 0, segment); active = null; });
        rebuildDrawn(segment);
    }

    async function addGpx(files) {
        status('GPX inlezen…');
        const parts = (await Promise.all([...files].map(readGpx))).filter((p) => p.line.length > 1);
        const skipped = files.length - parts.length;
        // Several files (e.g. four days of the Vierdaagse): by the time in the file, else by file name.
        parts.sort((a, b) => (a.time && b.time ? a.time.localeCompare(b.time) : a.label.localeCompare(b.label, 'nl', { numeric: true })));
        if (parts.length) {
            change(() => {
                const index = active !== null ? active + 1 : state.segments.length;
                state.segments.splice(index, 0, ...parts.map((p) => ({ kind: 'gpx', label: p.label, routing: null, waypoints: [], line: p.line })));
                active = null;
            });
            fit();
        }
        status(skipped ? `${skipped} bestand(en) zonder route overgeslagen.` : '');
    }

    /* ----- saving ----- */
    async function save() {
        readMeta();
        const payload = serialise();
        payload.segments = payload.segments.filter((s) => decode(s.line).length > 1);
        status('Opslaan…');
        try {
            await queue;
            const result = await wire.save(payload);
            if (!result) { status(''); return; }
            dirty = false;
            storage.remove(draftKey);
            storage.remove(`${DRAFT_PREFIX}new`);
            if (!initial.routeId) {
                // Continue editing the saved piece under its own address.
                window.location.replace(`${window.location.pathname}?route=${result.routeId}`);
                return;
            }
            status('Opgeslagen.');
        } catch {
            status('Niet opgeslagen: geen verbinding? Je werk staat veilig op dit apparaat, probeer het zo opnieuw.');
        }
    }

    /* ----- meta fields (texts, country, type) ----- */
    const metaFields = [...root.querySelectorAll('[data-meta]')];
    function writeMeta() { metaFields.forEach((field) => { field.value = state.meta[field.dataset.meta] ?? ''; }); }
    function readMeta() { metaFields.forEach((field) => { state.meta[field.dataset.meta] = field.value || null; }); }
    metaFields.forEach((field) => field.addEventListener('change', () => change(readMeta)));

    /* ----- drawing on the map and the list ----- */
    function gaps() {
        return state.segments.slice(0, -1).map((segment, i) => {
            const end = segment.line.at(-1);
            const next = state.segments[i + 1].line[0];
            return end && next && metres(end, next) > GAP_METRES ? { from: end, to: next, km: metres(end, next) / 1000 } : null;
        });
    }

    function render() {
        drawing.clearLayers();
        state.segments.forEach((segment, i) => {
            const colour = COLOURS[i % COLOURS.length];
            if (segment.line.length > 1) {
                L.polyline(segment.line, { color: '#fff', weight: i === active ? 9 : 7, opacity: 0.8, interactive: false }).addTo(drawing);
                L.polyline(segment.line, { color: colour, weight: i === active ? 6 : 4 })
                    .on('click', (event) => {
                        // Selecting a part must not also add a point to the active part.
                        L.DomEvent.stopPropagation(event);
                        active = segment.kind === 'drawn' ? i : null;
                        render();
                    })
                    .addTo(drawing);
            }
            if (i === active) {
                segment.waypoints.forEach((point, p) => {
                    const marker = L.marker(point.slice(0, 2), {
                        draggable: true,
                        icon: L.divIcon({ className: '', html: `<div style="width:26px;height:26px;border-radius:50%;background:${colour};color:#fff;font:700 12px/26px sans-serif;text-align:center;border:2px solid #fff;box-shadow:0 1px 4px #0006">${p + 1}</div>`, iconSize: [26, 26], iconAnchor: [13, 13] }),
                    });
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.textContent = 'Punt verwijderen';
                    button.style.cssText = 'padding:.4rem .7rem;border-radius:.5rem;background:#dc2626;color:#fff;font-weight:600';
                    button.addEventListener('click', () => { map.closePopup(); removePoint(segment, p); });
                    marker.bindPopup(button);
                    marker.on('dragend', () => movePoint(segment, p, marker.getLatLng()));
                    marker.addTo(drawing);
                });
            }
        });
        gaps().forEach((gap) => gap && L.polyline([gap.from, gap.to], { color: '#dc2626', weight: 3, dashArray: '2 8', interactive: false }).addTo(drawing));

        renderList();
        const km = state.segments.reduce((sum, s) => sum + lineKm(s.line), 0);
        $('[data-distance]').textContent = `${formatKm(km)} · ${state.segments.length} ${state.segments.length === 1 ? 'stuk' : 'stukken'}`;
        $('[data-hint]').textContent = active !== null
            ? `Tik op de kaart om punten aan stuk ${active + 1} toe te voegen. Sleep een punt om het te verplaatsen, tik erop om het te verwijderen.`
            : 'Kies "Stuk tekenen" of "GPX toevoegen". Tik op een getekend stuk om het te bewerken.';
    }

    function renderList() {
        const list = $('[data-segments]');
        const gapList = gaps();
        const button = (action, i, label, title) => `<button type="button" data-do="${action}" data-i="${i}" title="${title}" style="min-width:2.5rem;height:2.5rem;border-radius:.5rem;border:1px solid #d1d5db;background:#fff;color:#111;font-weight:700">${label}</button>`;
        list.innerHTML = state.segments.map((segment, i) => {
            const colour = COLOURS[i % COLOURS.length];
            const card = `
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.5rem;padding:.6rem .75rem;border-radius:.75rem;border:2px solid ${i === active ? colour : '#e5e7eb'};background:#fff">
                    <span style="width:.9rem;height:.9rem;border-radius:50%;background:${colour};flex:none"></span>
                    <strong style="flex:none">${i + 1}.</strong>
                    <input data-label="${i}" value="${escapeHtml(segment.label)}" style="flex:1 1 9rem;min-width:0;border:1px solid #e5e7eb;border-radius:.5rem;padding:.35rem .5rem;font-size:.875rem;background:#fff;color:#111">
                    <span style="font-size:.8rem;color:#6b7280;flex:none">${segment.kind === 'gpx' ? 'GPX' : (segment.routing === 'straight' ? 'Getekend, recht' : 'Getekend, paden')} · ${formatKm(lineKm(segment.line))}</span>
                    <span style="display:flex;gap:.35rem;margin-inline-start:auto">
                        ${segment.kind === 'drawn' ? button('edit', i, '✎', 'Punten bewerken') : ''}
                        ${button('up', i, '↑', 'Omhoog')}${button('down', i, '↓', 'Omlaag')}${button('reverse', i, '⇄', 'Omdraaien')}${button('remove', i, '🗑', 'Verwijderen')}
                    </span>
                </div>`;
            const gap = gapList[i];
            const gapRow = gap ? `
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.5rem;padding:.2rem .75rem;font-size:.85rem;color:#b91c1c">
                    Gat van ${formatKm(gap.km)} tussen stuk ${i + 1} en ${i + 2}
                    <button type="button" data-do="connect" data-i="${i}" style="padding:.3rem .7rem;border-radius:.5rem;background:#b91c1c;color:#fff;font-weight:600">Verbinden</button>
                </div>` : '';
            return card + gapRow;
        }).join('') || '<p style="font-size:.875rem;color:#6b7280;margin:0">Nog geen stukken. Teken een stuk of voeg GPX-bestanden toe (meerdere tegelijk kan).</p>';
    }

    $('[data-segments]').addEventListener('click', (event) => {
        const target = event.target.closest('[data-do]');
        if (!target) return;
        const i = Number(target.dataset.i);
        ({
            edit: () => { active = active === i ? null : i; render(); zoomTo(i); },
            up: () => move(i, -1),
            down: () => move(i, 1),
            reverse: () => reverse(i),
            remove: () => remove(i),
            connect: () => connect(i),
        })[target.dataset.do]();
    });
    $('[data-segments]').addEventListener('change', (event) => {
        const input = event.target.closest('[data-label]');
        if (input) change(() => { state.segments[Number(input.dataset.label)].label = input.value.trim() || null; });
    });

    map.on('click', (event) => {
        if (active !== null && state.segments[active]?.kind === 'drawn') addPoint(event.latlng);
    });

    $('[data-action="draw"]').addEventListener('click', () => startDrawing());
    $('[data-action="gpx"]').addEventListener('click', () => $('[data-gpx-input]').click());
    $('[data-gpx-input]').addEventListener('change', (event) => { addGpx([...event.target.files]); event.target.value = ''; });
    $('[data-action="undo"]').addEventListener('click', undo);
    $('[data-action="save"]').addEventListener('click', save);
    $('[data-routing]').addEventListener('change', () => {
        const segment = state.segments[active];
        if (segment?.kind === 'drawn' && segment.waypoints.length > 1 && confirm(`Stuk ${active + 1} opnieuw berekenen met deze instelling?`)) {
            change(() => { segment.routing = routing(); });
            rebuildDrawn(segment);
        }
    });
    root.querySelector('[data-switch]').addEventListener('change', (event) => {
        if (dirty && !confirm('Je hebt niet-opgeslagen wijzigingen (ze blijven als concept op dit apparaat). Toch wisselen?')) {
            event.target.value = initial.routeId ?? '';
            return;
        }
        window.location.assign(event.target.value ? `${window.location.pathname}?route=${event.target.value}` : window.location.pathname);
    });
    window.addEventListener('beforeunload', (event) => { if (dirty) event.preventDefault(); });

    /* ----- view ----- */
    function fit() {
        const bounds = L.latLngBounds(state.segments.flatMap((s) => s.line));
        if (bounds.isValid()) map.fitBounds(bounds, { padding: [30, 30] });
        else if (reference.getBounds().isValid()) map.fitBounds(reference.getBounds(), { padding: [30, 30] });
        else map.setView([52.2575, 4.557], 9); // Lisse
    }

    function zoomTo(i) {
        const bounds = L.latLngBounds(state.segments[i]?.line ?? []);
        if (bounds.isValid()) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
    }

    /* ----- draft from an earlier session on this device ----- */
    const draft = storage.get(draftKey);
    if (draft && draft.savedAt > initial.updatedAt) {
        const banner = $('[data-draft-banner]');
        banner.hidden = false;
        banner.innerHTML = `<span>Niet-opgeslagen werk van ${new Date(draft.savedAt).toLocaleString('nl-NL')} gevonden op dit apparaat.</span>`;
        const restore = Object.assign(document.createElement('button'), { type: 'button', textContent: 'Herstellen' });
        const discard = Object.assign(document.createElement('button'), { type: 'button', textContent: 'Weggooien' });
        restore.style.cssText = 'padding:.35rem .8rem;border-radius:.5rem;background:#78350f;color:#fff;font-weight:600';
        discard.style.cssText = 'padding:.35rem .8rem;border-radius:.5rem;border:1px solid #78350f;font-weight:600';
        restore.addEventListener('click', () => {
            state = { meta: draft.state.meta, segments: draft.state.segments.map((s) => ({ ...s, line: decode(s.line) })) };
            dirty = true;
            banner.hidden = true;
            writeMeta();
            render();
            fit();
        });
        discard.addEventListener('click', () => { storage.remove(draftKey); banner.hidden = true; });
        banner.append(restore, discard);
    }

    writeMeta();
    render();
    fit();
}
