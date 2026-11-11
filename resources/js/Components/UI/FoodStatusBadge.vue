<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        status: 'terverifikasi' | 'menunggu' | 'ditolak' | string;
        label?: string;
    }>(),
    {
        status: 'terverifikasi',
    }
);

const config = computed(() => {
    switch (props.status) {
        case 'menunggu':
            return {
                bg: 'bg-[#fff1d6]',
                text: 'text-[#9a4a00]',
                dot: 'bg-[#9a4a00]',
                defaultLabel: 'Menunggu',
            };
        case 'ditolak':
            return {
                bg: 'bg-[#fde8e6]',
                text: 'text-[#b42318]',
                dot: 'bg-[#b42318]',
                defaultLabel: 'Ditolak',
            };
        case 'terverifikasi':
        default:
            return {
                bg: 'bg-[#e6f6ec]',
                text: 'text-[#0b7a46]',
                dot: 'bg-[#0b7a46]',
                defaultLabel: 'Terverifikasi',
            };
    }
});
</script>

<template>
    <span
        :class="[
            'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold whitespace-nowrap',
            config.bg,
            config.text
        ]"
    >
        <span :class="['w-1.5 h-1.5 rounded-full shrink-0', config.dot]"></span>
        <span>{{ label || config.defaultLabel }}</span>
    </span>
</template>
