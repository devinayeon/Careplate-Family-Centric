<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Coffee, Cookie, CookingPot, Loader2, Soup, X } from 'lucide-vue-next';
import FoodSearch, {
    type FoodOption,
} from '@/Components/MealLog/FoodSearch.vue';
import type { MealItem, MealKey } from '@/Components/MealLog/MealGroupCard.vue';
import { idNumber } from '@/lib/foodUi';

const props = defineProps<{
    open: boolean;
    item: MealItem | null;
    mealKey: MealKey;
    tanggal: string;
    preset: FoodOption | null;
}>();

const emit = defineEmits<{ (e: 'close'): void }>();

const MEALS = [
    { key: 'sarapan', label: 'Sarapan', icon: Coffee },
    { key: 'makan_siang', label: 'Makan Siang', icon: Soup },
    { key: 'makan_malam', label: 'Makan Malam', icon: CookingPot },
    { key: 'camilan', label: 'Camilan', icon: Cookie },
] as const;

const panel = ref<HTMLElement | null>(null);
const searchBox = ref<InstanceType<typeof FoodSearch> | null>(null);
const selected = ref<FoodOption | null>(null);
const initialQuery = ref('');
const localError = ref('');

const form = useForm({
    makanan_id: null as number | null,
    waktu_makan: 'sarapan' as MealKey,
    jumlah_porsi: 1,
    tanggal_konsumsi: props.tanggal,
});

const isEdit = computed(() => props.item !== null);
const portions = computed(() => {
    const base = [0.5, 1, 1.5, 2];
    const own = props.item?.jumlah_porsi;

    return own && !base.includes(own)
        ? [...base, own].sort((a, b) => a - b)
        : base;
});

watch(
    () => props.open,
    async (isOpen) => {
        if (typeof document !== 'undefined')
            document.body.style.overflow = isOpen ? 'hidden' : '';
        if (!isOpen) return;
        form.clearErrors();
        localError.value = '';
        form.waktu_makan = props.mealKey;
        form.tanggal_konsumsi = props.tanggal;
        const item = props.item;
        if (item) {
            const per = item.jumlah_porsi || 1;
            selected.value = {
                id: item.makanan_id,
                nama: item.nama,
                takaran_saji: item.jumlah_porsi === 1 ? item.takaran : '',
                energi: item.total_kalori / per,
                protein: item.protein / per,
                lemak: item.lemak / per,
                karbohidrat: item.karbohidrat / per,
                foto_makanan_url: item.foto_makanan_url,
            };
            form.jumlah_porsi = item.jumlah_porsi;
            initialQuery.value = item.nama;
        } else {
            selected.value = props.preset;
            form.jumlah_porsi = 1;
            initialQuery.value = props.preset?.nama ?? '';
        }
        form.makanan_id = selected.value?.id ?? null;
        await nextTick();
        if (selected.value) panel.value?.focus();
        else searchBox.value?.focus();
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    document.body.style.overflow = '';
});

function pick(food: FoodOption) {
    selected.value = food;
    form.makanan_id = food.id;
    localError.value = '';
}

/** "1 mangkuk" x 1,5 = "1,5 mangkuk"; a takaran without a leading number becomes "2 × 75 g". */
function portionLabel(takaran: string, porsi: number): string {
    const match = takaran.trim().match(/^(\d+(?:[.,]\d+)?)\s*(.*)$/);
    if (match) {
        const amount = Number(match[1].replace(',', '.')) * porsi;

        return `${idNumber.format(amount)} ${match[2]}`.trim();
    }
    if (!takaran) return `${idNumber.format(porsi)} porsi`;

    return porsi === 1 ? takaran : `${idNumber.format(porsi)} × ${takaran}`;
}

const summary = computed(() => {
    const food = selected.value;
    if (!food) return null;
    const p = form.jumlah_porsi;

    return {
        title: `${food.nama}, ${portionLabel(food.takaran_saji, p)}`,
        detail: `${idNumber.format(Math.round(food.energi * p))} kkal • ${idNumber.format(food.protein * p)} g protein • ${idNumber.format(food.lemak * p)} g lemak`,
    };
});

function close() {
    if (form.processing) return;
    emit('close');
}

function submit() {
    if (!form.makanan_id) {
        localError.value = 'Pilih makanan dulu.';
        searchBox.value?.focus();
        return;
    }
    const options = { preserveScroll: true, onSuccess: () => emit('close') };
    if (props.item) {
        form.transform(({ makanan_id, waktu_makan, jumlah_porsi }) => ({
            makanan_id,
            waktu_makan,
            jumlah_porsi,
        })).put(route('user.meals.update', props.item.id), options);
    } else {
        form.transform((data) => data).post(route('user.meals.store'), options);
    }
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        event.preventDefault();
        close();
        return;
    }
    if (event.key !== 'Tab' || !panel.value) return;
    const items = Array.from(
        panel.value.querySelectorAll<HTMLElement>(
            'button:not([disabled]), input:not([disabled])',
        ),
    ).filter((el) => {
        if (!(el instanceof HTMLInputElement) || el.type !== 'radio')
            return true;
        return el.checked;
    });
    if (!items.length) return;
    const first = items[0];
    const last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

const errorText = 'mt-1 text-sm text-[#b42318] dark:text-red-300';
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50" @keydown="onKeydown">
        <div
            class="absolute inset-0 bg-[#1e2321]/50"
            aria-hidden="true"
            @click="close"
        />
        <div
            ref="panel"
            role="dialog"
            aria-modal="true"
            aria-labelledby="meal-sheet-title"
            tabindex="-1"
            class="absolute inset-y-0 right-0 flex w-full max-w-xl flex-col bg-white shadow-2xl outline-none dark:bg-[#1c1c1c]"
        >
            <header class="flex items-start justify-between gap-4 px-6 pt-6">
                <h2
                    id="meal-sheet-title"
                    class="font-display text-3xl font-bold text-[#191919] dark:text-[#f0efea]"
                >
                    {{ isEdit ? 'Ubah catatan' : 'Tambah catatan' }}
                </h2>
                <button
                    type="button"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-[#191919] hover:bg-[#f4f3f0] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919] dark:text-[#f0efea] dark:hover:bg-[#262626] dark:focus-visible:outline-[#f0efea]"
                    aria-label="Tutup"
                    @click="close"
                >
                    <X class="h-5 w-5" aria-hidden="true" />
                </button>
            </header>

            <form
                id="meal-sheet-form"
                class="flex flex-1 flex-col gap-6 overflow-y-auto px-6 py-6"
                @submit.prevent="submit"
            >
                <fieldset>
                    <legend
                        class="mb-2 text-sm font-semibold text-[#191919] dark:text-[#f0efea]"
                    >
                        Waktu makan
                    </legend>
                    <div class="grid grid-cols-2 gap-3">
                        <label
                            v-for="m in MEALS"
                            :key="m.key"
                            class="flex cursor-pointer flex-col items-center gap-1.5 rounded-2xl border-2 px-3 py-4 text-base font-semibold text-[#191919] has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-[#191919] dark:text-[#f0efea] dark:has-[:focus-visible]:outline-[#f0efea]"
                            :class="
                                form.waktu_makan === m.key
                                    ? 'border-[#191919] bg-[#e4f6ea] dark:border-[#f0efea] dark:bg-[#17321f]'
                                    : 'border-transparent bg-[#f4f3f0] hover:bg-[#eceae5] dark:bg-[#262626] dark:hover:bg-[#2e2e2e]'
                            "
                        >
                            <input
                                v-model="form.waktu_makan"
                                type="radio"
                                name="waktu_makan"
                                :value="m.key"
                                class="sr-only"
                            />
                            <component
                                :is="m.icon"
                                class="h-6 w-6"
                                aria-hidden="true"
                            />
                            {{ m.label }}
                        </label>
                    </div>
                    <p v-if="form.errors.waktu_makan" :class="errorText">
                        {{ form.errors.waktu_makan }}
                    </p>
                </fieldset>

                <FoodSearch
                    ref="searchBox"
                    :model-value="selected"
                    :initial-query="initialQuery"
                    :error="localError || form.errors.makanan_id"
                    @update:model-value="pick"
                />

                <fieldset>
                    <legend
                        class="mb-2 text-sm font-semibold text-[#191919] dark:text-[#f0efea]"
                    >
                        Porsi
                    </legend>
                    <div
                        class="inline-flex overflow-hidden rounded-xl border-2 border-[#191919] dark:border-[#f0efea]"
                    >
                        <label
                            v-for="p in portions"
                            :key="p"
                            class="min-w-12 cursor-pointer px-4 py-2.5 text-center text-sm font-semibold has-[:focus-visible]:outline-2 has-[:focus-visible]:-outline-offset-4 has-[:focus-visible]:outline-[#0b8043]"
                            :class="
                                form.jumlah_porsi === p
                                    ? 'bg-[#191919] text-white dark:bg-[#f0efea] dark:text-[#121212]'
                                    : 'bg-white text-[#191919] hover:bg-[#f4f3f0] dark:bg-[#1c1c1c] dark:text-[#f0efea] dark:hover:bg-[#262626]'
                            "
                            ><input
                                v-model="form.jumlah_porsi"
                                type="radio"
                                name="jumlah_porsi"
                                :value="p"
                                class="sr-only"
                            />{{ idNumber.format(p) }}</label
                        >
                    </div>
                    <p v-if="form.errors.jumlah_porsi" :class="errorText">
                        {{ form.errors.jumlah_porsi }}
                    </p>
                </fieldset>

                <div
                    v-if="summary"
                    class="rounded-2xl bg-[#f4f3f0] px-4 py-3 dark:bg-[#262626]"
                    role="status"
                >
                    <p
                        class="text-sm font-semibold text-[#191919] dark:text-[#f0efea]"
                    >
                        {{ summary.title }}
                    </p>
                    <p class="text-sm text-[#5e5e5e] dark:text-[#a3a3a3]">
                        {{ summary.detail }}
                    </p>
                </div>

                <p v-if="form.errors.tanggal_konsumsi" :class="errorText">
                    {{ form.errors.tanggal_konsumsi }}
                </p>
            </form>

            <footer
                class="flex justify-end gap-3 border-t border-[#eceae5] px-6 py-4 dark:border-[#2e2e2e]"
            >
                <button
                    type="button"
                    :disabled="form.processing"
                    class="h-11 rounded-full border-2 border-[#191919] bg-white px-6 text-sm font-semibold text-[#191919] hover:bg-[#f4f3f0] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919] disabled:opacity-50 dark:border-[#f0efea] dark:bg-[#1c1c1c] dark:text-[#f0efea] dark:hover:bg-[#262626] dark:focus-visible:outline-[#f0efea]"
                    @click="close"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    form="meal-sheet-form"
                    :disabled="form.processing"
                    class="inline-flex h-11 items-center gap-2 rounded-full bg-[#0b8043] px-6 text-sm font-semibold text-white hover:bg-[#086637] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919] disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:outline-[#f0efea]"
                >
                    <Loader2
                        v-if="form.processing"
                        class="h-4 w-4 animate-spin"
                        aria-hidden="true"
                    />
                    {{ isEdit ? 'Simpan Perubahan' : 'Simpan Catatan' }}
                </button>
            </footer>
        </div>
    </div>
</template>
