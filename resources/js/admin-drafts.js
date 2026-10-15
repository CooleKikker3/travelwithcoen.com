// CMS safety net for weak connections: every few seconds the form on the current page is saved as
// a draft in this browser (localStorage). After a failed save, a reload or an expired session the
// draft can be restored. A successful save removes it. Loaded in the Filament panel (AdminPanelProvider).

// Device time zone for the server (FilamentTimezone in AdminPanelProvider): times in the CMS follow the country you are in.
document.cookie = `tz=${encodeURIComponent(Intl.DateTimeFormat().resolvedOptions().timeZone)}; path=/; max-age=31536000; SameSite=Lax`;

const PREFIX = 'twc-draft:';
const INTERVAL_MS = 4000;
const MAX_AGE_MS = 30 * 24 * 60 * 60 * 1000;
const SAVE_METHODS = ['save', 'create', 'createAnother'];

const storage = {
    get: (key) => { try { return JSON.parse(localStorage.getItem(key)); } catch { return null; } },
    set: (key, value) => { try { localStorage.setItem(key, JSON.stringify(value)); } catch { /* full or blocked */ } },
    remove: (key) => { try { localStorage.removeItem(key); } catch { /* blocked */ } },
};

// Temporary (unsaved) file uploads can't be restored later, so they are left out of drafts.
const serialize = (data) => JSON.stringify(data ?? {}, (key, value) => (typeof value === 'string' && value.startsWith('livewire-file:') ? undefined : value));

function start() {
    const root = document.querySelector('.fi-page')?.closest('[wire\\:id]');
    const wire = root && window.Livewire?.find(root.getAttribute('wire:id'));
    if (!wire || typeof wire.$get('data') !== 'object' || wire.$get('data') === null) return;

    const key = PREFIX + location.pathname;
    let saved = serialize(wire.$get('data')); // what the server has
    let last = saved;

    offerRestore(root, wire, key, saved);

    const store = () => {
        const current = serialize(wire.$get('data'));
        if (current === last) return;
        last = current;
        current === saved ? storage.remove(key) : storage.set(key, { savedAt: Date.now(), data: current });
    };
    setInterval(store, INTERVAL_MS);
    window.addEventListener('beforeunload', store);

    // A save without validation errors means the server has everything: drop the draft.
    window.Livewire.hook('commit', ({ component, commit, succeed }) => {
        if (component.id !== root.getAttribute('wire:id')) return;
        if (!(commit.calls ?? []).some((call) => SAVE_METHODS.includes(call.method))) return;

        succeed(({ snapshot }) => {
            const errors = JSON.parse(snapshot || '{}')?.memo?.errors ?? {};
            if (Object.keys(errors).length === 0) {
                saved = last = serialize(wire.$get('data'));
                storage.remove(key);
            }
        });
    });
}

function offerRestore(root, wire, key, saved) {
    const draft = storage.get(key);
    if (!draft || draft.data === saved) return;
    if (Date.now() - draft.savedAt > MAX_AGE_MS) return storage.remove(key);

    const banner = document.createElement('div');
    banner.setAttribute('role', 'alert');
    banner.style.cssText = 'display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;margin-bottom:1rem;padding:.75rem 1rem;border-radius:.75rem;background:#fef3c7;color:#78350f;font-size:.875rem';
    banner.textContent = `Er zijn niet-opgeslagen wijzigingen van ${new Date(draft.savedAt).toLocaleString('nl-NL')} gevonden in deze browser. `;

    const button = (label, primary, onClick) => {
        const el = document.createElement('button');
        el.type = 'button';
        el.textContent = label;
        el.style.cssText = `padding:.35rem .9rem;border-radius:999px;font-weight:600;cursor:pointer;${primary ? 'background:#15803d;color:#fff' : 'background:#fff;color:#78350f'}`;
        el.addEventListener('click', () => { onClick(); banner.remove(); });
        return el;
    };

    banner.append(
        button('Herstellen', true, () => wire.$set('data', { ...wire.$get('data'), ...JSON.parse(draft.data) })),
        button('Weggooien', false, () => storage.remove(key)),
    );

    root.querySelector('.fi-page')?.prepend(banner);
}

window.Livewire?.all?.().length ? start() : document.addEventListener('livewire:initialized', start);

// Connection warning: when the CMS can't reach the server (no signal, or a request that breaks off), say so instead of
// failing silently, so a save that did not arrive is noticed. It disappears with the next request that does arrive.
// Drafts (above) keep the form in this browser meanwhile.
const connection = (() => {
    let banner;
    const show = (text) => {
        if (!banner) {
            banner = document.createElement('div');
            banner.setAttribute('role', 'alert');
            banner.style.cssText = 'position:fixed;left:50%;bottom:1rem;transform:translateX(-50%);z-index:9999;max-width:calc(100% - 2rem);padding:.75rem 1rem;border-radius:.75rem;background:#7f1d1d;color:#fff;font-size:.875rem;font-weight:600;box-shadow:0 10px 30px rgb(0 0 0 / .3)';
            document.body.append(banner);
        }
        banner.textContent = text;
        banner.hidden = false;
    };
    const hide = () => banner && (banner.hidden = true);
    return { show, hide };
})();
const NO_SIGNAL = 'Geen verbinding: wat je nu opslaat komt niet aan. Je wijzigingen blijven in deze browser bewaard; probeer het opnieuw zodra je bereik hebt.';
window.addEventListener('offline', () => connection.show(NO_SIGNAL));
window.addEventListener('online', () => connection.hide());
if (!navigator.onLine) connection.show(NO_SIGNAL);

// Livewire talks to the server with fetch: a rejected request means it never arrived.
const originalFetch = window.fetch;
window.fetch = async (...args) => {
    const url = String(args[0]?.url ?? args[0] ?? '');
    try {
        const response = await originalFetch(...args);
        if (url.includes('/livewire')) {
            response.status >= 500 ? connection.show('De server gaf een fout; je wijziging is misschien niet opgeslagen. Probeer het opnieuw.') : connection.hide();
        }
        return response;
    } catch (error) {
        if (url.includes('/livewire')) connection.show(NO_SIGNAL);
        throw error;
    }
};
