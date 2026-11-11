<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="transform -translate-y-1 opacity-0"
        enter-to-class="transform translate-y-0 opacity-100"
    >
        <div
            v-if="message"
            class="flex items-start gap-1.5 pt-1.5 text-xs font-medium text-[#d9381e] sm:text-sm"
        >
            <svg
                class="mt-0.5 h-4 w-4 shrink-0 text-[#d9381e]"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path
                    fill-rule="evenodd"
                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                    clip-rule="evenodd"
                />
            </svg>
            <span class="leading-tight">{{ message }}</span>
        </div>
    </transition>
</template>
