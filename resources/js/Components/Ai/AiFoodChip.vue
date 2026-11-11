<script setup lang="ts">
import { Bot, Info } from 'lucide-vue-next'

// Admin screens: is this verified food used by Chat Gizi, and if not, what to fill in (text comes from AiFoodStatus).
defineProps<{ status: { state: 'ready' | 'blocked' | string; label: string } }>()
</script>

<template>
    <span class="inline-flex max-w-[16rem] items-start gap-1.5 whitespace-normal rounded-lg px-2.5 py-1 text-xs font-semibold leading-snug" :class="status.state === 'ready' ? 'bg-[#e4f6ea] dark:bg-[#17321f] text-[#0b7a46] dark:text-[#3fcb7a]' : 'bg-[#fff4cc] dark:bg-amber-950/40 text-[#7a4b00] dark:text-amber-300'">
        <component :is="status.state === 'ready' ? Bot : Info" class="mt-0.5 h-3 w-3 shrink-0" aria-hidden="true" />
        <span>{{ status.label }}</span>
    </span>
</template>
