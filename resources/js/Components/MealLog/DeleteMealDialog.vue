<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Loader2, Trash2 } from 'lucide-vue-next';
import { idNumber } from '@/lib/foodUi';
import type { MealItem } from '@/Components/MealLog/MealGroupCard.vue';

const props = defineProps<{
    target: { item: MealItem; groupLabel: string } | null;
}>();

const emit = defineEmits<{ (e: 'close'): void }>();

const panel = ref<HTMLElement | null>(null);
const cancelButton = ref<HTMLButtonElement | null>(null);
const processing = ref(false);
const errorText = ref('');

// Focus starts on the safe choice (Batal), never on the destructive one.
watch(
    () => props.target,
    async (value) => {
        errorText.value = '';
        if (!value) return;
        await nextTick();
        cancelButton.value?.focus();
    },
);

function close() {
    if (processing.value) return;
    emit('close');
}

function confirm() {
    if (!props.target || processing.value) return;
    processing.value = true;
    errorText.value = '';
    router.delete(route('user.meals.destroy', props.target.item.id), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
        onError: () => {
            errorText.value = 'Catatan belum terhapus. Coba lagi.';
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        event.preventDefault();
        close();
        return;
    }
    if (event.key !== 'Tab' || !panel.value) return;
    const items = Array.from(
        panel.value.querySelectorAll<HTMLElement>('button:not([disabled])'),
    );
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
</script>

<template>
    <div
        v-if="target"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        @keydown="onKeydown"
    >
        <div
            class="absolute inset-0 bg-[#1e2321]/50"
            aria-hidden="true"
            @click="close"
        />
        <div
            ref="panel"
            role="dialog"
            aria-modal="true"
            aria-labelledby="delete-meal-title"
            aria-describedby="delete-meal-desc"
            class="relative w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl sm:p-8 dark:bg-[#1c1c1c]"
        >
            <span
                class="flex h-12 w-12 items-center justify-center rounded-full bg-[#fde8e6] dark:bg-[#3a1614]"
            >
                <Trash2
                    class="h-5 w-5 text-[#b42318] dark:text-red-300"
                    aria-hidden="true"
                />
            </span>
            <h2
                id="delete-meal-title"
                class="mt-4 font-display text-2xl font-bold text-[#191919] dark:text-[#f0efea]"
            >
                Hapus {{ target.item.nama.toLowerCase() }} dari
                {{ target.groupLabel }}?
            </h2>
            <p
                id="delete-meal-desc"
                class="mt-3 text-base text-[#5e5e5e] dark:text-[#a3a3a3]"
            >
                Total hari ini turun
                {{ idNumber.format(target.item.total_kalori) }} kkal.
            </p>
            <p
                v-if="errorText"
                class="mt-3 rounded-xl bg-[#ffece5] px-4 py-3 text-sm text-[#a52f08] dark:bg-[#3a1e14] dark:text-red-300"
                role="alert"
            >
                {{ errorText }}
            </p>
            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <button
                    ref="cancelButton"
                    type="button"
                    :disabled="processing"
                    class="h-11 rounded-full border-2 border-[#191919] bg-white px-6 text-sm font-semibold text-[#191919] hover:bg-[#f4f3f0] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919] disabled:opacity-50 dark:border-[#f0efea] dark:bg-[#1c1c1c] dark:text-[#f0efea] dark:hover:bg-[#262626] dark:focus-visible:outline-[#f0efea]"
                    @click="close"
                >
                    Batal
                </button>
                <button
                    type="button"
                    :disabled="processing"
                    class="inline-flex h-11 items-center gap-2 rounded-full bg-[#b42318] px-6 text-sm font-semibold text-white hover:bg-[#912018] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919] disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:outline-[#f0efea]"
                    @click="confirm"
                >
                    <Loader2
                        v-if="processing"
                        class="h-4 w-4 animate-spin"
                        aria-hidden="true"
                    />
                    {{ processing ? 'Menghapus…' : 'Hapus Catatan' }}
                </button>
            </div>
        </div>
    </div>
</template>
