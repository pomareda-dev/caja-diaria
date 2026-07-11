import { onMounted, onUnmounted } from 'vue';

export interface KeyboardShortcut {
    /** The event.key string to match (e.g. 'ArrowLeft', 'n', 'Escape') */
    key: string;
    /** Called when the shortcut matches */
    handler: (event: KeyboardEvent) => void;
    /**
     * If true, compares event.key.toLowerCase() so the handler
     * fires regardless of Shift state. Default false.
     */
    ignoreShift?: boolean;
}

export interface UseKeyboardShortcutsOptions {
    /**
     * Optional function that returns true when a dialog is open.
     * When true, all shortcuts are suppressed.
     */
    isDialogOpen?: () => boolean;
}

/**
 * Register global keyboard shortcuts with built-in guards:
 * - Does not fire when the user is typing in an input/textarea/select/contenteditable
 * - Does not fire when `isDialogOpen` returns true (page-specific)
 *
 * Attaches the listener on mount and cleans up on unmount.
 */
export function useKeyboardShortcuts(
    shortcuts: KeyboardShortcut[],
    options?: UseKeyboardShortcutsOptions,
): void {
    function handler(event: KeyboardEvent): void {
        // Guard: skip if the user is typing in a form control
        const tag = document.activeElement?.tagName?.toLowerCase();

        if (
            tag === 'input' ||
            tag === 'textarea' ||
            tag === 'select' ||
            (document.activeElement as HTMLElement | null)?.isContentEditable
        ) {
            return;
        }

        // Guard: skip if a dialog is open
        if (options?.isDialogOpen?.()) {
            return;
        }

        for (const shortcut of shortcuts) {
            const matches = shortcut.ignoreShift
                ? event.key.toLowerCase() === shortcut.key
                : event.key === shortcut.key;

            if (matches) {
                event.preventDefault();
                shortcut.handler(event);

                return;
            }
        }
    }

    onMounted(() => window.addEventListener('keydown', handler));
    onUnmounted(() => window.removeEventListener('keydown', handler));
}
