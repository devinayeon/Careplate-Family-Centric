<script setup lang="ts">
import { X, CheckCircle2, Loader2 } from 'lucide-vue-next';

defineProps<{
    show: boolean;
    loading?: boolean;
    clientName?: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'confirm'): void;
}>();
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
    >
        <!-- Backdrop Scrim -->
        <div
            class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity"
            @click="emit('close')"
        ></div>

        <!-- Modal Content -->
        <div
            class="relative w-full max-w-md rounded-3xl border border-gray-100 bg-white p-6 shadow-2xl transition-all dark:border-[#262626] dark:bg-[#1a1a1a] sm:p-7"
        >
            <div class="flex items-center justify-between pb-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-100 text-[#0f6b36] dark:bg-emerald-950/50 dark:text-emerald-400">
                    <CheckCircle2 class="h-6 w-6" />
                </div>
                <button
                    @click="emit('close')"
                    class="rounded-full p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-[#262626] dark:hover:text-gray-200"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <div class="mt-3">
                <h3 class="text-base font-bold text-gray-900 dark:text-[#f0efea]">
                    Selesaikan Konsultasi?
                </h3>
                <p class="mt-2 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                    Apakah Anda yakin ingin menyelesaikan sesi konsultasi dengan <strong class="text-gray-800 dark:text-gray-200">{{ clientName || 'klien' }}</strong>? Sesi akan ditandai selesai dan ruang obrolan akan ditutup.
                </p>
            </div>

            <!-- Actions -->
            <div class="mt-6 flex items-center justify-end gap-3">
                <button
                    type="button"
                    @click="emit('close')"
                    :disabled="loading"
                    class="rounded-full border border-gray-200 px-5 py-2.5 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-50 dark:border-[#333] dark:text-gray-300 dark:hover:bg-[#262626]"
                >
                    Batal
                </button>
                <button
                    type="button"
                    @click="emit('confirm')"
                    :disabled="loading"
                    class="inline-flex items-center gap-2 rounded-full bg-[#0f6b36] px-6 py-2.5 text-xs font-bold text-white shadow-xs transition-colors hover:bg-[#0c592c] disabled:opacity-50"
                >
                    <Loader2 v-if="loading" class="h-3.5 w-3.5 animate-spin" />
                    <span>Ya, Selesaikan</span>
                </button>
            </div>
        </div>
    </div>
</template>
