<script setup lang="ts">
import { computed } from 'vue';
import { Loader2 } from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        variant?: 'primary' | 'outline' | 'danger' | 'ghost';
        type?: 'button' | 'submit' | 'reset';
        disabled?: boolean;
        loading?: boolean;
        size?: 'sm' | 'md' | 'lg';
    }>(),
    {
        variant: 'primary',
        type: 'button',
        disabled: false,
        loading: false,
        size: 'md',
    },
);

const variantClasses = computed(() => {
    switch (props.variant) {
        case 'primary':
            return 'bg-[#0b8043] hover:bg-[#086637] dark:bg-[#3fcb7a] dark:hover:bg-[#34aa66] text-white dark:text-[#121212] shadow-2xs';
        case 'outline':
            return 'bg-white dark:bg-[#1c1c1c] border-2 border-[#191919] dark:border-[#3d3d3d] text-[#191919] dark:text-[#f0efea] hover:bg-gray-50 dark:hover:bg-[#262626]';
        case 'danger':
            return 'bg-red-50 hover:bg-red-100 dark:bg-red-950/40 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-900/50';
        case 'ghost':
            return 'bg-transparent hover:bg-gray-100 dark:hover:bg-[#262626] text-gray-700 dark:text-[#a3a3a3]';
        default:
            return 'bg-[#0b8043] dark:bg-[#3fcb7a] text-white dark:text-[#121212]';
    }
});

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'h-8 px-3 text-xs';
        case 'lg':
            return 'h-12 px-6 text-base';
        default:
            return 'h-10 sm:h-11 px-5 text-sm';
    }
});
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-full font-bold transition-all select-none active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50"
        :class="[variantClasses, sizeClasses]"
    >
        <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
        <slot />
    </button>
</template>
