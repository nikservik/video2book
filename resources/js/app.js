import './bootstrap';
import '@tailwindplus/elements';

const THEME_KEY = 'video2book:theme';

const getStoredTheme = () => {
    try {
        const storedTheme = window.localStorage.getItem(THEME_KEY);

        if (storedTheme === 'light' || storedTheme === 'dark') {
            return storedTheme;
        }
    } catch (error) {
        return null;
    }

    return null;
};

const getSystemTheme = () => {
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        return 'dark';
    }

    return 'light';
};

const syncThemeToggleState = (theme) => {
    const controls = document.querySelectorAll('[data-theme-set]');

    controls.forEach((control) => {
        const controlTheme = control.getAttribute('data-theme-set');
        const isActive = controlTheme === theme;

        control.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        control.classList.remove('bg-white', 'bg-gray-200', 'dark:bg-gray-600', 'shadow-sm');

        if (!isActive) {
            return;
        }

        if (theme === 'light') {
            control.classList.add('bg-white', 'shadow-sm');
            return;
        }

        control.classList.add('bg-gray-200', 'dark:bg-gray-600', 'shadow-sm');
    });
};

const applyTheme = (theme, persist = true) => {
    const isDark = theme === 'dark';
    document.documentElement.classList.toggle('dark', isDark);

    if (persist) {
        try {
            window.localStorage.setItem(THEME_KEY, theme);
        } catch (error) {
            // Ignore localStorage failures.
        }
    }

    syncThemeToggleState(theme);
};

const initializeThemeToggle = () => {
    const storedTheme = getStoredTheme();
    const fallbackTheme = document.documentElement.classList.contains('dark') ? 'dark' : null;
    applyTheme(storedTheme ?? fallbackTheme ?? getSystemTheme(), false);
};

document.addEventListener('click', (event) => {
    const themeControl = event.target.closest('[data-theme-set]');

    if (themeControl) {
        const requestedTheme = themeControl.getAttribute('data-theme-set');

        if (requestedTheme === 'light' || requestedTheme === 'dark') {
            applyTheme(requestedTheme);
        }

        return;
    }
});

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-mobile-toggle]');

    if (!toggle) {
        return;
    }

    const menuId = toggle.getAttribute('data-mobile-toggle');
    const menu = document.getElementById(menuId);
    const openIcon = toggle.querySelector('.menu-icon-open');
    const closeIcon = toggle.querySelector('.menu-icon-close');

    if (!menu) {
        return;
    }

    const isHidden = menu.classList.contains('hidden');
    menu.classList.toggle('hidden', !isHidden);
    toggle.setAttribute('aria-expanded', isHidden ? 'true' : 'false');

    if (openIcon && closeIcon) {
        openIcon.classList.toggle('hidden', isHidden);
        closeIcon.classList.toggle('hidden', !isHidden);
    }
});

initializeThemeToggle();
document.addEventListener('livewire:navigated', initializeThemeToggle);
