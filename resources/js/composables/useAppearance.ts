import { onMounted, onUnmounted, ref } from 'vue';

export type Appearance = 'system' | 'light' | 'dark';

const STORAGE_KEY = 'appearance';

// Shared across components so every switcher stays in sync.
const appearance = ref<Appearance>('system');

function readStored(): Appearance {
    try {
        const value = localStorage.getItem(STORAGE_KEY);

        return value === 'light' || value === 'dark' ? value : 'system';
    } catch {
        return 'system';
    }
}

function prefersDark(): boolean {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function apply(value: Appearance): void {
    const dark = value === 'dark' || (value === 'system' && prefersDark());

    document.documentElement.classList.toggle('dark', dark);
}

/**
 * Light/dark/system appearance, persisted in localStorage.
 * The initial class is applied by the inline script in app.blade.php.
 */
export function useAppearance() {
    const media = () => window.matchMedia('(prefers-color-scheme: dark)');
    const onSystemChange = () => {
        if (appearance.value === 'system') {
            apply('system');
        }
    };

    onMounted(() => {
        appearance.value = readStored();
        media().addEventListener('change', onSystemChange);
    });

    onUnmounted(() => {
        media().removeEventListener('change', onSystemChange);
    });

    function updateAppearance(value: Appearance): void {
        appearance.value = value;

        try {
            if (value === 'system') {
                localStorage.removeItem(STORAGE_KEY);
            } else {
                localStorage.setItem(STORAGE_KEY, value);
            }
        } catch {
            // Storage unavailable (private mode); the choice lasts for this page only.
        }

        apply(value);
    }

    return { appearance, updateAppearance };
}
