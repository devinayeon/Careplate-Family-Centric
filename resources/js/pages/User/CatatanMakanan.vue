<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Plus,
    Soup,
} from 'lucide-vue-next';
import UserLayout from '@/Layouts/UserLayout.vue';
import MealGroupCard, {
    type MealGroup,
    type MealItem,
    type MealKey,
} from '@/Components/MealLog/MealGroupCard.vue';
import DailySummary, {
    type Ringkasan,
} from '@/Components/MealLog/DailySummary.vue';
import MealSheet from '@/Components/MealLog/MealSheet.vue';
import DeleteMealDialog from '@/Components/MealLog/DeleteMealDialog.vue';
import type { FoodOption } from '@/Components/MealLog/FoodSearch.vue';
import { idNumber } from '@/lib/foodUi';

interface DayCard {
    tanggal: string;
    label_hari: string;
    label_tanggal: string;
    total_kalori: number | null;
}

const props = defineProps<{
    tanggal: string;
    hariIni: string;
    judulTanggal: string;
    minggu: DayCard[];
    waktuMakan: MealGroup[];
    ringkasan: Ringkasan;
    batas: { gula: number; natrium: number; lemak: number };
    tambah: FoodOption | null;
}>();

const page = usePage();
const flash = computed(
    () =>
        (page.props.flash ?? {}) as {
            success?: string | null;
            error?: string | null;
        },
);

const isToday = computed(() => props.tanggal >= props.hariIni);

/** Shift a YYYY-MM-DD string by whole days without time-zone drift. */
function shift(date: string, days: number): string {
    const [y, m, d] = date.split('-').map(Number);
    const next = new Date(Date.UTC(y, m - 1, d + days));

    return next.toISOString().slice(0, 10);
}

function go(tanggal: string) {
    if (tanggal === props.tanggal || tanggal > props.hariIni) return;
    router.get(route('user.meals'), { tanggal }, { preserveScroll: true });
}

// Sheet and dialog both hand focus back to whatever opened them.
const addButton = ref<HTMLButtonElement | null>(null);
let trigger: HTMLElement | null = null;

function remember() {
    if (typeof document === 'undefined') return;
    const active = document.activeElement;
    trigger =
        active instanceof HTMLElement && active !== document.body
            ? active
            : null;
}

async function restoreFocus() {
    await nextTick();
    const target = trigger?.isConnected ? trigger : addButton.value;
    target?.focus();
    trigger = null;
}

const sheetOpen = ref(false);
const sheetItem = ref<MealItem | null>(null);
const sheetKey = ref<MealKey>('sarapan');
const sheetPreset = ref<FoodOption | null>(null);

/** Default meal time from the clock, used by the hero button. */
function keyForNow(): MealKey {
    const hour = new Date().getHours();
    if (hour < 10) return 'sarapan';
    if (hour < 15) return 'makan_siang';
    if (hour < 21) return 'makan_malam';

    return 'camilan';
}

function openAdd(key: MealKey = keyForNow(), preset: FoodOption | null = null) {
    remember();
    sheetItem.value = null;
    sheetKey.value = key;
    sheetPreset.value = preset;
    sheetOpen.value = true;
}

function openEdit(item: MealItem, key: MealKey) {
    remember();
    sheetItem.value = item;
    sheetKey.value = key;
    sheetPreset.value = null;
    sheetOpen.value = true;
}

function closeSheet() {
    sheetOpen.value = false;
    sheetPreset.value = null;
    void restoreFocus();
}

// "Catat" from another page arrives with a food already chosen.
watch(
    () => props.tambah,
    (food) => {
        if (food) openAdd(keyForNow(), food);
    },
    { immediate: true },
);

const deleteTarget = ref<{ item: MealItem; groupLabel: string } | null>(null);

function askDelete(item: MealItem, group: MealGroup) {
    remember();
    deleteTarget.value = { item, groupLabel: group.label };
}

function closeDelete() {
    deleteTarget.value = null;
    void restoreFocus();
}

const navButton =
    'flex h-10 w-10 items-center justify-center rounded-full text-[#191919] hover:bg-[#f4f3f0] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919] disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent dark:text-[#f0efea] dark:hover:bg-[#262626] dark:focus-visible:outline-[#f0efea]';
</script>

<template>
    <Head title="Catatan Makanan" />
    <UserLayout>
        <div class="flex flex-col gap-6">
            <p
                v-if="flash.success"
                class="flex items-center gap-2 rounded-2xl bg-[#e4f6ea] px-4 py-3 text-sm font-semibold text-[#0b7a46] dark:bg-[#17321f] dark:text-[#3fcb7a]"
                role="status"
            >
                <CheckCircle2 class="h-4 w-4 shrink-0" aria-hidden="true" />
                {{ flash.success }}
            </p>
            <p
                v-if="flash.error"
                class="flex items-center gap-2 rounded-2xl bg-[#ffece5] px-4 py-3 text-sm font-semibold text-[#a52f08] dark:bg-[#3a1e14] dark:text-red-300"
                role="alert"
            >
                <AlertTriangle class="h-4 w-4 shrink-0" aria-hidden="true" />
                {{ flash.error }}
            </p>

            <header
                class="flex flex-wrap items-center justify-between gap-5 rounded-3xl bg-[#e4f6ea] px-6 py-6 sm:px-7 sm:py-8 dark:bg-[#17321f]"
            >
                <h1
                    class="font-display text-3xl font-extrabold text-[#191919] dark:text-[#f0efea]"
                >
                    Catatan Makanan
                </h1>
                <div class="flex items-center gap-6">
                    <div class="flex flex-col items-stretch gap-3 sm:items-end">
                        <nav
                            aria-label="Pilih tanggal"
                            class="flex items-center justify-between gap-1 rounded-full bg-white px-1 py-1 dark:bg-[#1c1c1c]"
                        >
                            <button
                                type="button"
                                :class="navButton"
                                aria-label="Hari sebelumnya"
                                @click="go(shift(tanggal, -1))"
                            >
                                <ChevronLeft
                                    class="h-5 w-5"
                                    aria-hidden="true"
                                />
                            </button>
                            <p
                                class="px-2 text-sm font-semibold whitespace-nowrap text-[#191919] dark:text-[#f0efea]"
                                aria-live="polite"
                            >
                                {{ judulTanggal }}
                            </p>
                            <button
                                type="button"
                                :class="navButton"
                                aria-label="Hari berikutnya"
                                :disabled="isToday"
                                @click="go(shift(tanggal, 1))"
                            >
                                <ChevronRight
                                    class="h-5 w-5"
                                    aria-hidden="true"
                                />
                            </button>
                        </nav>
                        <button
                            ref="addButton"
                            type="button"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-full bg-[#0b8043] px-6 text-sm font-semibold text-white hover:bg-[#086637] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919] dark:focus-visible:outline-[#f0efea]"
                            @click="openAdd()"
                        >
                            <Plus class="h-4 w-4" aria-hidden="true" /> Tambah
                            Catatan
                        </button>
                    </div>
                    <Soup
                        class="hidden h-20 w-20 shrink-0 text-[#0b8043] lg:block dark:text-[#3fcb7a]"
                        aria-hidden="true"
                    />
                </div>
            </header>

            <nav aria-label="Tujuh hari terakhir">
                <ul class="grid grid-cols-7 gap-1 sm:gap-3">
                    <li v-for="day in minggu" :key="day.tanggal">
                        <button
                            type="button"
                            class="flex w-full flex-col items-center gap-0.5 rounded-xl px-0.5 py-2.5 text-center focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919] sm:rounded-2xl sm:px-1 sm:py-3 dark:focus-visible:outline-[#f0efea]"
                            :class="
                                day.tanggal === tanggal
                                    ? 'bg-[#191919] text-white dark:bg-[#f0efea] dark:text-[#121212]'
                                    : 'bg-white text-[#191919] hover:bg-[#f4f3f0] dark:bg-[#1c1c1c] dark:text-[#f0efea] dark:hover:bg-[#262626]'
                            "
                            :aria-current="
                                day.tanggal === tanggal ? 'date' : undefined
                            "
                            @click="go(day.tanggal)"
                        >
                            <span class="text-xs">{{ day.label_hari }}</span>
                            <span
                                class="text-[11px] font-bold whitespace-nowrap sm:text-sm"
                                >{{ day.label_tanggal }}</span
                            >
                            <span
                                class="text-[11px] tabular-nums sm:text-xs"
                                :class="
                                    day.tanggal === tanggal
                                        ? 'text-[#ffd27a] dark:text-[#0b8043]'
                                        : 'text-[#5e5e5e] dark:text-[#a3a3a3]'
                                "
                            >
                                <template v-if="day.total_kalori === null"
                                    ><span aria-hidden="true" class="sm:hidden"
                                        >–</span
                                    ><span class="max-sm:sr-only"
                                        >Kosong</span
                                    ></template
                                >
                                <template v-else
                                    >{{ idNumber.format(day.total_kalori)
                                    }}<span class="max-sm:sr-only">
                                        kkal</span
                                    ></template
                                >
                            </span>
                        </button>
                    </li>
                </ul>
            </nav>

            <div class="grid items-start gap-6 lg:grid-cols-[1fr_22rem]">
                <div class="flex flex-col gap-6">
                    <MealGroupCard
                        v-for="group in waktuMakan"
                        :key="group.key"
                        :group="group"
                        @add="openAdd"
                        @edit="openEdit"
                        @remove="askDelete"
                    />
                </div>
                <DailySummary :ringkasan="ringkasan" :batas="batas" />
            </div>
        </div>

        <MealSheet
            :open="sheetOpen"
            :item="sheetItem"
            :meal-key="sheetKey"
            :tanggal="tanggal"
            :preset="sheetPreset"
            @close="closeSheet"
        />
        <DeleteMealDialog :target="deleteTarget" @close="closeDelete" />
    </UserLayout>
</template>
