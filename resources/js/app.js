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
    const onScroll = () => nav.classList.toggle('is-scrolled', window.scrollY > 24);
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
