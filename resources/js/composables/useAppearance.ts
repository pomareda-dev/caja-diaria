import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
};

/**
 * The 8 valid design-system theme keys.
 * Used by initializeTheme() to validate/fallback stale keys.
 */
export const VALID_THEMES = [
    'default',
    'bold-tech',
    'claude',
    'pastel-dreams',
    'quantum-rose',
    'sunny-sprout',
    'twitter',
    'violet-bloom',
] as const;

export type ValidTheme = (typeof VALID_THEMES)[number];

/**
 * Module-level ref for the current color palette key.
 * Updated by setTheme() and initializeTheme().
 */
export const themeKey: Ref<string> = ref('default');

/**
 * Apply a palette class to <html>, removing any previous .theme-* classes.
 */
function applyPalette(key: string): void {
    if (typeof document === 'undefined') {
        return;
    }

    const el = document.documentElement;

    // Snapshot the live DOMTokenList before mutating it:
    // removing classes mid-iteration would skip elements.
    for (const cls of Array.from(el.classList)) {
        if (cls.startsWith('theme-')) {
            el.classList.remove(cls);
        }
    }

    el.classList.add(`theme-${key}`);
}

/**
 * Read the XSRF-TOKEN cookie for fetch requests to Laravel.
 */
function getCsrfToken(): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    if (!match) {
        return null;
    }

    return decodeURIComponent(match[1]);
}

export function updateTheme(value: Appearance): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (value === 'system') {
        const mediaQueryList = window.matchMedia(
            '(prefers-color-scheme: dark)',
        );
        const systemTheme = mediaQueryList.matches ? 'dark' : 'light';

        document.documentElement.classList.toggle(
            'dark',
            systemTheme === 'dark',
        );
    } else {
        document.documentElement.classList.toggle('dark', value === 'dark');
    }
}

export function setTheme(key: string): void {
    themeKey.value = key;
    applyPalette(key);

    const csrfToken = getCsrfToken();

    if (csrfToken) {
        fetch('/settings', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ theme: key }),
        }).catch(() => {
            // Server save failed — UI already updated locally
        });
    }
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('appearance') as Appearance | null;
};

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const handleSystemThemeChange = () => {
    const currentAppearance = getStoredAppearance();

    updateTheme(currentAppearance || 'system');
};

export function syncThemeFromPage(page: {
    props?: {
        auth?: {
            user?: {
                settings?: Record<string, unknown>;
            } | null;
        };
    };
}): void {
    const user = page.props?.auth?.user;
    const pageTheme = user?.settings?.theme;

    let nextTheme = 'default';

    if (pageTheme && typeof pageTheme === 'string') {
        nextTheme = (VALID_THEMES as readonly string[]).includes(pageTheme)
            ? pageTheme
            : 'default';
    }

    themeKey.value = nextTheme;
    applyPalette(nextTheme);
}

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const savedAppearance = getStoredAppearance();
    updateTheme(savedAppearance || 'system');

    mediaQuery()?.addEventListener('change', handleSystemThemeChange);

    let initialTheme: string = 'default';

    try {
        const scriptEl = document.querySelector(
            'script[data-page="app"][type="application/json"]',
        );

        if (scriptEl?.textContent) {
            const pageData = JSON.parse(scriptEl.textContent) as {
                props?: {
                    auth?: {
                        user?: {
                            settings?: Record<string, unknown>;
                        };
                    };
                };
            };

            const pageTheme = pageData?.props?.auth?.user?.settings?.theme;

            if (pageTheme && typeof pageTheme === 'string') {
                initialTheme = (VALID_THEMES as readonly string[]).includes(pageTheme)
                    ? pageTheme
                    : 'default';
            }
        }
    } catch {
        // DOM parse failed — use default
    }

    themeKey.value = initialTheme;
    applyPalette(initialTheme);
}

const appearance = ref<Appearance>('system');

export function useAppearance(): UseAppearanceReturn {
    onMounted(() => {
        const savedAppearance = localStorage.getItem(
            'appearance',
        ) as Appearance | null;

        if (savedAppearance) {
            appearance.value = savedAppearance;
        }
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() => {
        if (appearance.value === 'system') {
            return prefersDark() ? 'dark' : 'light';
        }

        return appearance.value;
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;

        localStorage.setItem('appearance', value);

        setCookie('appearance', value);

        updateTheme(value);
    }

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
    };
}
