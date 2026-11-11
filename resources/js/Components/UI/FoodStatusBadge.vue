<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        status: 'terverifikasi' | 'menunggu' | 'ditolak' | string;
        label?: string;
    }>(),
    {
        status: 'terverifikasi',
    },
);

const config = computed(() => {
    switch (props.status) {
        case 'menunggu':
            return {
                bg: 'bg-[#fff1d6] dark:bg-amber-950/60 dark:border dark:border-amber-800/40',
                text: 'text-[#9a4a00] dark:text-amber-300',
                dot: 'bg-[#9a4a00] dark:bg-amber-400',
                defaultLabel: 'Menunggu',
            };
        case 'ditolak':
            return {
                bg: 'bg-[#fde8e6] dark:bg-red-950/60 dark:border dark:border-red-900/40',
                text: 'text-[#b42318] dark:text-red-300',
                dot: 'bg-[#b42318] dark:bg-red-400',
                defaultLabel: 'Ditolak',
            };
        case 'terverifikasi':
        default:
            return {
                bg: 'bg-[#e6f6ec] dark:bg-[#17321f] dark:border dark:border-emerald-800/40',
                text: 'text-[#0b7a46] dark:text-[#3fcb7a]',
                dot: 'bg-[#0b7a46] dark:bg-[#3fcb7a]',
                defaultLabel: 'Terverifikasi',
            };
    }
});
</script>

<template>
    <span
        :class="[
            'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap',
            config.bg,
            config.text,
        ]"
    >
        <span :class="['h-1.5 w-1.5 shrink-0 rounded-full', config.dot]"></span>
        <span>{{ label || config.defaultLabel }}</span>
    </span>
</template>
