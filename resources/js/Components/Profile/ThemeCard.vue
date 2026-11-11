<script setup lang="ts">
import { useTheme, type ThemeMode } from '@/composables/useTheme';

const { theme, setTheme } = useTheme();

const options: { value: ThemeMode; label: string }[] = [
    { value: 'light', label: 'Terang' },
    { value: 'dark', label: 'Gelap' },
    { value: 'system', label: 'Ikuti sistem' },
];
</script>

<template>
    <section
        class="rounded-3xl bg-white p-6 shadow-[0_1px_2px_rgba(30,35,33,0.06),0_4px_12px_rgba(30,35,33,0.06)] dark:bg-[#1c1c1c]"
        aria-labelledby="theme-title"
    >
        <h2
            id="theme-title"
            class="font-display text-xl font-bold text-[#191919] dark:text-[#f0efea]"
        >
            Tampilan
        </h2>
        <fieldset class="mt-4">
            <legend
                class="text-sm font-semibold text-[#191919] dark:text-[#f0efea]"
            >
                Tema
            </legend>
            <div
                class="mt-2 flex overflow-hidden rounded-xl border-2 border-[#191919] dark:border-[#f0efea]"
            >
                <label
                    v-for="option in options"
                    :key="option.value"
                    class="relative flex h-11 flex-1 cursor-pointer items-center justify-center px-2 text-sm font-semibold whitespace-nowrap text-[#191919] has-[:checked]:bg-[#191919] has-[:checked]:text-white has-[:focus-visible]:outline-2 has-[:focus-visible]:-outline-offset-4 has-[:focus-visible]:outline-[#0b8043] dark:text-[#f0efea] dark:has-[:checked]:bg-[#f0efea] dark:has-[:checked]:text-[#191919] dark:has-[:focus-visible]:outline-[#3fcb7a]"
                >
                    <input
                        type="radio"
                        name="tema"
                        class="sr-only"
                        :value="option.value"
                        :checked="theme === option.value"
                        @change="setTheme(option.value)"
                    />
                    {{ option.label }}
                </label>
            </div>
        </fieldset>
    </section>
</template>
