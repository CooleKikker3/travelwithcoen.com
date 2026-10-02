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
// Google Analytics: only after the visitor says yes (banner in the layout); the choice is kept in this browser.
const analyticsId = document.body.dataset.analytics;
if (analyticsId) {
    const banner = document.querySelector('[data-cookie-banner]');
    const storage = {
        get: () => { try { return localStorage.getItem('twc-analytics'); } catch { return null; } },
        set: (value) => { try { localStorage.setItem('twc-analytics', value); } catch { /* blocked */ } },
    };
    const load = () => {
        if (window.gtag) return;
        window.dataLayer = window.dataLayer || [];
        window.gtag = function () { window.dataLayer.push(arguments); };
        window.gtag('js', new Date());
        window.gtag('config', analyticsId);
        const script = document.createElement('script');
        script.async = true;
        script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(analyticsId)}`;
        document.head.append(script);
    };
    const choice = storage.get();
    if (choice === 'yes') load();
    if (!choice && banner) banner.hidden = false;
    banner?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-cookie-choice]');
        if (!button) return;
        storage.set(button.dataset.cookieChoice);
        // Fly off (app.css), then hide; straight away when motion is reduced.
        banner.classList.add('is-leaving');
        setTimeout(() => { banner.hidden = true; banner.classList.remove('is-leaving'); }, window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 500);
        if (button.dataset.cookieChoice === 'yes') load();
        else {
            // Saying no after yes: remove GA's cookies (set on the main domain) and stop the script.
            const domain = location.hostname.replace(/^www\./, '');
            document.cookie.split(';').map((c) => c.trim().split('=')[0]).filter((name) => name.startsWith('_ga'))
                .forEach((name) => [domain, `.${domain}`, ''].forEach((d) => {
                    document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/${d ? `; domain=${d}` : ''}`;
                }));
            if (window.gtag) window.location.reload();
        }
    });
    document.querySelector('[data-cookie-settings]')?.addEventListener('click', () => { if (banner) banner.hidden = false; });
}
