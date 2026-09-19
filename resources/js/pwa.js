/**
 * Registers the PWA service worker in production builds.
 * A no-op in dev until the service worker itself is implemented (later phase).
 */
export function registerServiceWorker() {
    if (!('serviceWorker' in navigator) || import.meta.env.DEV) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Registration is best-effort; the app still works without it.
        });
    });
}
