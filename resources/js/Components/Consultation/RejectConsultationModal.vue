<script setup lang="ts">
import { ref } from 'vue';
import { X, AlertCircle, Loader2 } from 'lucide-vue-next';

defineProps<{
    show: boolean;
    loading?: boolean;
    clientName?: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'submit', alasan: string): void;
}>();

const alasan = ref('');

const presetReasons = [
    'Jadwal konsultasi penuh untuk minggu ini.',
    'Topik keluhan lebih tepat ditangani oleh dokter spesialis gizi klinis rumah sakit.',
    'Mohon ajukan kembali dengan melengkapi catatan asupan makanan 3 hari.',
];

const setPreset = (preset: string) => {
    alasan.value = preset;
};

const handleSubmit = () => {
    if (!alasan.value.trim()) return;
    emit('submit', alasan.value.trim());
};
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
            class="relative w-full max-w-lg rounded-3xl border border-gray-100 bg-white p-6 shadow-2xl transition-all dark:border-[#262626] dark:bg-[#1a1a1a] sm:p-7"
        >
            <!-- Header -->
            <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-[#262626]">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-red-100 text-red-600 dark:bg-red-950/50 dark:text-red-400">
                        <AlertCircle class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-[#f0efea]">
                            Tolak Permohonan Konsultasi
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Klien: {{ clientName || 'Pengguna' }}
                        </p>
                    </div>
                </div>
                <button
                    @click="emit('close')"
                    class="rounded-full p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-[#262626] dark:hover:text-gray-200"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Form -->
            <form @submit.prevent="handleSubmit" class="mt-5 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                        Alasan Penolakan <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        v-model="alasan"
                        rows="3"
                        placeholder="Tuliskan alasan penolakan secara jelas dan santun..."
                        required
                        class="mt-1.5 w-full rounded-2xl border border-gray-200 bg-gray-50/50 p-3 text-xs leading-relaxed text-gray-900 transition-colors focus:border-red-500 focus:bg-white focus:outline-none dark:border-[#262626] dark:bg-[#121212] dark:text-[#f0efea]"
                    ></textarea>
                </div>

                <!-- Template Reasons -->
                <div>
                    <span class="text-[11px] font-medium text-gray-400 dark:text-gray-500">
                        Pilihan alasan cepat:
                    </span>
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        <button
                            v-for="r in presetReasons"
                            :key="r"
                            type="button"
                            @click="setPreset(r)"
                            class="rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1 text-[11px] text-gray-600 transition-colors hover:border-red-300 hover:bg-red-50 hover:text-red-700 dark:border-[#333] dark:bg-[#222] dark:text-gray-300 dark:hover:bg-red-950/30"
                        >
                            {{ r }}
                        </button>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-6 flex items-center justify-end gap-3 pt-3">
                    <button
                        type="button"
                        @click="emit('close')"
                        :disabled="loading"
                        class="rounded-full border border-gray-200 px-5 py-2.5 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-50 dark:border-[#333] dark:text-gray-300 dark:hover:bg-[#262626]"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="loading || !alasan.trim()"
                        class="inline-flex items-center gap-2 rounded-full bg-red-600 px-6 py-2.5 text-xs font-bold text-white shadow-xs transition-colors hover:bg-red-700 disabled:opacity-50"
                    >
                        <Loader2 v-if="loading" class="h-3.5 w-3.5 animate-spin" />
                        <span>Tolak Permohonan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
