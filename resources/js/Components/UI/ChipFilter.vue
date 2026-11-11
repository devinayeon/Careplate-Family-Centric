<script setup lang="ts">
defineProps<{
    label: string;
    count?: number | null;
    active?: boolean;
}>();

defineEmits<{
    (e: 'click'): void;
}>();
</script>

<template>
    <button
        type="button"
        @click="$emit('click')"
        class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-full px-4 text-xs font-bold whitespace-nowrap transition-all select-none sm:h-10 sm:text-sm"
        :class="
            active
                ? 'bg-[#191919] text-white shadow-xs dark:bg-[#f0efea] dark:text-[#121212]'
                : 'border border-transparent bg-[#f4f3f0] text-[#191919] hover:bg-[#eae8e3] dark:border-[#2e2e2e] dark:bg-[#1c1c1c] dark:text-[#f0efea] dark:hover:bg-[#262626]'
        "
    >
        <span>{{ label }}</span>
        <span
            v-if="typeof count === 'number'"
            class="py-0.2 rounded-full px-1.5 text-[11px] font-semibold opacity-80"
            :class="
                active
                    ? 'bg-white/20 text-white dark:bg-black/20 dark:text-[#121212]'
                    : 'bg-black/10 text-gray-800 dark:bg-white/10 dark:text-[#f0efea]'
            "
        >
            {{ count }}
        </span>
    </button>
</template>
