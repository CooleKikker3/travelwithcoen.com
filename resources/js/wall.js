// Gallery wall (resources/views/components/gallery-wall.blade.php):
// - spreads tiles over columns, each new tile into the shortest column (so appending never reshuffles);
// - pops tiles in with a small random tilt when they scroll into view;
// - loads older items from the next page when the "more" link comes near.

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const popIn = new IntersectionObserver((entries) => {
    let batch = 0;
    entries.filter((entry) => entry.isIntersecting).forEach((entry) => {
        popIn.unobserve(entry.target);
        entry.target.style.setProperty('--delay', `${reduceMotion ? 0 : (batch++ % 8) * 70}ms`);
        entry.target.classList.add('is-in');
    });
}, { rootMargin: '0px 0px -40px 0px' });

document.querySelectorAll('[data-wall]').forEach(initWall);

function initWall(wall) {
    const source = wall.querySelector('[data-wall-source]');
    const items = [...source.children];
    const maxColumns = Number(wall.dataset.maxColumns || 4);
    let columns = [];
    let heights = [];

    const columnCount = () => Math.min(maxColumns, wall.clientWidth < 640 ? 2 : wall.clientWidth < 1024 ? 3 : 4);

    function layout() {
        const count = columnCount();
        source.replaceChildren();
        source.classList.add('is-arranged');
        columns = Array.from({ length: count }, () => source.appendChild(Object.assign(document.createElement('div'), { className: 'wall-column' })));
        heights = columns.map(() => 0);
        items.forEach(place);
    }

    function place(item) {
        // Height in "column widths": 1 / aspect ratio, plus a bit for a caption.
        const column = heights.indexOf(Math.min(...heights));
        heights[column] += 1 / (Number(item.dataset.ratio) || 1) + (item.querySelector('figcaption') ? 0.2 : 0) + 0.08;
        columns[column].appendChild(item);

        if (!item.style.getPropertyValue('--tilt')) {
            item.style.setProperty('--tilt', `${reduceMotion ? 0 : (Math.random() * 5 - 2.5).toFixed(2)}deg`);
        }
        if (!item.classList.contains('is-in')) popIn.observe(item);
    }

    layout();

    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => columnCount() !== columns.length && layout(), 150);
    });

    // Infinite scroll: fetch the next page and take its tiles.
    const more = wall.querySelector('[data-wall-next]');
    if (!more) return;

    let loading = false;
    let failed = false;
    const loadMore = async () => {
        if (loading || !more.getAttribute('href')) return;
        loading = true;
        failed = false;
        more.classList.add('is-loading');

        try {
            const html = await (await fetch(more.href, { headers: { 'X-Requested-With': 'fetch' } })).text();
            const page = new DOMParser().parseFromString(html, 'text/html');
            page.querySelectorAll('[data-wall-source] > [data-wall-item]').forEach((item) => {
                const node = document.importNode(item, true);
                items.push(node);
                place(node);
            });

            const next = page.querySelector('[data-wall-next]');
            next ? more.setAttribute('href', next.getAttribute('href')) : more.parentElement.remove();
        } catch {
            // Keep the link: clicking it still opens the next page.
            failed = true;
        } finally {
            loading = false;
            more.classList.remove('is-loading');
        }

        // Few items on a tall screen: keep going while the link is still close.
        if (!failed && more.isConnected && more.getBoundingClientRect().top < window.innerHeight + 600) {
            loadMore();
        }
    };

    new IntersectionObserver((entries) => entries[0].isIntersecting && loadMore(), { rootMargin: '600px 0px' }).observe(more);
    more.addEventListener('click', (event) => {
        event.preventDefault();
        loadMore();
    });
}
