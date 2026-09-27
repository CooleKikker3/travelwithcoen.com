//
import './map';
import './wall';


// Sensitive images (articles and gallery lightbox): reveal after the visitor chooses to see them.
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-sensitive-reveal]');
    if (!button) return;
    event.preventDefault();
    button.closest('[data-sensitive]')?.classList.add('is-revealed');
});
