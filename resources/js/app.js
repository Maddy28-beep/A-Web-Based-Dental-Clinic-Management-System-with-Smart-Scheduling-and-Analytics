import './bootstrap';

const THEME_STORAGE_KEY = 'clinic_theme';

function getStoredTheme() {
    try {
        const v = localStorage.getItem(THEME_STORAGE_KEY);
        return v === 'dark' || v === 'light' ? v : null;
    } catch {
        return null;
    }
}

function getPreferredTheme() {
    try {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    } catch {
        return 'light';
    }
}

function getThemeToggleButtons() {
    const byData = Array.from(document.querySelectorAll('[data-theme-toggle]'));
    const byId = document.getElementById('toggleTheme');
    return byId ? [...byData, byId] : byData;
}

function applyTheme(theme) {
    const isDark = theme === 'dark';
    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';

    for (const btn of getThemeToggleButtons()) {
        btn.textContent = isDark ? 'Light Mode' : 'Dark Mode';
    }
}

function setStoredTheme(theme) {
    try {
        localStorage.setItem(THEME_STORAGE_KEY, theme);
    } catch {
        // ignore
    }
}

function initTheme() {
    applyTheme(getStoredTheme() ?? getPreferredTheme());

    for (const btn of getThemeToggleButtons()) {
        btn.addEventListener('click', () => {
            const isDark = document.documentElement.classList.contains('dark');
            const next = isDark ? 'light' : 'dark';
            setStoredTheme(next);
            applyTheme(next);
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTheme);
} else {
    initTheme();
}
