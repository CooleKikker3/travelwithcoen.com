// Gallery wall (resources/views/components/gallery-wall.blade.php):
// - tiles pop in with a small random tilt when they scroll into view;
// - older items load from the next page when the "more" link comes near;
// - clicking a tile opens the lightbox (large media + description); videos only play on request.

// Same icon as resources/views/components/icons/play.blade.php.
const PLAY_ICON = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.14v13.72a1 1 0 0 0 1.52.85l11.02-6.86a1 1 0 0 0 0-1.7L9.52 4.29A1 1 0 0 0 8 5.14Z"/></svg>';

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const popIn = new IntersectionObserver((entries) => {
    let batch = 0;
    entries.filter((entry) => entry.isIntersecting).forEach((entry) => {
        popIn.unobserve(entry.target);
        entry.target.style.setProperty('--delay', `${reduceMotion ? 0 : (batch++ % 10) * 60}ms`);
        entry.target.classList.add('is-in');
    });
}, { rootMargin: '0px 0px -40px 0px' });

document.querySelectorAll('[data-wall]').forEach(initWall);

function initWall(wall) {
    const grid = wall.querySelector('[data-wall-grid]');
    const tiles = () => [...grid.querySelectorAll('[data-wall-item]')];

    const prepare = (tile) => {
        tile.style.setProperty('--tilt', `${reduceMotion ? 0 : (Math.random() * 6 - 3).toFixed(2)}deg`);
        popIn.observe(tile);
    };
    tiles().forEach(prepare);

    const relayout = () => fillGaps(grid, tiles());
    relayout();
    slideOnResize(grid, tiles, relayout);

    const loadMore = initInfiniteScroll(wall, grid, prepare, relayout);
    initLightbox(wall, grid, tiles, loadMore);
}

/**
 * Freewall-style gap filling: the dense grid can't fill a hole with a brick that is too big, so a
 * brick left of a hole grows into it (or the brick above grows down). Repeats until the wall is
 * closed; only the last row may stay open. Extra spans are reset first, so it adapts to every width.
 */
function fillGaps(grid, tiles) {
    tiles.forEach((tile) => { tile.style.gridColumnStart = ''; tile.style.gridRowStart = ''; });

    const style = getComputedStyle(grid);
    const cols = style.gridTemplateColumns.split(' ').length;
    const gap = parseFloat(style.columnGap) || 0;

    for (let pass = 0; pass < 200; pass++) {
        const cellW = (grid.clientWidth + gap) / cols;
        const cellH = (parseFloat(getComputedStyle(grid).gridAutoRows) || cellW - gap) + gap;
        const cells = new Map();
        let rows = 0;

        const bricks = tiles.map((tile) => {
            // offset* = layout position, unaffected by the pop-in/slide transforms.
            const brick = {
                tile,
                col: Math.round(tile.offsetLeft / cellW),
                row: Math.round(tile.offsetTop / cellH),
                cs: Math.max(1, Math.round((tile.offsetWidth + gap) / cellW)),
                rs: Math.max(1, Math.round((tile.offsetHeight + gap) / cellH)),
            };
            for (let r = brick.row; r < brick.row + brick.rs; r++) {
                for (let c = brick.col; c < brick.col + brick.cs; c++) cells.set(`${r},${c}`, brick);
            }
            rows = Math.max(rows, brick.row + brick.rs);
            return brick;
        });
        if (!bricks.length) return;

        const free = (r, c) => c >= 0 && c < cols && !cells.has(`${r},${c}`);
        const hole = (() => {
            for (let r = 0; r < rows - 1; r++) for (let c = 0; c < cols; c++) if (free(r, c)) return [r, c];
            return null;
        })();
        if (!hole) return;

        const [r, c] = hole;
        const left = cells.get(`${r},${c - 1}`);
        const above = cells.get(`${r - 1},${c}`);
        const range = (from, length) => Array.from({ length }, (_, i) => from + i);

        if (left && range(left.row, left.rs).every((row) => free(row, c))) {
            left.tile.style.gridColumnStart = `span ${left.cs + 1}`;
        } else if (above && range(above.col, above.cs).every((col) => free(r, col))) {
            above.tile.style.gridRowStart = `span ${above.rs + 1}`;
        } else if (left) {
            left.tile.style.gridColumnStart = `span ${left.cs + 1}`; // may push things around; the next pass re-measures
        } else {
            return;
        }
    }
}

// When the width changes the grid repacks; bricks then slide from their old to their new place (FLIP).
function slideOnResize(grid, tiles, relayout) {
    const measure = () => new Map(tiles().map((tile) => [tile, { x: tile.offsetLeft, y: tile.offsetTop }]));

    let positions = measure();
    let width = grid.clientWidth;

    new ResizeObserver(() => {
        if (grid.clientWidth !== width) relayout();
        const next = measure();
        if (!reduceMotion && grid.clientWidth !== width) {
            next.forEach((position, tile) => {
                const old = positions.get(tile);
                if (!old || !tile.classList.contains('is-in')) return;
                const [dx, dy] = [old.x - position.x, old.y - position.y];
                if (dx || dy) {
                    tile.animate([{ transform: `translate(${dx}px, ${dy}px)` }, { transform: 'none' }], { duration: 550, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)' });
                }
            });
        }
        positions = next;
        width = grid.clientWidth;
    }).observe(grid);
}

function initInfiniteScroll(wall, grid, prepare, relayout) {
    const more = wall.querySelector('[data-wall-next]');
    if (!more) return null;

    let loading = null;
    let failed = false;

    const loadMore = () => {
        if (loading || !more.isConnected) return loading ?? Promise.resolve();
        more.classList.add('is-loading');
        failed = false;

        loading = fetch(more.href, { headers: { 'X-Requested-With': 'fetch' } })
            .then((response) => response.text())
            .then((html) => {
                const page = new DOMParser().parseFromString(html, 'text/html');
                page.querySelectorAll('[data-wall-grid] > [data-wall-item]').forEach((item) => {
                    const tile = document.importNode(item, true);
                    grid.appendChild(tile);
                    prepare(tile);
                });
                relayout();
                const next = page.querySelector('[data-wall-next]');
                next ? more.setAttribute('href', next.getAttribute('href')) : more.parentElement.remove();
            })
            .catch(() => { failed = true; }) // the link still works as a normal link
            .finally(() => {
                loading = null;
                more.classList.remove('is-loading');
                // Few items on a tall screen: keep going while the link is still close.
                if (!failed && more.isConnected && more.getBoundingClientRect().top < window.innerHeight + 600) loadMore();
            });

        return loading;
    };

    new IntersectionObserver((entries) => entries[0].isIntersecting && loadMore(), { rootMargin: '600px 0px' }).observe(more);
    more.addEventListener('click', (event) => {
        event.preventDefault();
        loadMore();
    });

    return loadMore;
}

function initLightbox(wall, grid, tiles, loadMore) {
    const dialog = wall.querySelector('[data-wall-lightbox]');
    const $ = (name) => dialog.querySelector(`[data-lightbox-${name}]`);
    let current = 0;

    const el = (tag, attributes = {}) => Object.assign(document.createElement(tag), attributes);

    function play() {
        const media = $('media');
        media.classList.add('is-revealed'); // starting a sensitive video also shows it
        const video = media.querySelector('video');
        if (video) return video.play();

        const poster = media.querySelector('[data-youtube-poster]');
        if (poster) {
            poster.replaceWith(el('iframe', {
                src: `https://www.youtube-nocookie.com/embed/${encodeURIComponent(poster.dataset.youtubePoster)}?autoplay=1`,
                allow: 'autoplay; encrypted-media; picture-in-picture; fullscreen',
                allowFullscreen: true,
                title: 'YouTube video',
            }));
            $('play').hidden = true;
        }
    }

    function show(index) {
        const list = tiles();
        current = Math.max(0, Math.min(index, list.length - 1));
        const d = list[current].dataset;
        const media = $('media');
        media.replaceChildren();

        // Photos and videos always fill the available space (also small photos), keeping their shape.
        const fill = (node) => {
            node.style.width = `min(var(--media-w), calc(var(--media-h) * ${Number(d.ratio) || 1.5}))`;
            node.style.aspectRatio = d.ratio;
            return node;
        };

        if (d.kind === 'image') {
            media.append(fill(el('img', { src: d.src, alt: d.caption || '' })));
        } else if (d.kind === 'video') {
            media.append(fill(el('video', { src: d.src, controls: true, playsInline: true, preload: 'metadata' })));
        } else {
            // YouTube: a poster first; the player only loads when started.
            const poster = el('button', { type: 'button', className: 'wall-lightbox__poster' });
            poster.setAttribute('aria-label', $('play').textContent.trim());
            poster.dataset.youtubePoster = d.src;
            const image = el('img', { src: d.thumb, alt: '' });
            image.addEventListener('error', () => { image.src = d.thumbFallback; }, { once: true });
            const icon = el('span', { className: 'wall-play' });
            icon.innerHTML = PLAY_ICON;
            poster.append(image, icon);
            poster.addEventListener('click', play);
            media.append(poster);
        }

        // Sensitive items: blurred with a warning until "Show anyway" (handled in app.js).
        media.classList.remove('is-revealed');
        media.classList.toggle('sensitive', d.sensitive === '1');
        media.toggleAttribute('data-sensitive', d.sensitive === '1');
        if (d.sensitive === '1') {
            media.append(dialog.querySelector('[data-lightbox-sensitive]').content.cloneNode(true));
        }

        $('caption').textContent = d.caption || '';
        $('meta').textContent = [d.date, d.country].filter(Boolean).join(' · ');
        $('article').hidden = !d.articleUrl;
        if (d.articleUrl) Object.assign($('article'), { href: d.articleUrl, textContent: `${d.articleTitle} →` });
        $('play').hidden = d.kind === 'image';
        $('prev').disabled = current === 0;
        $('next').disabled = current === list.length - 1 && !wall.querySelector('[data-wall-next]');

        // Shareable link to this item (#item-12, not the element id, so the page doesn't jump); removed on close.
        history.replaceState(null, '', `#${list[current].id.replace('wall-', '')}`);
    }

    async function step(direction) {
        if (direction > 0 && current === tiles().length - 1 && loadMore) await loadMore();
        show(current + direction);
    }

    grid.addEventListener('click', (event) => {
        const tile = event.target.closest('[data-wall-item]');
        if (!tile) return;
        event.preventDefault();
        show(tiles().indexOf(tile));
        dialog.showModal();
    });

    $('play').addEventListener('click', play);
    $('prev').addEventListener('click', () => step(-1));
    $('next').addEventListener('click', () => step(1));
    $('close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => event.target === dialog && dialog.close()); // click on the backdrop
    dialog.addEventListener('close', () => {
        $('media').replaceChildren(); // stops a playing video
        history.replaceState(null, '', location.pathname + location.search);
    });

    // Opened via a shared link: /gallery#item-12
    const linked = location.hash.startsWith('#item-') && grid.querySelector(`#wall-${CSS.escape(location.hash.slice(1))}`);
    if (linked) {
        show(tiles().indexOf(linked));
        dialog.showModal();
    }
    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') step(-1);
        if (event.key === 'ArrowRight') step(1);
    });

    // Swipe on touch screens.
    let touchX = null;
    dialog.addEventListener('touchstart', (event) => { touchX = event.touches[0].clientX; }, { passive: true });
    dialog.addEventListener('touchend', (event) => {
        const dx = event.changedTouches[0].clientX - (touchX ?? event.changedTouches[0].clientX);
        if (Math.abs(dx) > 60) step(dx < 0 ? 1 : -1);
        touchX = null;
    });
}
