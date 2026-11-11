<script setup lang="ts">
import { useTheme } from '@/composables/useTheme';
import { Sun, Moon } from 'lucide-vue-next';

defineProps<{
    variant?: 'icon' | 'pill';
}>();

const { isDark, toggleTheme } = useTheme();
</script>

<template>
    <button
        type="button"
        @click="toggleTheme"
        :title="isDark ? 'Beralih ke Mode Terang' : 'Beralih ke Mode Gelap'"
        :aria-label="
            isDark ? 'Beralih ke Mode Terang' : 'Beralih ke Mode Gelap'
        "
        class="relative inline-flex size-9 cursor-pointer items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-2xs transition-all hover:bg-gray-50 focus:ring-2 focus:ring-[#0b8043]/30 focus:outline-none md:size-10 dark:border-[#2e2e2e] dark:bg-[#1c1c1c] dark:text-[#f0efea] dark:hover:bg-[#262626]"
    >
        <!-- Sun Icon (shown in dark mode) -->
        <Sun
            v-if="isDark"
            class="h-4 w-4 scale-100 rotate-0 text-amber-400 transition-transform duration-300 md:h-4.5 md:w-4.5"
        />

        <!-- Moon Icon (shown in light mode) -->
        <Moon
            v-else
            class="h-4 w-4 scale-100 rotate-0 text-gray-600 transition-transform duration-300 md:h-4.5 md:w-4.5"
        />
    </button>
</template>
