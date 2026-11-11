<script setup lang="ts">
import { computed } from 'vue';
import { BadgeCheck, Clock } from 'lucide-vue-next';

const props = defineProps<{
    profil: {
        name: string;
        email: string;
        peran: string | null;
        usia: number | null;
    };
}>();

const initials = computed(
    () =>
        props.profil.name
            .split(/\s+/)
            .filter(Boolean)
            .map((part) => part[0])
            .slice(0, 2)
            .join('')
            .toUpperCase() || 'U',
);
</script>

<template>
    <section
        class="flex flex-col items-center rounded-3xl bg-white p-6 text-center shadow-[0_1px_2px_rgba(30,35,33,0.06),0_4px_12px_rgba(30,35,33,0.06)] dark:bg-[#1c1c1c]"
        aria-label="Identitas"
    >
        <span
            class="flex h-16 w-16 items-center justify-center rounded-full bg-[#e4f6ea] font-display text-2xl font-bold text-[#191919] dark:bg-[#17321f] dark:text-[#f0efea]"
            aria-hidden="true"
        >
            {{ initials }}
        </span>
        <h2
            class="mt-4 max-w-full font-display text-2xl font-bold break-words text-[#191919] dark:text-[#f0efea]"
        >
            {{ props.profil.name }}
        </h2>
        <p
            class="mt-1 max-w-full text-base break-all text-[#5e5e5e] dark:text-[#a3a3a3]"
        >
            {{ props.profil.email }}
        </p>
        <ul
            v-if="props.profil.peran || props.profil.usia !== null"
            class="mt-4 flex flex-wrap justify-center gap-2"
        >
            <li
                v-if="props.profil.peran"
                class="inline-flex items-center gap-1.5 rounded-full bg-[#e4f6ea] px-3 py-1 text-sm font-semibold text-[#0b8043] dark:bg-[#17321f] dark:text-[#3fcb7a]"
            >
                <BadgeCheck class="h-4 w-4" aria-hidden="true" />
                {{ props.profil.peran }}
            </li>
            <li
                v-if="props.profil.usia !== null"
                class="inline-flex items-center gap-1.5 rounded-full bg-[#f4f3f0] px-3 py-1 text-sm text-[#5e5e5e] dark:bg-[#262626] dark:text-[#a3a3a3]"
            >
                <Clock class="h-4 w-4" aria-hidden="true" />
                {{ props.profil.usia }} tahun
            </li>
        </ul>
    </section>
</template>
