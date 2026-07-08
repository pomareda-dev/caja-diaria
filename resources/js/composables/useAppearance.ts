import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';
import { router } from '@inertiajs/vue3';

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

    for (const cls of el.classList) {
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

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const savedAppearance = getStoredAppearance();
    updateTheme(savedAppearance || 'system');

    mediaQuery()?.addEventListener('change', handleSystemThemeChange);

    // Apply palette from Inertia shared props (fallback to 'default')
    let initialTheme: string = 'default';

    try {
        const r = router as unknown as { page: { props: Record<string, unknown> } };

        const auth = r.page?.props?.auth as Record<string, unknown> | undefined;
        const user = auth?.user as Record<string, unknown> | undefined;
        const settings = user?.settings as Record<string, unknown> | undefined;
        const pageTheme = settings?.theme;

        if (pageTheme && typeof pageTheme === 'string') {
            // Defensive check: reject stale/legacy keys gracefully
            initialTheme = (VALID_THEMES as readonly string[]).includes(pageTheme)
                ? pageTheme
                : 'default';
        }
    } catch {
        // router.page not yet available — use default
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
