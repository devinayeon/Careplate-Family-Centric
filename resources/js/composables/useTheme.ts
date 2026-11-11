import { ref, computed } from 'vue';

export type ThemeMode = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'careplate_theme';

// Shared singleton state so all components stay perfectly in sync
const currentTheme = ref<ThemeMode>('system');
const systemIsDark = ref(false);
const isInitialized = ref(false);

const applyThemeToDOM = (dark: boolean) => {
    if (typeof document === 'undefined') return;
    if (dark) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
};

export function useTheme() {
    const isDark = computed(() => {
        if (currentTheme.value === 'dark') return true;
        if (currentTheme.value === 'light') return false;
        return systemIsDark.value;
    });

    const initTheme = () => {
        if (isInitialized.value || typeof window === 'undefined') return;

        // 1. Read stored preference
        const stored = localStorage.getItem(STORAGE_KEY) as ThemeMode | null;
        if (stored === 'light' || stored === 'dark' || stored === 'system') {
            currentTheme.value = stored;
        } else {
            currentTheme.value = 'system';
        }

        // 2. Query system preference
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        systemIsDark.value = mediaQuery.matches;

        // 3. Listen for system preference changes
        mediaQuery.addEventListener('change', (e) => {
            systemIsDark.value = e.matches;
            if (currentTheme.value === 'system') {
                applyThemeToDOM(e.matches);
            }
        });

        // 4. Initial DOM application
        applyThemeToDOM(isDark.value);
        isInitialized.value = true;
    };

    const setTheme = (mode: ThemeMode) => {
        currentTheme.value = mode;
        if (typeof window !== 'undefined') {
            localStorage.setItem(STORAGE_KEY, mode);
        }
        applyThemeToDOM(isDark.value);
    };

    const toggleTheme = () => {
        // Toggle between light and dark
        const nextMode = isDark.value ? 'light' : 'dark';
        setTheme(nextMode);
    };

    // Auto initialize if not yet
    if (!isInitialized.value && typeof window !== 'undefined') {
        initTheme();
    }

    return {
        theme: currentTheme,
        isDark,
        setTheme,
        toggleTheme,
    };
}
