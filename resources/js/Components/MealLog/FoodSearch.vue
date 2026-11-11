<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Check, Loader2, Search, Utensils } from 'lucide-vue-next';
import { idNumber } from '@/lib/foodUi';

export interface FoodOption {
    id: number;
    nama: string;
    takaran_saji: string;
    energi: number;
    protein: number;
    lemak: number;
    karbohidrat: number;
    foto_makanan_url: string | null;
}

const props = defineProps<{
    modelValue: FoodOption | null;
    initialQuery: string;
    error?: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', food: FoodOption): void;
}>();

const query = ref(props.initialQuery);
const results = ref<FoodOption[]>([]);
const loading = ref(false);
const failed = ref(false);
const searched = ref(false);
const input = ref<HTMLInputElement | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;
let controller: AbortController | null = null;

async function search(q: string) {
    controller?.abort();
    const term = q.trim();
    if (!term) {
        results.value = [];
        searched.value = false;
        loading.value = false;
        return;
    }
    controller = new AbortController();
    loading.value = true;
    failed.value = false;
    try {
        const response = await fetch(route('user.meals.foods', { q: term }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: controller.signal,
        });
        if (!response.ok) throw new Error(String(response.status));
        const body = (await response.json()) as { data: FoodOption[] };
        results.value = body.data.slice(0, 10);
        searched.value = true;
        // Edit mode starts from the logged row; swap in the full food (it carries takaran_saji).
        const same = results.value.find((f) => f.id === props.modelValue?.id);
        if (same) emit('update:modelValue', same);
    } catch (e) {
        if ((e as Error).name === 'AbortError') return;
        failed.value = true;
        results.value = [];
    }
    loading.value = false;
}

watch(query, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => void search(value), 250);
});
onMounted(() => void search(query.value));
onBeforeUnmount(() => {
    clearTimeout(timer);
    controller?.abort();
});

// The chosen food stays visible even when a new search no longer lists it.
const options = computed(() => {
    const chosen = props.modelValue;
    if (!chosen || results.value.some((f) => f.id === chosen.id))
        return results.value;

    return [chosen, ...results.value];
});

const statusText = computed(() => {
    if (loading.value) return 'Mencari…';
    if (failed.value) return 'Pencarian gagal. Coba lagi.';
    if (!searched.value) return '';

    return results.value.length
        ? `${results.value.length} hasil`
        : 'Tidak ditemukan';
});

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <div>
        <label
            for="meal-food-search"
            class="mb-2 block text-sm font-semibold text-[#191919] dark:text-[#f0efea]"
            >Pilih makanan</label
        >
        <div class="relative">
            <Search
                class="pointer-events-none absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-[#5e5e5e] dark:text-[#a3a3a3]"
                aria-hidden="true"
            />
            <input
                id="meal-food-search"
                ref="input"
                v-model="query"
                type="search"
                autocomplete="off"
                placeholder="Cari nama makanan, misalnya soto"
                class="h-12 w-full rounded-2xl border-2 border-[#191919] bg-white pr-24 pl-11 text-base text-[#191919] placeholder:text-[#5e5e5e] focus:ring-2 focus:ring-[#0b8043]/30 focus:outline-none dark:border-[#f0efea] dark:bg-[#1c1c1c] dark:text-[#f0efea] dark:placeholder:text-[#a3a3a3]"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="
                    error
                        ? 'meal-food-status meal-food-error'
                        : 'meal-food-status'
                "
            />
            <span
                id="meal-food-status"
                class="absolute top-1/2 right-4 flex -translate-y-1/2 items-center gap-1 text-xs text-[#5e5e5e] dark:text-[#a3a3a3]"
                role="status"
            >
                <Loader2
                    v-if="loading"
                    class="h-3.5 w-3.5 animate-spin"
                    aria-hidden="true"
                />{{ statusText }}
            </span>
        </div>
        <p
            v-if="error"
            id="meal-food-error"
            class="mt-1 text-sm text-[#b42318] dark:text-red-300"
        >
            {{ error }}
        </p>

        <fieldset v-if="options.length" class="mt-4">
            <legend class="sr-only">Hasil pencarian makanan</legend>
            <div class="flex flex-col gap-2">
                <label
                    v-for="food in options"
                    :key="food.id"
                    class="flex cursor-pointer items-center gap-3 rounded-2xl border-2 px-3 py-3 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-[#191919] dark:has-[:focus-visible]:outline-[#f0efea]"
                    :class="
                        modelValue?.id === food.id
                            ? 'border-[#191919] bg-[#e4f6ea] dark:border-[#f0efea] dark:bg-[#17321f]'
                            : 'border-[#eceae5] bg-white hover:bg-[#f4f3f0] dark:border-[#2e2e2e] dark:bg-[#1c1c1c] dark:hover:bg-[#262626]'
                    "
                >
                    <input
                        type="radio"
                        name="meal-food"
                        class="sr-only"
                        :value="food.id"
                        :checked="modelValue?.id === food.id"
                        @change="emit('update:modelValue', food)"
                    />
                    <span
                        class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-[#f4f3f0] dark:bg-[#262626]"
                    >
                        <img
                            v-if="food.foto_makanan_url"
                            :src="food.foto_makanan_url"
                            alt=""
                            class="h-full w-full object-cover"
                            loading="lazy"
                        />
                        <Utensils
                            v-else
                            class="h-5 w-5 text-[#0b8043] dark:text-[#3fcb7a]"
                            aria-hidden="true"
                        />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span
                            class="block truncate text-base font-semibold text-[#191919] dark:text-[#f0efea]"
                            >{{ food.nama }}</span
                        >
                        <span
                            class="block text-sm text-[#5e5e5e] dark:text-[#a3a3a3]"
                            ><template v-if="food.takaran_saji"
                                >{{ food.takaran_saji }} • </template
                            >{{ idNumber.format(food.energi) }} kkal •
                            {{ idNumber.format(food.protein) }} g protein</span
                        >
                    </span>
                    <span
                        v-if="modelValue?.id === food.id"
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#191919] dark:bg-[#f0efea]"
                    >
                        <Check
                            class="h-3.5 w-3.5 text-white dark:text-[#121212]"
                            aria-hidden="true"
                        />
                    </span>
                </label>
            </div>
        </fieldset>
        <p
            v-else-if="!query.trim()"
            class="mt-3 text-sm text-[#5e5e5e] dark:text-[#a3a3a3]"
        >
            Ketik nama makanan untuk mencari. Hanya makanan terverifikasi yang
            muncul.
        </p>
    </div>
</template>
