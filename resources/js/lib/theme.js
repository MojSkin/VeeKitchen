/**
 * Light/dark theme plumbing. The blade shell resolves the stored theme
 * before first paint; this module keeps the DOM in sync afterwards.
 */

const STORAGE_KEY = 'veekitchen.theme';

export function currentTheme() {
    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}

export function applyTheme(theme) {
    document.documentElement.classList.toggle('dark', theme === 'dark');

    try {
        localStorage.setItem(STORAGE_KEY, theme);
    } catch {
        // Private-mode storage quota — the toggle still works in-page.
    }
}

export function toggleTheme() {
    const next = currentTheme() === 'dark' ? 'light' : 'dark';

    applyTheme(next);

    return next;
}

/**
 * Follow OS scheme changes only while the user has not expressed a
 * preference — matching the blade shell's resolution order.
 */
export function watchSystemTheme() {
    const media = window.matchMedia('(prefers-color-scheme: dark)');

    media.addEventListener('change', (event) => {
        let stored = null;

        try {
            stored = localStorage.getItem(STORAGE_KEY);
        } catch {
            // Ignore unreadable storage; fall back to following the OS.
        }

        if (stored !== 'light' && stored !== 'dark') {
            document.documentElement.classList.toggle('dark', event.matches);
        }
    });
}
