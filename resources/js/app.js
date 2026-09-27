//
import './map';

// Gallery: replace a YouTube thumbnail with the (privacy-friendly) player only when clicked.
document.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-youtube]');
    if (!link) return;
    event.preventDefault();
    const player = document.createElement('iframe');
    player.src = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(link.dataset.youtube)}?autoplay=1`;
    player.title = link.getAttribute('aria-label') ?? 'YouTube video';
    player.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
    player.allowFullscreen = true;
    player.className = 'aspect-square w-full rounded-xl';
    link.replaceWith(player);
});
