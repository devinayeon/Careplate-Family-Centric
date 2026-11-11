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
        :aria-label="isDark ? 'Beralih ke Mode Terang' : 'Beralih ke Mode Gelap'"
        class="relative inline-flex items-center justify-center size-9 md:size-10 rounded-xl border transition-all cursor-pointer focus:outline-none focus:ring-2 focus:ring-[#0b8043]/30
               bg-white hover:bg-gray-50 border-gray-200 text-gray-600
               dark:bg-[#1c1c1c] dark:hover:bg-[#262626] dark:border-[#2e2e2e] dark:text-[#f0efea] shadow-2xs"
    >
        <!-- Sun Icon (shown in dark mode) -->
        <Sun
            v-if="isDark"
            class="w-4 h-4 md:w-4.5 md:h-4.5 text-amber-400 transition-transform duration-300 rotate-0 scale-100"
        />

        <!-- Moon Icon (shown in light mode) -->
        <Moon
            v-else
            class="w-4 h-4 md:w-4.5 md:h-4.5 text-gray-600 transition-transform duration-300 rotate-0 scale-100"
        />
    </button>
</template>
