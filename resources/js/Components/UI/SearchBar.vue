<script setup lang="ts">
import { Search, X } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        modelValue: string;
        placeholder?: string;
    }>(),
    {
        placeholder: 'Cari nama atau email',
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', val: string): void;
    (e: 'search'): void;
    (e: 'clear'): void;
}>();

const handleInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    emit('update:modelValue', target.value);
};

const clear = () => {
    emit('update:modelValue', '');
    emit('clear');
};
</script>

<template>
    <div class="relative w-full max-w-xs sm:max-w-sm">
        <div
            class="flex h-10 items-center gap-2.5 rounded-full border border-transparent bg-[#f4f3f0] px-4 transition-all focus-within:border-[#eceae5] focus-within:bg-white focus-within:ring-2 focus-within:ring-[#0b8043]/30 hover:bg-[#eceae5] dark:bg-[#262626] dark:focus-within:border-[#3d3d3d] dark:focus-within:bg-[#1c1c1c] dark:hover:bg-[#2e2e2e]"
        >
            <Search
                class="h-4 w-4 shrink-0 text-[#5e5e5e] dark:text-[#a3a3a3]"
            />
            <input
                type="text"
                :value="modelValue"
                @input="handleInput"
                @keydown.enter="$emit('search')"
                :placeholder="placeholder"
                class="w-full border-none bg-transparent p-0 text-xs text-[#191919] placeholder-[#8a8a8a] focus:outline-hidden sm:text-sm dark:text-[#f0efea] dark:placeholder-gray-500"
            />
            <button
                v-if="modelValue"
                type="button"
                @click="clear"
                class="shrink-0 cursor-pointer rounded-full p-0.5 text-gray-500 transition-colors hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-[#3d3d3d]"
            >
                <X class="h-3.5 w-3.5" />
            </button>
        </div>
    </div>
</template>
