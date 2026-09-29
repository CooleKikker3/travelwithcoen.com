//
import './map';
import './wall';


// Scroll reveal: [data-reveal] elements float in once they come into view (styles in app.css).
document.documentElement.classList.add('js');
const revealer = new IntersectionObserver((entries) => {
    entries.filter((entry) => entry.isIntersecting).forEach((entry) => {
        entry.target.classList.add('is-visible');
        revealer.unobserve(entry.target);
    });
}, { rootMargin: '0px 0px -8% 0px' });
document.querySelectorAll('[data-reveal]').forEach((el) => revealer.observe(el));

// Floating navigation: solid background once the page is scrolled; close the mobile menu on outside click.
const nav = document.querySelector('[data-nav]');
if (nav) {
    const toTop = document.querySelector('[data-to-top]');
    const onScroll = () => {
        nav.classList.toggle('is-scrolled', window.scrollY > 24);
        toTop?.classList.toggle('is-visible', window.scrollY > 900);
    };
    toTop?.addEventListener('click', (event) => {
        event.preventDefault();
        window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    });
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    document.addEventListener('click', (event) => {
        if (!event.target.closest('.nav-menu')) nav.querySelector('.nav-menu')?.removeAttribute('open');
    });
}

// Sensitive images (articles and gallery lightbox): reveal after the visitor chooses to see them.
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-sensitive-reveal]');
    if (!button) return;
    event.preventDefault();
    button.closest('[data-sensitive]')?.classList.add('is-revealed');
});

// Numbers that count to their value when they come into view (status block on the home page):
// data-count-from → data-count-to, shown with data-format ("#" is replaced by the number).
const counters = document.querySelectorAll('[data-count-to]');
if (counters.length && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const format = new Intl.NumberFormat(document.documentElement.lang || 'en');
    const show = (el, value) => { el.textContent = el.dataset.format.replace('#', format.format(Math.round(value))); };
    const counter = new IntersectionObserver((entries) => {
        entries.filter((entry) => entry.isIntersecting).forEach(({ target }) => {
            counter.unobserve(target);
            const [from, to] = [Number(target.dataset.countFrom), Number(target.dataset.countTo)];
            const start = performance.now();
            const step = (now) => {
                const t = Math.min(1, (now - start) / 1600);
                show(target, from + (to - from) * (1 - (1 - t) ** 3)); // ease out
                if (t < 1) requestAnimationFrame(step);
            };
            show(target, from);
            requestAnimationFrame(step);
        });
    }, { threshold: 0.5 });
    counters.forEach((el) => counter.observe(el));
}