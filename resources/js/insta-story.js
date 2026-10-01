// Instagram story for an article (Filament page InstaStory): drawn on a 1080×1920 canvas in the site's style —
// green wave pattern, polaroid with tape, handwritten note and a dashed spot for Instagram's link sticker.
const W = 1080;
const H = 1920;
const C = {
    forest950: '#0e1c13',
    forest800: '#1c3a26',
    forest700: '#264d33',
    fern300: '#a6cf92',
    olive300: '#c2c07a',
    sand100: '#eee8d8',
    tape: 'rgba(194, 192, 122, 0.8)',
};
const FONTS = { display: '"Bricolage Grotesque", sans-serif', hand: 'Caveat, cursive', body: 'Nunito, sans-serif' };

const root = document.querySelector('[data-insta-story]');
if (root) init(root);

async function init(root) {
    const data = JSON.parse(root.dataset.story);
    const canvas = root.querySelector('[data-canvas]');
    const localeSelect = root.querySelector('[data-locale]');
    const noteInput = root.querySelector('[data-note]');
    const status = root.querySelector('[data-status]');
    const say = (text) => (status.textContent = text);

    let cover = null;
    if (data.cover) {
        cover = new Image();
        cover.crossOrigin = 'anonymous'; // R2 sends CORS headers (php artisan media:cors), so the canvas stays exportable
        cover.src = data.cover;
        await cover.decode().catch(() => (cover = null));
    }
    await Promise.all([`800 80px ${FONTS.display}`, `700 80px ${FONTS.hand}`, `800 30px ${FONTS.body}`].map((font) => document.fonts.load(font).catch(() => {})));

    const texts = () => data.locales[localeSelect.value];
    const draw = () => render(canvas.getContext('2d'), { ...texts(), note: noteInput.value || texts().note }, cover);

    localeSelect.addEventListener('change', () => {
        noteInput.value = texts().note;
        draw();
    });
    noteInput.addEventListener('input', draw);
    noteInput.value = texts().note;
    draw();

    const file = () => new Promise((resolve) => canvas.toBlob((blob) => resolve(new File([blob], 'travelwithcoen-story.png', { type: 'image/png' })), 'image/png'));
    const download = async () => {
        const link = Object.assign(document.createElement('a'), { href: URL.createObjectURL(await file()), download: 'travelwithcoen-story.png' });
        link.click();
        setTimeout(() => URL.revokeObjectURL(link.href), 1000);
    };

    root.querySelector('[data-copy]').addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(texts().url);
            say('Link gekopieerd: ' + texts().url);
        } catch {
            prompt('Kopieer de link:', texts().url);
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

function render(ctx, t, cover) {
    ctx.save();
    ctx.clearRect(0, 0, W, H);

    // Background: dark green with the wave (topo) pattern.
    const bg = ctx.createLinearGradient(0, 0, 0, H);
    bg.addColorStop(0, C.forest700);
    bg.addColorStop(1, C.forest950);
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);
    ctx.strokeStyle = 'rgba(166, 207, 146, 0.09)';
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

    // Header: name and "Lisse ····· Hanoi".
    ctx.textAlign = 'center';
    ctx.fillStyle = '#ffffff';
    ctx.font = `800 66px ${FONTS.display}`;
    ctx.fillText('Travel with Coen', W / 2, 170);
    ctx.fillStyle = C.fern300;
    ctx.font = `800 28px ${FONTS.body}`;
    ctx.letterSpacing = '8px';
    ctx.fillText('LISSE  ·····  HANOI', W / 2, 222);
    ctx.letterSpacing = '0px';

    // Polaroid with the cover photo, slightly tilted, with a piece of tape.
    const pw = 820;
    const photo = 740;
    const ph = photo + 40 + 150;
    ctx.save();
    ctx.translate(W / 2, 300 + ph / 2);
    ctx.rotate((-2.5 * Math.PI) / 180);
    ctx.shadowColor = 'rgba(0, 0, 0, 0.45)';
    ctx.shadowBlur = 40;
    ctx.shadowOffsetY = 18;
    ctx.fillStyle = '#fbfaf5';
    ctx.fillRect(-pw / 2, -ph / 2, pw, ph);
    ctx.shadowColor = 'transparent';
    const px = -photo / 2;
    const py = -ph / 2 + 40;
    if (cover) {
        const scale = Math.max(photo / cover.naturalWidth, photo / cover.naturalHeight);
        const sw = photo / scale;
        const sh = photo / scale;
        ctx.drawImage(cover, (cover.naturalWidth - sw) / 2, (cover.naturalHeight - sh) / 2, sw, sh, px, py, photo, photo);
    } else {
        ctx.fillStyle = C.forest800;
        ctx.fillRect(px, py, photo, photo);
        ctx.fillStyle = C.fern300;
        ctx.font = `700 120px ${FONTS.hand}`;
        ctx.fillText('Lisse → Hanoi', 0, py + photo / 2 + 40);
    }
    ctx.fillStyle = C.forest800;
    ctx.font = `700 64px ${FONTS.hand}`;
    fitText(ctx, t.caption || '', 0, ph / 2 - 52, photo);
    ctx.rotate((5 * Math.PI) / 180);
    ctx.fillStyle = C.tape;
    ctx.fillRect(-140, -ph / 2 - 40, 280, 76);
    ctx.restore();

    // Title.
    ctx.fillStyle = '#ffffff';
    ctx.font = `800 74px ${FONTS.display}`;
    const lines = wrap(ctx, t.title || '', 920).slice(0, 3);
    lines.forEach((line, i) => ctx.fillText(line, W / 2, 1375 + i * 86));
    const after = 1375 + (lines.length - 1) * 86;

    // Handwritten note with an arrow down to the spot for the link sticker.
    const boxY = Math.max(after + 190, 1610);
    ctx.save();
    ctx.translate(230, boxY - 80);
    ctx.rotate((-6 * Math.PI) / 180);
    ctx.fillStyle = C.olive300;
    ctx.font = `700 76px ${FONTS.hand}`;
    ctx.fillText(t.note, 0, 0);
    ctx.restore();
    ctx.strokeStyle = C.olive300;
    ctx.lineWidth = 6;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.beginPath();
    ctx.moveTo(445, boxY - 115);
    ctx.bezierCurveTo(500, boxY - 125, 525, boxY - 60, 500, boxY - 10);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(478, boxY - 30);
    ctx.lineTo(500, boxY - 6);
    ctx.lineTo(522, boxY - 32);
    ctx.stroke();

    // Dashed spot for Instagram's link sticker.
    ctx.setLineDash([16, 14]);
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.7)';
    ctx.lineWidth = 4;
    roundRect(ctx, W / 2 - 280, boxY, 560, 120, 60);
    ctx.stroke();
    ctx.setLineDash([]);
    ctx.fillStyle = 'rgba(255, 255, 255, 0.55)';
    ctx.font = `800 34px ${FONTS.body}`;
    ctx.fillText(t.sticker, W / 2, boxY + 72);

    // Footer: the site.
    ctx.fillStyle = C.fern300;
    ctx.font = `800 32px ${FONTS.body}`;
    ctx.fillText(t.site, W / 2, H - 70);
    ctx.restore();
}

function wrap(ctx, text, maxWidth) {
    const lines = [];
    let line = '';
    for (const word of text.split(/\s+/)) {
        const test = line ? `${line} ${word}` : word;
        if (ctx.measureText(test).width > maxWidth && line) {
            lines.push(line);
            line = word;
        } else {
            line = test;
        }
    }
    if (line) lines.push(line);
    return lines;
}

function fitText(ctx, text, x, y, maxWidth) {
    let size = 64;
    while (size > 30 && ctx.measureText(text).width > maxWidth) {
        size -= 4;
        ctx.font = `700 ${size}px ${FONTS.hand}`;
    }
    ctx.fillText(text, x, y);
}

function roundRect(ctx, x, y, w, h, r) {
    ctx.beginPath();
    ctx.roundRect(x, y, w, h, r);
}
