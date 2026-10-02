// Instagram story for an article (Filament page InstaStory): drawn on a 1080×1920 canvas in the site's style.
// Five designs (polaroid, big photo, journal page, counter, postcard); parts can be switched on and off, the choice
// is remembered per design in this browser. Every design keeps a dashed spot for Instagram's link sticker.
const W = 1080;
const H = 1920;
const C = {
    forest950: '#0e1c13',
    forest900: '#142a1c',
    forest800: '#1c3a26',
    forest700: '#264d33',
    moss600: '#4a6b3a',
    fern300: '#a6cf92',
    olive300: '#c2c07a',
    sand100: '#eee8d8',
    bark: '#8a5a3c',
    paper: '#fbfaf5',
    // Washi tape colours (semi-transparent, like the real thing).
    tapes: ['rgba(194, 192, 122, 0.82)', 'rgba(166, 207, 146, 0.78)', 'rgba(238, 232, 216, 0.85)', 'rgba(214, 160, 140, 0.75)', 'rgba(150, 190, 205, 0.75)'],
};
const FONTS = { display: '"Bricolage Grotesque", sans-serif', hand: 'Caveat, cursive', body: 'Nunito, sans-serif' };
const ALL = ['title', 'excerpt', 'place', 'date', 'km', 'progress', 'note', 'sticker', 'brand'];
const NO_EXCERPT = ALL.filter((part) => part !== 'excerpt');

// Which parts a design can show, and what is on the first time. The site's name ("brand") is off by default:
// the stories are Coen's, not an advert.
const DESIGNS = {
    polaroid: { parts: ALL, defaults: ['title', 'place', 'note', 'sticker'], draw: drawPolaroid },
    photo: { parts: ALL, defaults: ['title', 'place', 'km', 'note', 'sticker'], draw: drawPhoto },
    simple: { parts: NO_EXCERPT, defaults: ['title', 'place', 'sticker'], draw: drawSimple },
    journal: { parts: ALL, defaults: ['title', 'excerpt', 'place', 'date', 'note', 'sticker'], draw: drawJournal },
    quote: { parts: ALL, defaults: ['title', 'excerpt', 'place', 'note', 'sticker'], draw: drawQuote },
    collage: { parts: NO_EXCERPT, defaults: ['title', 'place', 'date', 'note', 'sticker'], draw: drawCollage },
    route: { parts: NO_EXCERPT, defaults: ['title', 'place', 'km', 'progress', 'note', 'sticker'], draw: drawRoute },
    counter: { parts: NO_EXCERPT, defaults: ['title', 'place', 'km', 'progress', 'note', 'sticker'], draw: drawCounter },
    ticket: { parts: NO_EXCERPT, defaults: ['title', 'place', 'date', 'km', 'progress', 'sticker'], draw: drawTicket },
    postcard: { parts: ALL, defaults: ['excerpt', 'place', 'date', 'km', 'note', 'sticker'], draw: drawPostcard },
};

// Flag images (flag-icons, the same as on the site); emoji flags don't show on Windows.
const FLAGS = import.meta.glob('../../node_modules/flag-icons/flags/4x3/*.svg', { eager: true, query: '?url', import: 'default' });

const root = document.querySelector('[data-insta-story]');
if (root) init(root);

const storage = {
    get: () => { try { return JSON.parse(localStorage.getItem('twc-story')) || {}; } catch { return {}; } },
    set: (value) => { try { localStorage.setItem('twc-story', JSON.stringify(value)); } catch { /* full or blocked */ } },
};

async function init(root) {
    const data = JSON.parse(root.dataset.story);
    if (data.kind === 'stats') return initStats(root, data);
    const canvas = root.querySelector('[data-canvas]');
    const localeSelect = root.querySelector('[data-locale]');
    const noteInput = root.querySelector('[data-note]');
    const status = root.querySelector('[data-status]');
    const designSelect = root.querySelector('[data-design]');
    const partInputs = [...root.querySelectorAll('[data-part]')];
    const say = (text) => (status.textContent = text);

    const [cover, flag, ...photos] = await Promise.all([loadImage(data.cover, true), loadFlag(data.flag), ...(data.photos || []).map((url) => loadImage(url, true))]);
    await Promise.all([`800 80px ${FONTS.display}`, `700 80px ${FONTS.hand}`, `800 30px ${FONTS.body}`, `700 30px ${FONTS.body}`].map((font) => document.fonts.load(font).catch(() => {})));

    const texts = () => data.locales[localeSelect.value];
    // Parts this story has data for.
    const available = (part) => ({
        excerpt: !!texts().excerpt,
        place: !!(texts().day || texts().country),
        date: !!texts().date,
        km: !!texts().km,
        progress: data.progress !== null,
    })[part] ?? true;

    const saved = storage.get();
    let design = DESIGNS[saved.design] ? saved.design : 'polaroid';
    const chosen = saved.parts || {};
    let variation = randomVariation();

    const syncInputs = () => {
        designSelect.value = design;
        const selected = chosen[design] || DESIGNS[design].defaults;
        partInputs.forEach((input) => {
            input.checked = selected.includes(input.value);
            input.disabled = !DESIGNS[design].parts.includes(input.value) || !available(input.value);
        });
    };
    const draw = () => {
        const selected = chosen[design] || DESIGNS[design].defaults;
        const on = Object.fromEntries(ALL.map((part) => [part, selected.includes(part) && DESIGNS[design].parts.includes(part) && available(part)]));
        const ctx = canvas.getContext('2d');
        ctx.save();
        ctx.clearRect(0, 0, W, H);
        ctx.textAlign = 'center';
        DESIGNS[design].draw(ctx, { t: { ...texts(), note: noteInput.value || texts().note }, on, cover, flag, progress: data.progress, photos: [cover, ...photos].filter(Boolean), v: variation });
        ctx.restore();
    };
    const remember = () => storage.set({ design, parts: chosen });

    designSelect.addEventListener('change', () => {
        design = designSelect.value;
        variation = randomVariation();
        remember();
        syncInputs();
        draw();
    });
    partInputs.forEach((input) => input.addEventListener('change', () => {
        chosen[design] = partInputs.filter((i) => i.checked).map((i) => i.value);
        remember();
        draw();
    }));
    root.querySelector('[data-tape]').addEventListener('click', () => {
        variation = randomVariation();
        draw();
    });
    localeSelect.addEventListener('change', () => {
        noteInput.value = texts().note;
        syncInputs();
        draw();
    });
    noteInput.addEventListener('input', draw);
    noteInput.value = texts().note;
    syncInputs();
    draw();
    wireExport(root, canvas, () => texts().url, say);
}

// Copy link, download and share buttons (shared by the article and counter stories).
function wireExport(root, canvas, url, say) {
    const file = () => new Promise((resolve) => canvas.toBlob((blob) => resolve(new File([blob], 'travelwithcoen-story.png', { type: 'image/png' })), 'image/png'));
    const download = async () => {
        const link = Object.assign(document.createElement('a'), { href: URL.createObjectURL(await file()), download: 'travelwithcoen-story.png' });
        link.click();
        setTimeout(() => URL.revokeObjectURL(link.href), 1000);
    };

    root.querySelector('[data-copy]').addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(url());
            say('Link gekopieerd: ' + url());
        } catch {
            prompt('Kopieer de link:', url());
        }
    });
    root.querySelector('[data-download]').addEventListener('click', download);
    root.querySelector('[data-share]').addEventListener('click', async () => {
        const shared = await file();
        if (navigator.canShare?.({ files: [shared] })) {
            try {
                await navigator.share({ files: [shared] });
            } catch {
                // cancelled
            }
        } else {
            await download();
            say('Delen kan niet in deze browser: de afbeelding is gedownload.');
        }
    });
}

async function loadImage(src, cors = false) {
    if (!src) return null;
    const image = new Image();
    if (cors) image.crossOrigin = 'anonymous'; // R2 sends CORS headers (php artisan media:cors), so the canvas stays exportable
    image.src = src;
    return image.decode().then(() => image, () => null);
}

// The flag SVGs have no width/height, which some browsers need to draw them on a canvas: add them.
async function loadFlag(code) {
    const url = code && Object.entries(FLAGS).find(([path]) => path.endsWith(`/${code}.svg`))?.[1];
    if (!url) return null;
    try {
        const svg = (await (await fetch(url)).text()).replace('<svg ', '<svg width="640" height="480" ');
        return await loadImage(URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml' })));
    } catch {
        return null;
    }
}

const between = (min, max) => min + Math.random() * (max - min);
const rad = (deg) => (deg * Math.PI) / 180;

// "Andere variatie": new tape and slightly different angles.
function randomVariation() {
    return { tape: randomTape(), tape2: randomTape(), tilt: between(-1.5, 1.5), seed: Math.floor(Math.random() * 1e9) };
}

// A random piece of tape: colour, angle, place, size, stripes and torn ends differ every time.
function randomTape() {
    return {
        color: C.tapes[Math.floor(Math.random() * C.tapes.length)],
        angle: between(-9, 9),
        x: between(-150, 150),
        width: between(220, 330),
        height: between(62, 84),
        stripes: Math.random() < 0.35,
        ends: Array.from({ length: 2 }, () => Array.from({ length: 7 }, () => between(-7, 7))),
    };
}

// Tape centred on (x, y), with its own angle on top of `angle`.
function drawTape(ctx, tape, x, y, angle = 0, scale = 1) {
    const w = tape.width * scale;
    const h = tape.height * scale;
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(rad(tape.angle + angle));
    ctx.beginPath();
    ctx.moveTo(-w / 2, -h / 2);
    ctx.lineTo(w / 2, -h / 2);
    tape.ends[1].forEach((dx, i) => ctx.lineTo(w / 2 + dx, -h / 2 + (h * (i + 1)) / 8)); // torn right end
    ctx.lineTo(w / 2, h / 2);
    ctx.lineTo(-w / 2, h / 2);
    tape.ends[0].forEach((dx, i) => ctx.lineTo(-w / 2 + dx, h / 2 - (h * (i + 1)) / 8)); // torn left end
    ctx.closePath();
    ctx.fillStyle = tape.color;
    ctx.fill();
    if (tape.stripes) {
        ctx.clip();
        ctx.fillStyle = 'rgba(255, 255, 255, 0.28)';
        for (let sx = -w; sx < w; sx += 34) {
            ctx.beginPath();
            ctx.moveTo(sx, -h / 2);
            ctx.lineTo(sx + 14, -h / 2);
            ctx.lineTo(sx + 14 + h, h / 2);
            ctx.lineTo(sx + h, h / 2);
            ctx.fill();
        }
    }
    ctx.restore();
}

// Background gradient with the site's wave (topo) lines.
function waves(ctx, top, bottom, line) {
    const bg = ctx.createLinearGradient(0, 0, 0, H);
    bg.addColorStop(0, top);
    bg.addColorStop(1, bottom);
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);
    ctx.strokeStyle = line;
    ctx.lineWidth = 3;
    for (let i = 0; i < 34; i++) {
        const y0 = i * 62 - 60;
        ctx.beginPath();
        for (let x = -20; x <= W + 20; x += 20) {
            const y = y0 + Math.sin(x / 140 + i * 0.7) * 22 + Math.sin(x / 57 + i) * 6;
            x === -20 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        }
        ctx.stroke();
    }
}

// The cover photo cropped to fill the box; a green "Lisse → Hanoi" card without one.
function photo(ctx, cover, x, y, w, h) {
    if (cover) {
        const scale = Math.max(w / cover.naturalWidth, h / cover.naturalHeight);
        const sw = w / scale;
        const sh = h / scale;
        ctx.drawImage(cover, (cover.naturalWidth - sw) / 2, (cover.naturalHeight - sh) / 2, sw, sh, x, y, w, h);
        return;
    }
    ctx.save();
    ctx.fillStyle = C.forest800;
    ctx.fillRect(x, y, w, h);
    ctx.fillStyle = C.fern300;
    ctx.textAlign = 'center';
    fitFont(ctx, 'Lisse → Hanoi', w * 0.8, `700 {}px ${FONTS.hand}`, Math.min(130, h / 3));
    ctx.fillText('Lisse → Hanoi', x + w / 2, y + h / 2 + 30);
    ctx.restore();
}

function drawFlag(ctx, flag, x, y, w) {
    if (!flag) return;
    ctx.save();
    ctx.shadowColor = 'rgba(0, 0, 0, 0.3)';
    ctx.shadowBlur = 8;
    ctx.shadowOffsetY = 3;
    ctx.drawImage(flag, x, y, w, w * 0.75);
    ctx.restore();
}

// Largest font size (pattern with "{}") at which the text fits.
function fitFont(ctx, text, maxWidth, pattern, size, min = 24) {
    ctx.font = pattern.replace('{}', size);
    while (size > min && ctx.measureText(text).width > maxWidth) {
        size -= 2;
        ctx.font = pattern.replace('{}', size);
    }
    return size;
}

// Word wrap; the last line gets "…" when the text is longer than maxLines.
function wrap(ctx, text, maxWidth, maxLines = 99) {
    const lines = [];
    let line = '';
    for (const word of (text || '').split(/\s+/).filter(Boolean)) {
        const test = line ? `${line} ${word}` : word;
        if (ctx.measureText(test).width > maxWidth && line) {
            lines.push(line);
            line = word;
        } else {
            line = test;
        }
    }
    if (line) lines.push(line);
    if (lines.length > maxLines) {
        lines.length = maxLines;
        let last = lines[maxLines - 1];
        while (last && ctx.measureText(last + '…').width > maxWidth) last = last.replace(/\s*\S+$/, '');
        lines[maxLines - 1] = last.replace(/[\s.,;:!?]+$/, '') + '…';
    }
    return lines;
}

// Shrinks the font until the text fits in maxLines (then cuts it off with "…"). @returns {lines, size}
function fitLines(ctx, text, maxWidth, maxLines, pattern, size, min) {
    for (; size > min; size -= 2) {
        ctx.font = pattern.replace('{}', size);
        if (wrap(ctx, text, maxWidth).length <= maxLines) break;
    }
    ctx.font = pattern.replace('{}', size);
    return { lines: wrap(ctx, text, maxWidth, maxLines), size };
}

const place = (t, on) => [on.place && t.day, on.place && t.country, on.date && t.date].filter(Boolean).join(' · ');

// "LISSE ·······●━━━ HANOI": how far along the way to Hanoi.
function progressLine(ctx, x, y, w, p, col) {
    ctx.save();
    ctx.font = `800 26px ${FONTS.body}`;
    ctx.letterSpacing = '5px';
    ctx.fillStyle = col.label;
    ctx.textAlign = 'left';
    ctx.fillText('LISSE', x, y + 9);
    const left = ctx.measureText('LISSE').width;
    ctx.textAlign = 'right';
    ctx.fillText('HANOI', x + w, y + 9);
    const right = ctx.measureText('HANOI').width;
    ctx.letterSpacing = '0px';
    const a = x + left + 26;
    const b = x + w - right - 26;
    const m = a + (b - a) * p;
    ctx.lineCap = 'round';
    ctx.setLineDash([2, 16]);
    ctx.strokeStyle = col.track;
    ctx.lineWidth = 7;
    ctx.beginPath();
    ctx.moveTo(a, y);
    ctx.lineTo(b, y);
    ctx.stroke();
    ctx.setLineDash([]);
    ctx.strokeStyle = col.done;
    ctx.lineWidth = 8;
    ctx.beginPath();
    ctx.moveTo(a, y);
    ctx.lineTo(m, y);
    ctx.stroke();
    ctx.fillStyle = col.done;
    ctx.strokeStyle = col.ring;
    ctx.lineWidth = 6;
    ctx.beginPath();
    ctx.arc(m, y, 15, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.restore();
}

// Handwritten note with an arrow down to the dashed spot for Instagram's link sticker (box top at boxY).
function linkSpot(ctx, s, boxY, col) {
    const { t, on } = s;
    ctx.save();
    if (col.shadow) {
        ctx.shadowColor = col.shadow;
        ctx.shadowBlur = 14;
    }
    ctx.textAlign = 'center';
    if (on.note && on.sticker) {
        ctx.save();
        ctx.translate(250, boxY - 80);
        ctx.rotate(rad(-6));
        ctx.fillStyle = col.note;
        fitFont(ctx, t.note, 420, `700 {}px ${FONTS.hand}`, 76, 40);
        ctx.fillText(t.note, 0, 0);
        ctx.restore();
        ctx.strokeStyle = col.note;
        ctx.lineWidth = 6;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.beginPath();
        ctx.moveTo(470, boxY - 115);
        ctx.bezierCurveTo(525, boxY - 125, 545, boxY - 60, 520, boxY - 10);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(498, boxY - 30);
        ctx.lineTo(520, boxY - 6);
        ctx.lineTo(542, boxY - 32);
        ctx.stroke();
    } else if (on.note) {
        ctx.translate(W / 2, boxY + 70);
        ctx.rotate(rad(-4));
        ctx.fillStyle = col.note;
        fitFont(ctx, t.note, 900, `700 {}px ${FONTS.hand}`, 96, 40);
        ctx.fillText(t.note, 0, 0);
    }
    ctx.restore();

    if (on.sticker) {
        ctx.save();
        ctx.setLineDash([16, 14]);
        ctx.strokeStyle = col.dash;
        ctx.lineWidth = 4;
        ctx.beginPath();
        ctx.roundRect(W / 2 - 280, boxY, 560, 120, 60);
        ctx.stroke();
        ctx.setLineDash([]);
        ctx.fillStyle = col.text;
        ctx.textAlign = 'center';
        ctx.font = `800 34px ${FONTS.body}`;
        ctx.fillText(t.sticker, W / 2, boxY + 72);
        ctx.restore();
    }
}

function brand(ctx, y, col, subtitle = true) {
    ctx.save();
    ctx.textAlign = 'center';
    ctx.fillStyle = col.name;
    ctx.font = `800 66px ${FONTS.display}`;
    ctx.fillText('Travel with Coen', W / 2, y);
    if (subtitle) {
        ctx.fillStyle = col.sub;
        ctx.font = `800 28px ${FONTS.body}`;
        ctx.letterSpacing = '8px';
        ctx.fillText('LISSE  ·····  HANOI', W / 2, y + 52);
    }
    ctx.restore();
}

function site(ctx, t, color) {
    ctx.save();
    ctx.textAlign = 'center';
    ctx.fillStyle = color;
    ctx.font = `800 32px ${FONTS.body}`;
    ctx.fillText(t.site, W / 2, H - 70);
    ctx.restore();
}

const DARK = { note: C.olive300, dash: 'rgba(255, 255, 255, 0.7)', text: 'rgba(255, 255, 255, 0.6)' };
const INK = { note: C.bark, dash: 'rgba(28, 58, 38, 0.55)', text: 'rgba(28, 58, 38, 0.6)' };

// 1. Polaroid: the cover in a taped polaroid on the green waves, title underneath.
function drawPolaroid(ctx, s) {
    const { t, on, v } = s;
    waves(ctx, C.forest700, C.forest950, 'rgba(166, 207, 146, 0.09)');
    if (on.brand) brand(ctx, 170, { name: '#ffffff', sub: C.fern300 }, !on.progress);
    if (on.progress) progressLine(ctx, 150, on.brand ? 222 : 200, 780, s.progress, { label: C.fern300, track: 'rgba(166, 207, 146, 0.5)', done: C.fern300, ring: C.forest800 });

    const pw = 820;
    const size = 740;
    const ph = size + 40 + 150;
    ctx.save();
    ctx.translate(W / 2, 300 + ph / 2);
    ctx.rotate(rad(-2.5 + v.tilt));
    ctx.shadowColor = 'rgba(0, 0, 0, 0.45)';
    ctx.shadowBlur = 40;
    ctx.shadowOffsetY = 18;
    ctx.fillStyle = C.paper;
    ctx.fillRect(-pw / 2, -ph / 2, pw, ph);
    ctx.shadowColor = 'transparent';
    photo(ctx, s.cover, -size / 2, -ph / 2 + 40, size, size);
    const caption = place(t, on);
    const flagged = on.place && s.flag;
    ctx.fillStyle = C.forest800;
    ctx.textAlign = 'center';
    fitFont(ctx, caption, flagged ? 600 : size, `700 {}px ${FONTS.hand}`, 64, 30);
    ctx.fillText(caption, flagged ? -45 : 0, ph / 2 - 52);
    if (flagged) {
        ctx.save();
        ctx.translate(pw / 2 - 120, ph / 2 - 112);
        ctx.rotate(rad(8));
        drawFlag(ctx, s.flag, 0, 0, 88);
        ctx.restore();
    }
    drawTape(ctx, v.tape, v.tape.x, -ph / 2 + v.tape.height / 2 - 34);
    // Kilometres on a little sand label stuck over the photo's top-left corner.
    if (on.km) {
        ctx.save();
        ctx.translate(-pw / 2 + 30, -ph / 2 + 110);
        ctx.rotate(rad(-8));
        ctx.font = `700 56px ${FONTS.hand}`;
        const w = ctx.measureText(t.km).width + 56;
        ctx.shadowColor = 'rgba(0, 0, 0, 0.3)';
        ctx.shadowBlur = 12;
        ctx.shadowOffsetY = 5;
        ctx.fillStyle = C.sand100;
        ctx.beginPath();
        ctx.roundRect(0, -46, w, 76, 10);
        ctx.fill();
        ctx.shadowColor = 'transparent';
        ctx.fillStyle = C.bark;
        ctx.textAlign = 'left';
        ctx.fillText(t.km, 28, 12);
        ctx.restore();
    }
    ctx.restore();

    let y = 1340;
    if (on.title) {
        ctx.fillStyle = '#ffffff';
        const { lines, size } = fitLines(ctx, t.title, 920, 2, `800 {}px ${FONTS.display}`, 74, 54);
        lines.forEach((line, i) => ctx.fillText(line, W / 2, y + i * size * 1.15));
        y += (lines.length - 1) * size * 1.15;
    }
    if (on.excerpt) {
        ctx.fillStyle = C.sand100;
        ctx.font = `700 38px ${FONTS.body}`;
        const lines = wrap(ctx, t.excerpt, 900, 2);
        lines.forEach((line, i) => ctx.fillText(line, W / 2, y + 66 + i * 50));
        y += 66 + (lines.length - 1) * 50;
    }
    linkSpot(ctx, s, Math.min(Math.max(y + 190, 1610), 1700), DARK);
    if (on.brand) site(ctx, t, C.fern300);
}

// 2. Big photo: the cover fills the story, text on a dark fade at the bottom.
function drawPhoto(ctx, s) {
    const { t, on } = s;
    if (s.cover) photo(ctx, s.cover, 0, 0, W, H);
    else waves(ctx, C.forest700, C.forest950, 'rgba(166, 207, 146, 0.09)');

    const top = ctx.createLinearGradient(0, 0, 0, 420);
    top.addColorStop(0, 'rgba(14, 28, 19, 0.75)');
    top.addColorStop(1, 'rgba(14, 28, 19, 0)');
    ctx.fillStyle = top;
    ctx.fillRect(0, 0, W, 420);
    const bottom = ctx.createLinearGradient(0, H * 0.38, 0, H);
    bottom.addColorStop(0, 'rgba(14, 28, 19, 0)');
    bottom.addColorStop(0.45, 'rgba(14, 28, 19, 0.7)');
    bottom.addColorStop(1, 'rgba(14, 28, 19, 0.95)');
    ctx.fillStyle = bottom;
    ctx.fillRect(0, H * 0.38, W, H * 0.62);

    ctx.textAlign = 'left';
    ctx.fillStyle = '#ffffff';
    ctx.font = `800 54px ${FONTS.display}`;
    if (on.brand) ctx.fillText('Travel with Coen', 80, 180);
    if (on.progress) progressLine(ctx, 80, on.brand ? 245 : 200, 920, s.progress, { label: C.fern300, track: 'rgba(255, 255, 255, 0.55)', done: C.fern300, ring: C.forest900 });

    // Bottom block, built upwards from above the sticker spot.
    const boxY = H - 330;
    const end = on.sticker ? boxY - (on.note ? 200 : 60) : on.note ? boxY - 20 : H - 180;
    const blocks = [];
    const caption = place(t, on);
    if (caption) {
        ctx.font = `800 36px ${FONTS.body}`;
        const flagW = on.place && s.flag ? 56 : 0;
        const w = Math.min(ctx.measureText(caption).width + 64 + (flagW ? flagW + 16 : 0), 920);
        blocks.push({ h: 72, gap: 34, draw: (y) => {
            ctx.fillStyle = C.fern300;
            ctx.beginPath();
            ctx.roundRect(80, y, w, 72, 36);
            ctx.fill();
            if (flagW) drawFlag(ctx, s.flag, 112, y + 15, flagW);
            ctx.fillStyle = C.forest950;
            ctx.font = `800 36px ${FONTS.body}`;
            ctx.fillText(caption, 112 + (flagW ? flagW + 16 : 0), y + 49, w - 64 - (flagW ? flagW + 16 : 0));
        } });
    }
    if (on.title) {
        ctx.font = `800 84px ${FONTS.display}`;
        const lines = wrap(ctx, t.title, 920, 4);
        blocks.push({ h: lines.length * 94, gap: 24, draw: (y) => {
            ctx.fillStyle = '#ffffff';
            ctx.font = `800 84px ${FONTS.display}`;
            lines.forEach((line, i) => ctx.fillText(line, 80, y + 76 + i * 94));
        } });
    }
    if (on.excerpt) {
        ctx.font = `700 40px ${FONTS.body}`;
        const lines = wrap(ctx, t.excerpt, 920, 3);
        blocks.push({ h: lines.length * 54, gap: 24, draw: (y) => {
            ctx.fillStyle = C.sand100;
            ctx.font = `700 40px ${FONTS.body}`;
            lines.forEach((line, i) => ctx.fillText(line, 80, y + 42 + i * 54));
        } });
    }
    if (on.km) {
        blocks.push({ h: 70, gap: 24, draw: (y) => {
            ctx.fillStyle = C.olive300;
            ctx.font = `700 76px ${FONTS.hand}`;
            ctx.fillText(t.km, 80, y + 60);
        } });
    }
    const total = blocks.reduce((sum, b, i) => sum + b.h + (i ? b.gap : 0), 0);
    let y = end - total;
    blocks.forEach((b, i) => {
        if (i) y += b.gap;
        b.draw(y);
        y += b.h;
    });

    linkSpot(ctx, s, boxY, DARK);
    if (on.brand) site(ctx, t, C.fern300);
}

// 3. Journal: a lined notebook page with a taped photo, a stamp and everything handwritten.
function drawJournal(ctx, s) {
    const { t, on, v } = s;
    ctx.fillStyle = '#f6f1e3';
    ctx.fillRect(0, 0, W, H);
    ctx.strokeStyle = 'rgba(120, 160, 110, 0.3)';
    ctx.lineWidth = 2;
    for (let y = 330; y < H - 140; y += 66) {
        ctx.beginPath();
        ctx.moveTo(0, y);
        ctx.lineTo(W, y);
        ctx.stroke();
    }
    ctx.strokeStyle = 'rgba(214, 120, 100, 0.4)';
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.moveTo(130, 0);
    ctx.lineTo(130, H);
    ctx.stroke();

    ctx.textAlign = 'right';
    ctx.fillStyle = C.moss600;
    ctx.font = `800 26px ${FONTS.body}`;
    ctx.letterSpacing = '6px';
    if (on.brand) ctx.fillText('TRAVEL WITH COEN', W - 70, 190);
    ctx.letterSpacing = '0px';
    if (on.date) {
        ctx.save();
        ctx.textAlign = 'left';
        ctx.translate(170, 250);
        ctx.rotate(rad(-2));
        ctx.fillStyle = C.forest800;
        ctx.font = `700 64px ${FONTS.hand}`;
        ctx.fillText(t.date, 0, 0);
        ctx.restore();
    }
    if (on.progress) progressLine(ctx, 170, 300, 840, s.progress, { label: C.moss600, track: 'rgba(28, 58, 38, 0.35)', done: C.moss600, ring: '#f6f1e3' });

    // Taped photo, a little crooked.
    const size = 600;
    ctx.save();
    ctx.translate(620, 380 + size / 2 + 22);
    ctx.rotate(rad(3 + v.tilt));
    ctx.shadowColor = 'rgba(0, 0, 0, 0.3)';
    ctx.shadowBlur = 30;
    ctx.shadowOffsetY = 12;
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(-size / 2 - 22, -size / 2 - 22, size + 44, size + 44);
    ctx.shadowColor = 'transparent';
    photo(ctx, s.cover, -size / 2, -size / 2, size, size);
    drawTape(ctx, v.tape, -size / 2 + 20, -size / 2 - 10, -38, 0.8);
    drawTape(ctx, v.tape2, size / 2 - 20, size / 2 + 10, -38, 0.8);
    ctx.restore();

    // Round stamp with the day and the country.
    if (on.place) {
        ctx.save();
        ctx.translate(235, 895); // over the photo's bottom-left corner
        ctx.rotate(rad(-12));
        ctx.strokeStyle = 'rgba(138, 90, 60, 0.85)';
        ctx.fillStyle = 'rgba(246, 241, 227, 0.85)';
        ctx.lineWidth = 6;
        ctx.beginPath();
        ctx.arc(0, 0, 130, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
        ctx.setLineDash([6, 8]);
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.arc(0, 0, 112, 0, Math.PI * 2);
        ctx.stroke();
        ctx.setLineDash([]);
        ctx.fillStyle = 'rgba(138, 90, 60, 0.95)';
        ctx.textAlign = 'center';
        if (s.flag) drawFlag(ctx, s.flag, -30, -88, 60);
        if (t.day) {
            fitFont(ctx, t.day, 190, `800 {}px ${FONTS.display}`, 54);
            ctx.fillText(t.day, 0, t.country ? 20 : 30);
        }
        if (t.country) {
            ctx.letterSpacing = '3px';
            fitFont(ctx, t.country.toUpperCase(), 190, `800 {}px ${FONTS.body}`, 26, 16);
            ctx.fillText(t.country.toUpperCase(), 0, t.day ? 62 : 20);
            ctx.letterSpacing = '0px';
        }
        ctx.restore();
    }

    const boxY = 1660;
    const limit = on.note && on.sticker ? boxY - 200 : on.note || on.sticker ? boxY - 40 : H - 160;
    ctx.textAlign = 'left';
    let y = 1120; // baselines sit on the ruled lines (330 + 66n − 2)
    if (on.title) {
        ctx.fillStyle = C.forest900;
        ctx.font = `700 78px ${FONTS.hand}`;
        const lines = wrap(ctx, t.title, 860, on.excerpt ? 2 : 3);
        lines.forEach((line, i) => ctx.fillText(line, 170, y + i * 66));
        y += lines.length * 66;
    }
    if (on.excerpt) {
        ctx.fillStyle = C.moss600;
        ctx.font = `700 52px ${FONTS.hand}`;
        const room = Math.max(1, Math.floor((limit - (on.km ? 90 : 0) - y + 40) / 66));
        const lines = wrap(ctx, t.excerpt, 860, room);
        lines.forEach((line, i) => ctx.fillText(line, 170, y + i * 66));
        y += lines.length * 66;
    }
    if (on.km) {
        ctx.fillStyle = C.bark;
        ctx.font = `700 64px ${FONTS.hand}`;
        ctx.fillText(t.km, 170, y + 10);
        const w = ctx.measureText(t.km).width;
        ctx.strokeStyle = C.bark;
        ctx.lineWidth = 4;
        ctx.lineCap = 'round';
        ctx.beginPath();
        for (let x = 0; x <= w; x += 10) ctx.lineTo(170 + x, y + 30 + Math.sin(x / 12) * 5);
        ctx.stroke();
    }

    linkSpot(ctx, s, boxY, INK);
    if (on.brand) site(ctx, t, C.moss600);
}

// 4. Counter: big day number, kilometres and the way to Hanoi, with a round photo.
function drawCounter(ctx, s) {
    const { t, on, v } = s;
    waves(ctx, C.forest800, C.forest950, 'rgba(166, 207, 146, 0.08)');
    if (on.brand) brand(ctx, 170, { name: '#ffffff', sub: C.fern300 });
    ctx.textAlign = 'center';

    if (on.place && t.day) {
        ctx.fillStyle = '#ffffff';
        fitFont(ctx, t.day, 960, `800 {}px ${FONTS.display}`, 200);
        ctx.fillText(t.day, W / 2, 480);
    } else if (on.date) {
        ctx.fillStyle = C.olive300;
        fitFont(ctx, t.date, 940, `700 {}px ${FONTS.hand}`, 130);
        ctx.fillText(t.date, W / 2, 470);
    } else {
        ctx.fillStyle = C.olive300;
        ctx.font = `700 130px ${FONTS.hand}`;
        ctx.fillText('Lisse → Hanoi', W / 2, 470);
    }
    const sub = [on.place && t.country, on.place && t.day && on.date && t.date].filter(Boolean).join(' · ');
    if (sub) {
        ctx.font = `800 46px ${FONTS.body}`;
        const flagW = on.place && t.country && s.flag ? 64 : 0;
        const w = ctx.measureText(sub).width + (flagW ? flagW + 20 : 0);
        if (flagW) drawFlag(ctx, s.flag, W / 2 - w / 2, 527, flagW);
        ctx.fillStyle = C.fern300;
        ctx.textAlign = 'left';
        ctx.fillText(sub, W / 2 - w / 2 + (flagW ? flagW + 20 : 0), 567);
        ctx.textAlign = 'center';
    }

    // Round photo with a white rim and tape.
    const r = 150;
    ctx.save();
    ctx.translate(W / 2, 790);
    ctx.shadowColor = 'rgba(0, 0, 0, 0.45)';
    ctx.shadowBlur = 36;
    ctx.shadowOffsetY = 14;
    ctx.fillStyle = C.paper;
    ctx.beginPath();
    ctx.arc(0, 0, r + 16, 0, Math.PI * 2);
    ctx.fill();
    ctx.shadowColor = 'transparent';
    ctx.save();
    ctx.beginPath();
    ctx.arc(0, 0, r, 0, Math.PI * 2);
    ctx.clip();
    photo(ctx, s.cover, -r, -r, r * 2, r * 2);
    ctx.restore();
    drawTape(ctx, v.tape, r * 0.75, -r * 0.8, 40, 0.7);
    ctx.restore();

    let y = 980;
    if (on.km) {
        ctx.fillStyle = '#ffffff';
        ctx.font = `800 150px ${FONTS.display}`;
        ctx.fillText(t.km_number, W / 2, y + 120);
        ctx.fillStyle = C.fern300;
        ctx.letterSpacing = '6px';
        ctx.font = `800 34px ${FONTS.body}`;
        ctx.fillText(t.km_label.toUpperCase(), W / 2, y + 172);
        ctx.letterSpacing = '0px';
        y += 200;
    }
    if (on.progress) {
        progressLine(ctx, 120, y + 50, 840, s.progress, { label: C.fern300, track: 'rgba(166, 207, 146, 0.5)', done: C.olive300, ring: C.forest900 });
        ctx.fillStyle = C.olive300;
        ctx.font = `700 54px ${FONTS.hand}`;
        ctx.fillText(t.progress, W / 2, y + 125);
        y += 145;
    }
    if (on.title) {
        ctx.fillStyle = '#ffffff';
        ctx.font = `800 58px ${FONTS.display}`;
        const room = Math.max(1, Math.min(2, Math.floor((1470 - y) / 66) + 1));
        const lines = wrap(ctx, t.title, 900, room);
        lines.forEach((line, i) => ctx.fillText(line, W / 2, y + 70 + i * 68));
        y += 70 + (lines.length - 1) * 68;
    }
    linkSpot(ctx, s, Math.min(Math.max(y + 190, 1640), 1690), DARK);
    if (on.brand) site(ctx, t, C.fern300);
}

// 5. Postcard: photo on the front, handwritten message, stamp and postmark on the back half.
function drawPostcard(ctx, s) {
    const { t, on, v } = s;
    waves(ctx, '#e3efd6', '#b9d6a6', 'rgba(28, 58, 38, 0.08)');
    ctx.textAlign = 'center';
    ctx.fillStyle = C.forest900;
    ctx.font = `800 60px ${FONTS.display}`;
    if (on.brand) ctx.fillText('Travel with Coen', W / 2, 165);
    ctx.fillStyle = C.moss600;
    ctx.font = `700 54px ${FONTS.hand}`;
    ctx.font = `700 ${on.brand ? 54 : 72}px ${FONTS.hand}`;
    if (on.place && t.greeting) ctx.fillText(t.greeting, W / 2, on.brand ? 225 : 200);

    const cw = 960;
    const ch = 1200;
    ctx.save();
    ctx.translate(W / 2, 290 + ch / 2);
    ctx.rotate(rad(-2 + v.tilt));
    ctx.shadowColor = 'rgba(20, 42, 28, 0.4)';
    ctx.shadowBlur = 40;
    ctx.shadowOffsetY = 16;
    ctx.fillStyle = C.paper;
    ctx.fillRect(-cw / 2, -ch / 2, cw, ch);
    ctx.shadowColor = 'transparent';
    photo(ctx, s.cover, -cw / 2 + 24, -ch / 2 + 24, cw - 48, 600);

    const top = -ch / 2 + 24 + 600 + 40; // back half starts here
    const bottom = ch / 2 - 36;
    ctx.strokeStyle = 'rgba(28, 58, 38, 0.18)';
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.moveTo(40, top + 20);
    ctx.lineTo(40, bottom - 10);
    ctx.stroke();

    // Message (left half).
    ctx.textAlign = 'left';
    let y = top + 50;
    const mx = -cw / 2 + 44;
    const mw = 400;
    if (on.date) {
        ctx.fillStyle = C.moss600;
        ctx.font = `700 40px ${FONTS.hand}`;
        ctx.fillText(t.date, mx, y);
        y += 62;
    }
    if (on.title) {
        ctx.fillStyle = C.forest900;
        ctx.font = `700 56px ${FONTS.hand}`;
        const lines = wrap(ctx, t.title, mw, 3);
        lines.forEach((line, i) => ctx.fillText(line, mx, y + i * 58));
        y += lines.length * 58 + 10;
    }
    if (on.excerpt) {
        ctx.fillStyle = C.forest700;
        ctx.font = `700 44px ${FONTS.hand}`;
        const lines = wrap(ctx, t.excerpt, mw, Math.max(1, Math.floor((bottom - y) / 48)));
        lines.forEach((line, i) => ctx.fillText(line, mx, y + i * 48));
        y += lines.length * 48;
    }
    if (!on.title && !on.excerpt) {
        ctx.fillStyle = C.forest900;
        ctx.font = `700 64px ${FONTS.hand}`;
        ctx.fillText(t.note, mx, y + 20);
    }

    // Stamp (top right) with perforated edges.
    const sx = cw / 2 - 44 - 170;
    const sy = top + 10;
    ctx.fillStyle = '#ffffff';
    ctx.shadowColor = 'rgba(0, 0, 0, 0.2)';
    ctx.shadowBlur = 6;
    ctx.fillRect(sx, sy, 170, 210);
    ctx.shadowColor = 'transparent';
    ctx.fillStyle = C.paper;
    for (let i = 0; i <= 10; i++) {
        [[sx + i * 17, sy], [sx + i * 17, sy + 210]].forEach(([x, yy]) => { ctx.beginPath(); ctx.arc(x, yy, 6, 0, Math.PI * 2); ctx.fill(); });
    }
    for (let i = 0; i <= 12; i++) {
        [[sx, sy + i * 17.5], [sx + 170, sy + i * 17.5]].forEach(([x, yy]) => { ctx.beginPath(); ctx.arc(x, yy, 6, 0, Math.PI * 2); ctx.fill(); });
    }
    ctx.save();
    ctx.beginPath();
    ctx.rect(sx + 14, sy + 14, 142, 182);
    ctx.clip();
    if (on.place && s.flag) {
        ctx.drawImage(s.flag, sx + 14 - 50, sy + 14, 242, 182);
    } else {
        photo(ctx, s.cover, sx + 14, sy + 14, 142, 182);
    }
    ctx.restore();

    // Postmark over the stamp: circle with the day, and wavy cancellation lines.
    ctx.save();
    ctx.translate(sx - 10, sy + 170);
    ctx.rotate(rad(-14));
    ctx.strokeStyle = 'rgba(138, 90, 60, 0.75)';
    ctx.fillStyle = 'rgba(138, 90, 60, 0.85)';
    ctx.lineWidth = 4;
    ctx.beginPath();
    ctx.arc(0, 0, 82, 0, Math.PI * 2);
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(0, 0, 70, 0, Math.PI * 2);
    ctx.stroke();
    ctx.textAlign = 'center';
    const mark = on.place && t.day ? t.day : 'Lisse';
    fitFont(ctx, mark.toUpperCase(), 120, `800 {}px ${FONTS.body}`, 30, 16);
    ctx.fillText(mark.toUpperCase(), 0, 11);
    for (let i = 0; i < 3; i++) {
        ctx.beginPath();
        for (let x = 90; x <= 260; x += 6) ctx.lineTo(x, -24 + i * 24 + Math.sin(x / 14) * 7);
        ctx.stroke();
    }
    ctx.restore();

    // Address lines: the site, kilometres and the way to Hanoi.
    const ax = 80;
    const aw = cw / 2 - 44 - ax;
    const lines = [on.brand && t.site, on.km && t.km, on.progress && t.progress].filter(Boolean);
    [top + 340, top + 410, top + 480].forEach((ly, i) => {
        ctx.strokeStyle = 'rgba(28, 58, 38, 0.25)';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(ax, ly);
        ctx.lineTo(ax + aw, ly);
        ctx.stroke();
        if (lines[i]) {
            ctx.fillStyle = C.forest800;
            ctx.textAlign = 'left';
            fitFont(ctx, lines[i], aw - 10, `700 {}px ${FONTS.hand}`, 46, 28);
            ctx.fillText(lines[i], ax + 6, ly - 12);
        }
    });

    drawTape(ctx, v.tape, -cw / 2 + 40, -ch / 2 + 10, -40, 0.75);
    drawTape(ctx, v.tape2, cw / 2 - 40, -ch / 2 + 10, 40, 0.75);
    ctx.restore();

    linkSpot(ctx, s, 1660, { ...INK, note: C.forest800 });
    if (on.brand) site(ctx, t, C.forest800);
}

// Repeatable randomness (specks, tape spots) so the picture doesn't change while typing; new with "Andere variatie".
function rng(seed) {
    return () => {
        seed = (seed + 0x6d2b79f5) | 0;
        let r = Math.imul(seed ^ (seed >>> 15), 1 | seed);
        r = (r + Math.imul(r ^ (r >>> 7), 61 | r)) ^ r;
        return ((r ^ (r >>> 14)) >>> 0) / 4294967296;
    };
}

// A strip of paper with torn top and bottom edges.
function tornRect(ctx, x, y, w, h, random) {
    ctx.beginPath();
    ctx.moveTo(x, y);
    for (let i = 1; i <= 40; i++) ctx.lineTo(x + (w * i) / 40, y + (random() - 0.5) * 9);
    ctx.lineTo(x + w, y + h);
    for (let i = 39; i >= 0; i--) ctx.lineTo(x + (w * i) / 40, y + h + (random() - 0.5) * 9);
    ctx.closePath();
}

// Round photo with a white rim and a bit of tape, centred on (x, y).
function roundPhoto(ctx, image, x, y, r, angle, tape) {
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(rad(angle));
    ctx.shadowColor = 'rgba(0, 0, 0, 0.35)';
    ctx.shadowBlur = 30;
    ctx.shadowOffsetY = 10;
    ctx.fillStyle = C.paper;
    ctx.beginPath();
    ctx.arc(0, 0, r + 12, 0, Math.PI * 2);
    ctx.fill();
    ctx.shadowColor = 'transparent';
    ctx.save();
    ctx.beginPath();
    ctx.arc(0, 0, r, 0, Math.PI * 2);
    ctx.clip();
    photo(ctx, image, -r, -r, r * 2, r * 2);
    ctx.restore();
    if (tape) drawTape(ctx, tape, r * 0.7, -r * 0.8, 40, 0.6);
    ctx.restore();
}

// 6. Just the photo: the whole story is the photo, with one handwritten line on it, like Instagram's own text.
function drawSimple(ctx, s) {
    const { t, on } = s;
    if (s.cover) photo(ctx, s.cover, 0, 0, W, H);
    else waves(ctx, C.forest700, C.forest950, 'rgba(166, 207, 146, 0.09)');
    const fade = ctx.createLinearGradient(0, H * 0.5, 0, H);
    fade.addColorStop(0, 'rgba(0, 0, 0, 0)');
    fade.addColorStop(1, 'rgba(0, 0, 0, 0.5)');
    ctx.fillStyle = fade;
    ctx.fillRect(0, H * 0.5, W, H * 0.5);
    if (on.progress) progressLine(ctx, 80, 200, 920, s.progress, { label: '#ffffff', track: 'rgba(255, 255, 255, 0.7)', done: '#ffffff', ring: 'rgba(0, 0, 0, 0.35)' });

    const boxY = H - 330;
    let bottom = on.sticker ? boxY - (on.note ? 190 : 50) : on.note ? boxY - 10 : H - 170;
    ctx.save();
    ctx.shadowColor = 'rgba(0, 0, 0, 0.6)';
    ctx.shadowBlur = 18;
    ctx.shadowOffsetY = 3;
    ctx.textAlign = 'left';
    ctx.fillStyle = '#ffffff';
    const sub = [place(t, on), on.km && t.km].filter(Boolean).join(' · ');
    if (sub) {
        ctx.font = `700 40px ${FONTS.body}`;
        ctx.fillText(sub, 80, bottom, 920);
        bottom -= 76;
    }
    if (on.title) {
        const { lines, size } = fitLines(ctx, t.title, 920, 3, `700 {}px ${FONTS.hand}`, 116, 70);
        lines.slice().reverse().forEach((line, i) => ctx.fillText(line, 80, bottom - i * size * 0.98));
    }
    ctx.restore();
    linkSpot(ctx, s, boxY, { note: '#ffffff', dash: 'rgba(255, 255, 255, 0.85)', text: 'rgba(255, 255, 255, 0.85)', shadow: 'rgba(0, 0, 0, 0.5)' });
    if (on.brand) site(ctx, t, '#ffffff');
}

// 7. Quote: Coen's own words, big and handwritten, with a small round photo.
function drawQuote(ctx, s) {
    const { t, on, v } = s;
    waves(ctx, '#f4ecdb', '#e7dbc0', 'rgba(138, 90, 60, 0.07)');
    roundPhoto(ctx, s.cover, 830, 370, 130, 6 + v.tilt, v.tape);
    ctx.textAlign = 'left';
    ctx.fillStyle = 'rgba(138, 90, 60, 0.3)';
    ctx.font = `800 420px ${FONTS.display}`;
    ctx.fillText('“', 60, 600);

    const quote = on.excerpt && t.excerpt ? t.excerpt : on.title ? t.title : t.note;
    const by = on.title && on.excerpt;
    const where = place(t, on);
    const boxY = 1660;
    const limit = (on.note && on.sticker ? boxY - 210 : boxY - 70) - (by ? 140 : 0) - (where ? 90 : 0);
    const top = 660;
    let size = 108;
    for (; size > 56; size -= 4) {
        ctx.font = `700 ${size}px ${FONTS.hand}`;
        if (top + (wrap(ctx, quote, 860).length - 1) * size * 1.05 <= limit) break;
    }
    ctx.font = `700 ${size}px ${FONTS.hand}`;
    const lines = wrap(ctx, quote, 860, Math.max(1, Math.floor((limit - top) / (size * 1.05)) + 1));
    ctx.fillStyle = C.forest900;
    lines.forEach((line, i) => ctx.fillText(line, 110, top + i * size * 1.05));
    let y = top + (lines.length - 1) * size * 1.05;
    if (by) {
        ctx.fillStyle = C.moss600;
        ctx.font = `800 38px ${FONTS.body}`;
        const titleLines = wrap(ctx, `— ${t.title}`, 860, 2);
        titleLines.forEach((line, i) => ctx.fillText(line, 110, y + 95 + i * 50));
        y += 95 + (titleLines.length - 1) * 50;
    }
    if (where) {
        ctx.fillStyle = C.bark;
        ctx.font = `700 54px ${FONTS.hand}`;
        ctx.fillText(where, 110, y + 90, 860);
    }
    linkSpot(ctx, s, boxY, INK);
    if (on.brand) site(ctx, t, C.moss600);
}

// 8. Collage: up to four photos from the story taped onto brown paper, title on a torn strip.
function drawCollage(ctx, s) {
    const { t, on, v } = s;
    const random = rng(v.seed);
    ctx.fillStyle = '#cfb48c';
    ctx.fillRect(0, 0, W, H);
    for (let i = 0; i < 1600; i++) {
        ctx.fillStyle = random() < 0.5 ? 'rgba(90, 60, 30, 0.12)' : 'rgba(255, 255, 255, 0.14)';
        ctx.fillRect(random() * W, random() * H, 2 + random() * 3, 2 + random() * 3);
    }

    const images = s.photos.length ? s.photos.slice(0, 4) : [null];
    const layouts = {
        1: [[540, 700, 760, -3]],
        2: [[400, 540, 560, -6], [680, 920, 560, 5]],
        3: [[350, 490, 480, -7], [730, 640, 470, 6], [460, 950, 490, -2]],
        4: [[320, 470, 430, -8], [760, 510, 420, 7], [330, 900, 420, 4], [750, 940, 440, -5]],
    };
    layouts[images.length].forEach(([x, y, w, a], i) => {
        const b = w * 0.045;
        const h = b + w + w * 0.15;
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate(rad(a + (i % 2 ? -v.tilt : v.tilt)));
        ctx.shadowColor = 'rgba(40, 25, 10, 0.4)';
        ctx.shadowBlur = 26;
        ctx.shadowOffsetY = 10;
        ctx.fillStyle = C.paper;
        ctx.fillRect(-w / 2 - b, -h / 2, w + 2 * b, h);
        ctx.shadowColor = 'transparent';
        photo(ctx, images[i], -w / 2, -h / 2 + b, w, w);
        drawTape(ctx, i % 2 ? v.tape2 : v.tape, (random() - 0.5) * w * 0.4, -h / 2 + 4, 0, 0.7);
        ctx.restore();
    });

    let y = 1250;
    if (on.title) {
        ctx.save();
        ctx.translate(W / 2, y);
        ctx.rotate(rad(-2));
        const { lines, size } = fitLines(ctx, t.title, 820, 2, `700 {}px ${FONTS.hand}`, 76, 50);
        const lh = size * 1.05;
        const h = lines.length * lh + 44;
        ctx.shadowColor = 'rgba(40, 25, 10, 0.35)';
        ctx.shadowBlur = 16;
        ctx.shadowOffsetY = 6;
        ctx.fillStyle = C.paper;
        tornRect(ctx, -460, 0, 920, h, random);
        ctx.fill();
        ctx.shadowColor = 'transparent';
        ctx.fillStyle = C.forest900;
        ctx.textAlign = 'center';
        lines.forEach((line, i) => ctx.fillText(line, 0, 22 + size * 0.8 + i * lh));
        ctx.restore();
        y += h + 30;
    }
    const sub = [place(t, on), on.km && t.km].filter(Boolean).join(' · ');
    if (sub) {
        ctx.save();
        ctx.translate(W / 2, y);
        ctx.rotate(rad(2));
        ctx.font = `700 50px ${FONTS.hand}`;
        const w = Math.min(ctx.measureText(sub).width + 80, 960);
        ctx.fillStyle = C.sand100;
        tornRect(ctx, -w / 2, 0, w, 76, random);
        ctx.fill();
        ctx.fillStyle = C.bark;
        ctx.textAlign = 'center';
        ctx.fillText(sub, 0, 54, w - 40);
        ctx.restore();
        y += 100;
    }
    if (on.progress) progressLine(ctx, 120, y + 20, 840, s.progress, { label: C.forest900, track: 'rgba(28, 58, 38, 0.45)', done: C.forest900, ring: C.sand100 });
    linkSpot(ctx, s, Math.max(1660, Math.min(y + 190, 1720)), { note: C.forest900, dash: 'rgba(28, 58, 38, 0.6)', text: 'rgba(28, 58, 38, 0.7)' });
    if (on.brand) site(ctx, t, C.forest900);
}

// 9. Route scribble: a hand-drawn wiggly way from Lisse to Hanoi, with Coen's photo where he is now.
function drawRoute(ctx, s) {
    const { t, on, v } = s;
    waves(ctx, '#eef3e6', '#dce9cf', 'rgba(28, 58, 38, 0.06)');

    const pts = [];
    for (let i = 0; i <= 240; i++) {
        const k = i / 240;
        pts.push([170 + 740 * k + Math.sin(k * Math.PI * 2.6) * 200 * (1 - k), 400 + 1030 * k + Math.sin(k * 23) * 14]);
    }
    const p = s.progress ?? 0;
    const path = (to) => {
        ctx.beginPath();
        pts.slice(0, to + 1).forEach(([x, y], i) => (i ? ctx.lineTo(x, y) : ctx.moveTo(x, y)));
    };
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.setLineDash([3, 18]);
    ctx.strokeStyle = 'rgba(138, 90, 60, 0.6)';
    ctx.lineWidth = 8;
    path(240);
    ctx.stroke();
    ctx.setLineDash([]);
    if (on.progress && p > 0) {
        ctx.strokeStyle = C.moss600;
        ctx.lineWidth = 12;
        path(Math.round(p * 240));
        ctx.stroke();
    }

    // Start and finish.
    ctx.textAlign = 'center';
    ctx.font = `700 60px ${FONTS.hand}`;
    ctx.fillStyle = C.forest900;
    ctx.fillText('Lisse', pts[0][0], pts[0][1] - 40);
    ctx.fillText('Hanoi', pts[240][0], pts[240][1] + 80);
    [pts[0], pts[240]].forEach(([x, y]) => {
        ctx.beginPath();
        ctx.arc(x, y, 14, 0, Math.PI * 2);
        ctx.fill();
    });

    // Title at the top, handwritten.
    if (on.title) {
        const { lines, size } = fitLines(ctx, t.title, 900, 2, `700 {}px ${FONTS.hand}`, 80, 52);
        lines.forEach((line, i) => ctx.fillText(line, W / 2, 180 + i * size));
    }

    // Where Coen is: photo plus a handwritten label beside it.
    const [mx, my] = pts[Math.round(p * 240)];
    roundPhoto(ctx, s.cover, mx, my, 105, v.tilt * 3, v.tape);
    const right = mx < 560;
    const tx = right ? mx + 150 : mx - 150;
    const label = [];
    if (on.place && t.day) label.push([t.day, `700 66px ${FONTS.hand}`, C.forest900]);
    if (on.place && t.country) label.push([t.country, `800 36px ${FONTS.body}`, C.moss600, true]);
    if (on.date) label.push([t.date, `700 46px ${FONTS.hand}`, C.forest700]);
    if (on.km) label.push([t.km, `700 52px ${FONTS.hand}`, C.bark]);
    if (on.progress && t.progress) label.push([t.progress, `700 42px ${FONTS.hand}`, C.moss600]);
    if (!label.length && !s.progress) label.push([t.labels.home, `700 60px ${FONTS.hand}`, C.forest900]);
    ctx.textAlign = right ? 'left' : 'right';
    let y = my - (label.length * 58) / 2 + 40;
    label.forEach(([text, font, color, flagged]) => {
        ctx.font = font;
        ctx.fillStyle = color;
        const max = right ? W - tx - 50 : tx - 50;
        if (flagged && s.flag) {
            drawFlag(ctx, s.flag, right ? tx : tx - 48, y - 32, 48);
            ctx.fillText(text, right ? tx + 62 : tx - 62, y, max - 62);
        } else {
            ctx.fillText(text, tx, y, max);
        }
        y += 58;
    });

    linkSpot(ctx, s, 1660, INK);
    if (on.brand) site(ctx, t, C.moss600);
}

// 10. Ticket: a ticket from Lisse to Hanoi with the day, country, date and kilometres; the stub has the title.
function drawTicket(ctx, s) {
    const { t, on, v } = s;
    if (s.cover) {
        photo(ctx, s.cover, 0, 0, W, H);
        ctx.fillStyle = 'rgba(14, 28, 19, 0.6)';
        ctx.fillRect(0, 0, W, H);
    } else {
        waves(ctx, C.forest700, C.forest950, 'rgba(166, 207, 146, 0.09)');
    }

    // Drawn on its own canvas first, so the notches can be cut out of it.
    const tw = 860;
    const th = 1200;
    const cut = 960;
    const off = document.createElement('canvas');
    off.width = tw;
    off.height = th;
    const o = off.getContext('2d');
    o.fillStyle = '#f7f2e6';
    o.beginPath();
    o.roundRect(0, 0, tw, th, 30);
    o.fill();
    o.save();
    o.beginPath();
    o.roundRect(30, 30, tw - 60, 420, 18);
    o.clip();
    photo(o, s.cover, 30, 30, tw - 60, 420);
    o.restore();

    const small = (text, x, y, align = 'left') => {
        o.textAlign = align;
        o.fillStyle = C.moss600;
        o.font = `800 24px ${FONTS.body}`;
        o.letterSpacing = '5px';
        o.fillText(text.toUpperCase(), x, y);
        o.letterSpacing = '0px';
    };
    small(t.labels.from, 50, 515);
    small(t.labels.to, tw - 50, 515, 'right');
    o.fillStyle = C.forest900;
    o.font = `800 76px ${FONTS.display}`;
    o.textAlign = 'left';
    o.fillText('Lisse', 50, 595);
    o.textAlign = 'right';
    o.fillText('Hanoi', tw - 50, 595);
    // The way between, with a dot for how far.
    o.lineCap = 'round';
    o.setLineDash([2, 14]);
    o.strokeStyle = 'rgba(28, 58, 38, 0.45)';
    o.lineWidth = 6;
    o.beginPath();
    o.moveTo(290, 570);
    o.lineTo(tw - 300, 570);
    o.stroke();
    o.setLineDash([]);
    if (on.progress) {
        const m = 290 + (tw - 590) * s.progress;
        o.strokeStyle = C.moss600;
        o.lineWidth = 8;
        o.beginPath();
        o.moveTo(290, 570);
        o.lineTo(m, 570);
        o.stroke();
        o.fillStyle = C.moss600;
        o.beginPath();
        o.arc(m, 570, 13, 0, Math.PI * 2);
        o.fill();
    }

    [
        [t.labels.day, on.place && t.day_number],
        [t.labels.country, on.place && t.country],
        [t.labels.date, on.date && t.date],
        [t.labels.walked, on.km && t.km_number && `${t.km_number} km`],
    ].filter(([, value]) => value).forEach(([label, value], i) => {
        const x = i % 2 ? tw / 2 + 20 : 50;
        const y = 690 + Math.floor(i / 2) * 135;
        small(label, x, y);
        o.textAlign = 'left';
        o.fillStyle = C.forest900;
        fitFont(o, value, tw / 2 - 80, `700 {}px ${FONTS.hand}`, 64, 34);
        o.fillText(value, x, y + 62);
    });

    // Perforation with notches.
    o.setLineDash([10, 12]);
    o.strokeStyle = 'rgba(28, 58, 38, 0.35)';
    o.lineWidth = 3;
    o.beginPath();
    o.moveTo(40, cut);
    o.lineTo(tw - 40, cut);
    o.stroke();
    o.setLineDash([]);
    o.globalCompositeOperation = 'destination-out';
    [0, tw].forEach((x) => {
        o.beginPath();
        o.arc(x, cut, 26, 0, Math.PI * 2);
        o.fill();
    });
    o.globalCompositeOperation = 'source-over';

    // Stub: the title and how far along, handwritten.
    o.textAlign = 'center';
    o.fillStyle = C.forest900;
    if (on.title) {
        const { lines, size } = fitLines(o, t.title, tw - 100, 2, `700 {}px ${FONTS.hand}`, 66, 44);
        lines.forEach((line, i) => o.fillText(line, tw / 2, cut + 80 + i * size));
    }
    if (on.progress && t.progress) {
        o.fillStyle = C.moss600;
        o.font = `700 42px ${FONTS.hand}`;
        o.fillText(t.progress, tw / 2, th - 36);
    }

    ctx.save();
    ctx.translate(W / 2, 250 + th / 2);
    ctx.rotate(rad(-3 + v.tilt));
    ctx.shadowColor = 'rgba(0, 0, 0, 0.5)';
    ctx.shadowBlur = 40;
    ctx.shadowOffsetY = 16;
    ctx.drawImage(off, -tw / 2, -th / 2);
    ctx.shadowColor = 'transparent';
    drawTape(ctx, v.tape, 0, -th / 2 + 6, 0, 0.8);
    ctx.restore();

    linkSpot(ctx, s, 1660, DARK);
    if (on.brand) site(ctx, t, C.fern300);
}

// ——— Counter stories (Filament page CounterStory): today's numbers or the whole walk so far, no article. ———

const STAT_ITEMS = ['km', 'time', 'hours', 'days', 'countries', 'tent', 'progress', 'crow_day', 'crow_home', 'crow_hanoi'];
const STAT_PARTS = ['headline', 'sub', 'route', 'photo', ...STAT_ITEMS, 'crowline', 'note', 'sticker', 'brand'];
const STAT_DEFAULTS = {
    today: ['headline', 'sub', 'route', 'photo', 'km', 'time', 'crow_day', 'crow_home', 'crowline', 'note', 'sticker'],
    total: ['headline', 'sub', 'photo', 'km', 'days', 'countries', 'tent', 'crow_home', 'crow_hanoi', 'crowline', 'note', 'sticker'],
};
const STAT_DESIGNS = { big: drawStatsBig, photo: drawStatsPhoto, notebook: drawStatsNotebook, crow: drawStatsCrow };

async function initStats(root, data) {
    const canvas = root.querySelector('[data-canvas]');
    const localeSelect = root.querySelector('[data-locale]');
    const modeSelect = root.querySelector('[data-mode]');
    const designSelect = root.querySelector('[data-design]');
    const noteInput = root.querySelector('[data-note]');
    const status = root.querySelector('[data-status]');
    const partInputs = [...root.querySelectorAll('[data-part]')];
    const say = (text) => (status.textContent = text);
    const store = {
        get: () => { try { return JSON.parse(localStorage.getItem('twc-counter-story')) || {}; } catch { return {}; } },
        set: (value) => { try { localStorage.setItem('twc-counter-story', JSON.stringify(value)); } catch { /* full or blocked */ } },
    };

    const modes = ['today', 'total'];
    const delayedInput = root.querySelector('[data-delayed]');
    const liveWarning = root.querySelector('[data-live-warning]');
    // Photos and flags of both variants (delayed / live) and both modes, each loaded once.
    const all = (pick) => [...new Set(Object.values(data.variants).flatMap((v) => modes.map((m) => pick(v, m))).filter(Boolean))];
    const images = Object.fromEntries(await Promise.all(all((v, m) => v.photos[m]).map(async (url) => [url, await loadImage(url, true)])));
    const flagImages = Object.fromEntries(await Promise.all(all((v, m) => v.locales.nl[m].flag).map(async (code) => [code, await loadFlag(code)])));
    const variant = () => data.variants[delayedInput.checked ? 'delayed' : 'live'];
    // A photo picked from the gallery or uploaded replaces the automatic one (an upload stays in this browser).
    let picked = null;
    const photos = (m) => picked ?? images[variant().photos[m]] ?? null;
    const flags = (m) => flagImages[variant().locales.nl[m].flag] ?? null;
    await Promise.all([`800 80px ${FONTS.display}`, `700 80px ${FONTS.hand}`, `800 30px ${FONTS.body}`].map((font) => document.fonts.load(font).catch(() => {})));

    const saved = store.get();
    let mode = modes.includes(modeSelect.value) ? modeSelect.value : 'today';
    let design = STAT_DESIGNS[saved.design] ? saved.design : 'big';
    const chosen = saved.parts || {};
    let variation = randomVariation();

    const texts = () => variant().locales[localeSelect.value][mode];
    const available = (part) => {
        if (STAT_ITEMS.includes(part)) return texts().items.some((item) => item.key === part);
        return { sub: !!texts().sub, route: !!texts().route, photo: !!photos(mode), crowline: variant().crow !== null }[part] ?? true;
    };
    const selected = () => chosen[`${mode}:${design}`] || STAT_DEFAULTS[mode];
    const syncInputs = () => {
        designSelect.value = design;
        modeSelect.value = mode;
        partInputs.forEach((input) => {
            input.checked = selected().includes(input.value);
            input.disabled = !available(input.value);
        });
    };
    const draw = () => {
        const on = Object.fromEntries(STAT_PARTS.map((part) => [part, selected().includes(part) && available(part)]));
        const ctx = canvas.getContext('2d');
        ctx.save();
        ctx.clearRect(0, 0, W, H);
        ctx.textAlign = 'center';
        STAT_DESIGNS[design](ctx, {
            t: { ...texts(), note: noteInput.value || texts().note },
            on,
            items: texts().items.filter((item) => on[item.key]),
            cover: on.photo ? photos(mode) : null,
            flag: flags(mode),
            crow: variant().crow ?? 0,
            progress: variant().progress,
            v: variation,
        });
        ctx.restore();
    };
    const remember = () => store.set({ design, parts: chosen });

    modeSelect.addEventListener('change', () => {
        mode = modeSelect.value;
        noteInput.value = texts().note;
        syncInputs();
        draw();
    });
    const grid = root.querySelector('[data-photo-grid]');
    const fileInput = root.querySelector('[data-photo-file]');
    const usePhoto = (image, button = null) => {
        picked = image;
        grid.querySelectorAll('[data-pick]').forEach((b) => (b.style.borderColor = b === button ? 'rgb(22 163 74)' : 'transparent'));
        // Picking a photo means: show it.
        if (image && !selected().includes('photo')) chosen[`${mode}:${design}`] = [...selected(), 'photo'];
        remember();
        syncInputs();
        draw();
    };
    root.querySelector('[data-photo-auto]').addEventListener('click', () => {
        grid.hidden = true;
        usePhoto(null);
    });
    root.querySelector('[data-photo-gallery]').addEventListener('click', () => (grid.hidden = !grid.hidden));
    root.querySelector('[data-photo-upload]').addEventListener('click', () => fileInput.click());
    grid.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-pick]');
        if (!button) return;
        say('Foto laden…');
        const image = await loadImage(button.dataset.pick, true);
        say(image ? '' : 'Deze foto kon niet geladen worden.');
        if (image) usePhoto(image, button);
    });
    fileInput.addEventListener('change', async () => {
        const file = fileInput.files[0];
        if (!file) return;
        const image = await loadImage(URL.createObjectURL(file));
        say(image ? '' : 'Deze foto kon niet geladen worden.');
        if (image) usePhoto(image);
        fileInput.value = '';
    });

    // Delayed (as visitors see it, on by default) or live.
    const syncDelay = () => (liveWarning.style.display = delayedInput.checked ? 'none' : 'block');
    delayedInput.addEventListener('change', () => {
        syncDelay();
        syncInputs();
        draw();
    });
    syncDelay();
    designSelect.addEventListener('change', () => {
        design = designSelect.value;
        variation = randomVariation();
        remember();
        syncInputs();
        draw();
    });
    partInputs.forEach((input) => input.addEventListener('change', () => {
        chosen[`${mode}:${design}`] = partInputs.filter((i) => i.checked).map((i) => i.value);
        remember();
        draw();
    }));
    root.querySelector('[data-tape]').addEventListener('click', () => {
        variation = randomVariation();
        draw();
    });
    localeSelect.addEventListener('change', () => {
        noteInput.value = texts().note;
        syncInputs();
        draw();
    });
    noteInput.addEventListener('input', draw);
    noteInput.value = texts().note;
    syncInputs();
    draw();
    wireExport(root, canvas, () => texts().url, say);
}

// Numbers in one or two columns: a big number with a handwritten label under it. @returns the y below the grid
function statGrid(ctx, items, x, y, w, col, options = {}) {
    if (!items.length) return y;
    const cols = options.cols ?? (items.length > 3 ? 2 : 1);
    const cellW = w / cols;
    const cellH = options.cellH ?? 170;
    const size = options.size ?? (cols === 1 ? 128 : 100);
    ctx.save();
    ctx.textAlign = 'left';
    items.forEach((item, i) => {
        const cx = x + (i % cols) * cellW;
        const cy = y + Math.floor(i / cols) * cellH;
        if (col.card) {
            ctx.fillStyle = col.card;
            ctx.beginPath();
            ctx.roundRect(cx, cy, cellW - 20, cellH - 20, 24);
            ctx.fill();
        }
        const pad = col.card ? 28 : 0;
        ctx.fillStyle = col.value;
        const s = fitFont(ctx, item.value, cellW - 30 - pad * 2, `800 {}px ${FONTS.display}`, size, 48);
        ctx.fillText(item.value, cx + pad, cy + pad + s * 0.82);
        ctx.fillStyle = col.label;
        fitFont(ctx, item.label, cellW - 30 - pad * 2, `700 {}px ${FONTS.hand}`, options.label ?? 46, 26);
        ctx.fillText(item.label, cx + pad, cy + pad + s * 0.82 + 50);
    });
    ctx.restore();
    return y + Math.ceil(items.length / cols) * cellH;
}

// Country and date, with the flag in front.
function subLine(ctx, s, x, y, font, color, maxWidth, align = 'left') {
    const { t } = s;
    ctx.save();
    ctx.font = font;
    const flagW = s.flag ? 54 : 0;
    const width = Math.min(ctx.measureText(t.sub).width, maxWidth - flagW - 16);
    const left = align === 'center' ? x - (width + (flagW ? flagW + 16 : 0)) / 2 : x;
    if (flagW) drawFlag(ctx, s.flag, left, y - 34, flagW);
    ctx.fillStyle = color;
    ctx.textAlign = 'left';
    ctx.fillText(t.sub, left + (flagW ? flagW + 16 : 0), y, maxWidth - flagW - 16);
    ctx.restore();
}

// A. Big numbers on the green waves.
function drawStatsBig(ctx, s) {
    const { t, on, v } = s;
    waves(ctx, C.forest700, C.forest950, 'rgba(166, 207, 146, 0.09)');

    // Everything as one block, centred in the space above the sticker spot.
    const cols = s.items.length > 3 ? 2 : 1;
    const cellH = cols === 1 ? 230 : 210;
    const header = (on.headline ? 180 : 0) + (on.sub ? 80 : 0) + (on.route ? 80 : 0);
    const total = header + 60 + Math.ceil(s.items.length / cols) * cellH + (on.crowline ? 90 : 0);
    let y = Math.max(200, (on.note || on.sticker ? 1560 : 1800) / 2 - total / 2 + 100);

    if (s.cover) roundPhoto(ctx, s.cover, 850, y + 40, 125, 6 + v.tilt, v.tape);
    const textW = s.cover ? 600 : 900;
    ctx.textAlign = 'left';
    if (on.headline) {
        ctx.fillStyle = '#ffffff';
        fitFont(ctx, t.headline, textW, `800 {}px ${FONTS.display}`, 150, 70);
        ctx.fillText(t.headline, 90, y + 130);
        y += 180;
    }
    if (on.sub) {
        subLine(ctx, s, 90, y + 40, `800 40px ${FONTS.body}`, C.fern300, textW);
        y += 80;
    }
    if (on.route) {
        ctx.fillStyle = C.olive300;
        fitFont(ctx, t.route, 900, `700 {}px ${FONTS.hand}`, 60, 32);
        ctx.fillText(t.route, 90, y + 45);
        y += 80;
    }
    const end = statGrid(ctx, s.items, 90, y + 60, 920, { value: '#ffffff', label: C.fern300 }, { cellH, size: cols === 1 ? 150 : 124, label: 50 });
    if (on.crowline) progressLine(ctx, 90, end + 30, 900, s.crow, { label: C.fern300, track: 'rgba(166, 207, 146, 0.5)', done: C.olive300, ring: C.forest900 });
    linkSpot(ctx, s, 1660, DARK);
    if (on.brand) site(ctx, t, C.fern300);
}

// B. On the photo: the photo fills the story, numbers on dark cards at the bottom.
function drawStatsPhoto(ctx, s) {
    const { t, on } = s;
    if (s.cover) photo(ctx, s.cover, 0, 0, W, H);
    else waves(ctx, C.forest700, C.forest950, 'rgba(166, 207, 146, 0.09)');
    const top = ctx.createLinearGradient(0, 0, 0, 600);
    top.addColorStop(0, 'rgba(14, 28, 19, 0.7)');
    top.addColorStop(1, 'rgba(14, 28, 19, 0)');
    ctx.fillStyle = top;
    ctx.fillRect(0, 0, W, 600);
    const bottom = ctx.createLinearGradient(0, H * 0.45, 0, H);
    bottom.addColorStop(0, 'rgba(14, 28, 19, 0)');
    bottom.addColorStop(1, 'rgba(14, 28, 19, 0.85)');
    ctx.fillStyle = bottom;
    ctx.fillRect(0, H * 0.45, W, H * 0.55);

    ctx.save();
    ctx.shadowColor = 'rgba(0, 0, 0, 0.5)';
    ctx.shadowBlur = 16;
    ctx.textAlign = 'left';
    if (on.headline) {
        ctx.fillStyle = '#ffffff';
        fitFont(ctx, t.headline, 900, `700 {}px ${FONTS.hand}`, 130, 70);
        ctx.fillText(t.headline, 80, 270);
    }
    if (on.sub) subLine(ctx, s, 80, 345, `800 40px ${FONTS.body}`, '#ffffff', 920);
    if (on.route) {
        ctx.fillStyle = C.sand100;
        fitFont(ctx, t.route, 920, `700 {}px ${FONTS.hand}`, 56, 30);
        ctx.fillText(t.route, 80, 420);
    }
    ctx.restore();

    const boxY = H - 330;
    const cols = s.items.length > 2 ? 2 : 1;
    const rows = Math.ceil(s.items.length / cols);
    const gridEnd = boxY - (on.note && on.sticker ? 190 : 50) - (on.crowline ? 90 : 0);
    statGrid(ctx, s.items, 80, gridEnd - rows * 190, 940, { value: '#ffffff', label: C.fern300, card: 'rgba(14, 28, 19, 0.55)' }, { cols, cellH: 190, size: cols === 1 ? 96 : 80, label: 40 });
    if (on.crowline) progressLine(ctx, 80, gridEnd + 30, 920, s.crow, { label: '#ffffff', track: 'rgba(255, 255, 255, 0.6)', done: C.fern300, ring: C.forest900 });
    linkSpot(ctx, s, boxY, { ...DARK, shadow: 'rgba(0, 0, 0, 0.5)' });
    if (on.brand) site(ctx, t, '#ffffff');
}

// C. Notebook: a handwritten list with ticks on lined paper, a small taped photo.
function drawStatsNotebook(ctx, s) {
    const { t, on, v } = s;
    ctx.fillStyle = '#f6f1e3';
    ctx.fillRect(0, 0, W, H);
    ctx.strokeStyle = 'rgba(120, 160, 110, 0.3)';
    ctx.lineWidth = 2;
    for (let y = 412; y < H - 140; y += 88) {
        ctx.beginPath();
        ctx.moveTo(0, y);
        ctx.lineTo(W, y);
        ctx.stroke();
    }
    ctx.strokeStyle = 'rgba(214, 120, 100, 0.4)';
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.moveTo(130, 0);
    ctx.lineTo(130, H);
    ctx.stroke();

    if (s.cover) {
        const size = 250;
        ctx.save();
        ctx.translate(840, 290);
        ctx.rotate(rad(6 + v.tilt));
        ctx.shadowColor = 'rgba(0, 0, 0, 0.3)';
        ctx.shadowBlur = 24;
        ctx.shadowOffsetY = 10;
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(-size / 2 - 16, -size / 2 - 16, size + 32, size + 60);
        ctx.shadowColor = 'transparent';
        photo(ctx, s.cover, -size / 2, -size / 2, size, size);
        drawTape(ctx, v.tape, 0, -size / 2 - 12, 0, 0.6);
        ctx.restore();
    }
    const textW = s.cover ? 520 : 860;
    ctx.textAlign = 'left';
    if (on.headline) {
        ctx.fillStyle = C.forest900;
        fitFont(ctx, t.headline, textW, `700 {}px ${FONTS.hand}`, 130, 60);
        ctx.fillText(t.headline, 170, 300);
    }
    let y = 404; // baselines just above the ruled lines (412 + 88n)
    if (on.sub) {
        ctx.fillStyle = C.moss600;
        fitFont(ctx, t.sub, textW, `700 {}px ${FONTS.hand}`, 54, 30);
        ctx.fillText(t.sub, 170, y);
        y += 88;
    }
    if (on.route) {
        ctx.fillStyle = C.bark;
        fitFont(ctx, t.route, 860, `700 {}px ${FONTS.hand}`, 54, 30);
        ctx.fillText(t.route, 170, y);
        y += 88;
    }
    y += 88;
    s.items.forEach((item) => {
        // A handwritten tick.
        ctx.strokeStyle = C.moss600;
        ctx.lineWidth = 7;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.beginPath();
        ctx.moveTo(170, y - 22);
        ctx.lineTo(186, y - 6);
        ctx.lineTo(214, y - 46);
        ctx.stroke();
        ctx.fillStyle = C.forest900;
        ctx.font = `700 72px ${FONTS.hand}`;
        ctx.fillText(item.value, 240, y);
        const w = ctx.measureText(item.value).width;
        ctx.fillStyle = C.forest700;
        fitFont(ctx, item.label, 1000 - 260 - w, `700 {}px ${FONTS.hand}`, 54, 28);
        ctx.fillText(item.label, 260 + w, y);
        y += 88;
    });
    if (on.crowline) progressLine(ctx, 170, Math.min(y + 30, 1480), 840, s.crow, { label: C.moss600, track: 'rgba(28, 58, 38, 0.35)', done: C.moss600, ring: '#f6f1e3' });
    linkSpot(ctx, s, 1660, INK);
    if (on.brand) site(ctx, t, C.moss600);
}

// D. As the crow flies: a straight line from Lisse to Hanoi with Coen on it, and the straight-line distances big.
function drawStatsCrow(ctx, s) {
    const { t, on, v } = s;
    waves(ctx, '#eef3e6', '#dce9cf', 'rgba(28, 58, 38, 0.06)');
    ctx.textAlign = 'center';
    if (on.headline) {
        ctx.fillStyle = C.forest900;
        fitFont(ctx, t.headline, 900, `700 {}px ${FONTS.hand}`, 100, 50);
        ctx.fillText(t.headline, W / 2, 220);
    }
    if (on.sub) subLine(ctx, s, W / 2, 290, `800 38px ${FONTS.body}`, C.moss600, 900, 'center');

    const a = [200, 560];
    const b = [900, 1130];
    const p = [a[0] + (b[0] - a[0]) * s.crow, a[1] + (b[1] - a[1]) * s.crow];
    ctx.lineCap = 'round';
    ctx.setLineDash([3, 18]);
    ctx.strokeStyle = 'rgba(138, 90, 60, 0.6)';
    ctx.lineWidth = 8;
    ctx.beginPath();
    ctx.moveTo(...a);
    ctx.lineTo(...b);
    ctx.stroke();
    ctx.setLineDash([]);
    ctx.strokeStyle = C.moss600;
    ctx.lineWidth = 12;
    ctx.beginPath();
    ctx.moveTo(...a);
    ctx.lineTo(...p);
    ctx.stroke();
    ctx.fillStyle = C.forest900;
    [a, b].forEach(([x, y]) => {
        ctx.beginPath();
        ctx.arc(x, y, 16, 0, Math.PI * 2);
        ctx.fill();
    });
    ctx.font = `700 64px ${FONTS.hand}`;
    ctx.fillText(t.home, a[0] - 20, a[1] + 84);
    ctx.fillText(t.destination, b[0], b[1] + 86);
    // Coen's spot on the line; the photo hangs beside it (up-right), so it never covers Lisse or Hanoi.
    ctx.beginPath();
    ctx.arc(p[0], p[1], 24, 0, Math.PI * 2);
    ctx.fillStyle = C.moss600;
    ctx.fill();
    ctx.lineWidth = 8;
    ctx.strokeStyle = '#ffffff';
    ctx.stroke();
    if (s.cover) {
        const len = Math.hypot(b[0] - a[0], b[1] - a[1]);
        const q = [Math.min(940, p[0] + ((b[1] - a[1]) / len) * 200), Math.max(430, p[1] - ((b[0] - a[0]) / len) * 200)];
        ctx.strokeStyle = C.moss600;
        ctx.lineWidth = 5;
        ctx.setLineDash([2, 10]);
        ctx.beginPath();
        ctx.moveTo(...p);
        ctx.lineTo(...q);
        ctx.stroke();
        ctx.setLineDash([]);
        roundPhoto(ctx, s.cover, q[0], q[1], 100, v.tilt * 3, v.tape);
    }

    const crowItems = s.items.filter((item) => item.key.startsWith('crow_'));
    const others = s.items.filter((item) => !item.key.startsWith('crow_'));
    const end = statGrid(ctx, crowItems, 90, 1250, 920, { value: C.forest900, label: C.moss600 }, { cols: Math.max(1, Math.min(2, crowItems.length)), cellH: 170, size: 110, label: 40 });
    if (others.length) {
        ctx.textAlign = 'center';
        ctx.fillStyle = C.bark;
        fitFont(ctx, others.map((item) => `${item.value} ${item.label}`).join(' · '), 940, `700 {}px ${FONTS.hand}`, 50, 28);
        ctx.fillText(others.map((item) => `${item.value} ${item.label}`).join(' · '), W / 2, Math.min(end + 40, 1500));
    }
    linkSpot(ctx, s, 1660, INK);
    if (on.brand) site(ctx, t, C.moss600);
}
