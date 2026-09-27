// Gallery wall (resources/views/components/gallery-wall.blade.php):
// - tiles pop in with a small random tilt when they scroll into view;
// - older items load from the next page when the "more" link comes near;
// - clicking a tile opens the lightbox (large media + description); videos only play on request.

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
    slideOnResize(grid, tiles);

    const loadMore = initInfiniteScroll(wall, grid, prepare);
    initLightbox(wall, grid, tiles, loadMore);
}

// When the width changes the grid repacks; bricks then slide from their old to their new place (FLIP).
function slideOnResize(grid, tiles) {
    const measure = () => {
        const origin = grid.getBoundingClientRect();
        return new Map(tiles().map((tile) => {
            const rect = tile.getBoundingClientRect();
            return [tile, { x: rect.left - origin.left, y: rect.top - origin.top }];
        }));
    };

    let positions = measure();
    let width = grid.clientWidth;

    new ResizeObserver(() => {
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

function initInfiniteScroll(wall, grid, prepare) {
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

        if (d.kind === 'image') {
            media.append(el('img', { src: d.src, alt: d.caption || '' }));
        } else if (d.kind === 'video') {
            media.append(el('video', { src: d.src, controls: true, playsInline: true, preload: 'metadata' }));
        } else {
            // YouTube: a poster first; the player only loads when started.
            const poster = el('button', { type: 'button', className: 'wall-lightbox__poster' });
            poster.setAttribute('aria-label', $('play').textContent.trim());
            poster.dataset.youtubePoster = d.src;
            const image = el('img', { src: d.thumb.replace('hqdefault', 'maxresdefault'), alt: '' });
            image.addEventListener('error', () => { image.src = d.thumb; }, { once: true });
            poster.append(image, el('span', { className: 'wall-play', textContent: '▶' }));
            poster.addEventListener('click', play);
            media.append(poster);
        }

        $('caption').textContent = d.caption || '';
        $('meta').textContent = [d.date, d.country].filter(Boolean).join(' · ');
        $('article').hidden = !d.articleUrl;
        if (d.articleUrl) Object.assign($('article'), { href: d.articleUrl, textContent: `${d.articleTitle} →` });
        $('play').hidden = d.kind === 'image';
        $('prev').disabled = current === 0;
        $('next').disabled = current === list.length - 1 && !wall.querySelector('[data-wall-next]');
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
    dialog.addEventListener('close', () => $('media').replaceChildren()); // stops a playing video
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
