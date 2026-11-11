<script setup lang="ts">
import { computed } from 'vue';
import {
    Coffee,
    Cookie,
    CookingPot,
    Pencil,
    Plus,
    Soup,
    Trash2,
    Utensils,
} from 'lucide-vue-next';
import { idNumber } from '@/lib/foodUi';

export type MealKey = 'sarapan' | 'makan_siang' | 'makan_malam' | 'camilan';

export interface MealItem {
    id: number;
    makanan_id: number;
    nama: string;
    foto_makanan_url: string | null;
    jumlah_porsi: number;
    takaran: string;
    jam: string;
    total_kalori: number;
    protein: number;
    lemak: number;
    karbohidrat: number;
}

export interface MealGroup {
    key: MealKey;
    label: string;
    total_kalori: number;
    items: MealItem[];
}

const props = defineProps<{ group: MealGroup }>();

const emit = defineEmits<{
    (e: 'add', key: MealKey): void;
    (e: 'edit', item: MealItem, key: MealKey): void;
    (e: 'remove', item: MealItem, group: MealGroup): void;
}>();

const ICONS = {
    sarapan: Coffee,
    makan_siang: Soup,
    makan_malam: CookingPot,
    camilan: Cookie,
} as const;

const headingId = computed(() => `meal-${props.group.key}`);
const subtitle = computed(() =>
    props.group.items.length
        ? `${idNumber.format(props.group.total_kalori)} kkal, ${props.group.items.length} makanan`
        : 'Belum ada',
);

const iconButton =
    'flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-[#191919] hover:bg-[#f4f3f0] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919] dark:text-[#f0efea] dark:hover:bg-[#262626] dark:focus-visible:outline-[#f0efea]';
</script>

<template>
    <section
        class="rounded-3xl bg-white p-5 shadow-[0_1px_2px_rgba(30,35,33,0.06),0_4px_12px_rgba(30,35,33,0.06)] sm:p-6 dark:bg-[#1c1c1c]"
        :aria-labelledby="headingId"
    >
        <header class="flex items-center gap-3">
            <span
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#e4f6ea] dark:bg-[#17321f]"
            >
                <component
                    :is="ICONS[group.key]"
                    class="h-5 w-5 text-[#0b8043] dark:text-[#3fcb7a]"
                    aria-hidden="true"
                />
            </span>
            <div class="min-w-0 flex-1">
                <h2
                    :id="headingId"
                    class="font-display text-xl font-bold text-[#191919] dark:text-[#f0efea]"
                >
                    {{ group.label }}
                </h2>
                <p class="text-sm text-[#5e5e5e] dark:text-[#a3a3a3]">
                    {{ subtitle }}
                </p>
            </div>
            <button
                type="button"
                class="inline-flex h-10 shrink-0 items-center gap-1.5 rounded-full px-3 text-sm font-semibold text-[#0b8043] hover:bg-[#e4f6ea] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919] dark:text-[#3fcb7a] dark:hover:bg-[#17321f] dark:focus-visible:outline-[#f0efea]"
                :aria-label="`Tambah ${group.label.toLowerCase()}`"
                @click="emit('add', group.key)"
            >
                <Plus class="h-4 w-4" aria-hidden="true" /> Tambah
            </button>
        </header>

        <ul
            v-if="group.items.length"
            class="mt-4 divide-y divide-[#eceae5] border-t border-[#eceae5] dark:divide-[#2e2e2e] dark:border-[#2e2e2e]"
        >
            <li
                v-for="item in group.items"
                :key="item.id"
                class="flex items-center gap-3 py-3 sm:gap-4"
            >
                <div
                    class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-[#f4f3f0] dark:bg-[#262626]"
                >
                    <img
                        v-if="item.foto_makanan_url"
                        :src="item.foto_makanan_url"
                        :alt="`Foto ${item.nama}`"
                        class="h-full w-full object-cover"
                        loading="lazy"
                    />
                    <Utensils
                        v-else
                        class="h-6 w-6 text-[#0b8043] dark:text-[#3fcb7a]"
                        aria-hidden="true"
                    />
                </div>
                <div class="min-w-0 flex-1">
                    <p
                        class="truncate text-base font-semibold text-[#191919] dark:text-[#f0efea]"
                    >
                        {{ item.nama }}
                    </p>
                    <p class="text-sm text-[#5e5e5e] dark:text-[#a3a3a3]">
                        {{ item.takaran }} • {{ item.jam }}
                    </p>
                    <p
                        class="text-sm font-semibold text-[#191919] sm:hidden dark:text-[#f0efea]"
                    >
                        {{ idNumber.format(item.total_kalori) }} kkal
                    </p>
                </div>
                <p
                    class="hidden shrink-0 text-base font-semibold text-[#191919] tabular-nums sm:block dark:text-[#f0efea]"
                >
                    {{ idNumber.format(item.total_kalori) }} kkal
                </p>
                <button
                    type="button"
                    :class="iconButton"
                    :aria-label="`Ubah ${item.nama}`"
                    @click="emit('edit', item, group.key)"
                >
                    <Pencil class="h-4 w-4" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    :class="iconButton"
                    :aria-label="`Hapus ${item.nama}`"
                    @click="emit('remove', item, group)"
                >
                    <Trash2 class="h-4 w-4" aria-hidden="true" />
                </button>
            </li>
        </ul>

        <div
            v-else
            class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border-2 border-dashed border-[#eceae5] px-4 py-4 dark:border-[#2e2e2e]"
        >
            <p class="text-base text-[#5e5e5e] dark:text-[#a3a3a3]">
                Belum ada catatan {{ group.label.toLowerCase() }}.
            </p>
            <button
                type="button"
                class="text-sm font-semibold text-[#0b8043] underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919] dark:text-[#3fcb7a] dark:focus-visible:outline-[#f0efea]"
                @click="emit('add', group.key)"
            >
                Catat {{ group.label }}
            </button>
        </div>
    </section>
</template>
