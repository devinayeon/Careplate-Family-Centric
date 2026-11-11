<script setup lang="ts">
import { ref } from 'vue';
import { X, Calendar, Clock, Video, Loader2 } from 'lucide-vue-next';

const props = defineProps<{
    show: boolean;
    loading?: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'submit', data: { tanggal: string; mulai: string; durasi: string; link: string }): void;
}>();

const tanggal = ref('Jumat, 14 Nov 2026');
const mulai = ref('19.00');
const durasi = ref('30 mnt');
const link = ref('https://meet.google.com/abc-defg-hij');

const durasiOptions = ['15 mnt', '30 mnt', '45 mnt', '60 mnt'];

const handleSubmit = () => {
    if (!link.value.trim() || !tanggal.value.trim() || !mulai.value.trim()) return;
    emit('submit', {
        tanggal: tanggal.value.trim(),
        mulai: mulai.value.trim(),
        durasi: durasi.value,
        link: link.value.trim(),
    });
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

        <!-- Modal Dialog Content -->
        <div
            class="relative w-full max-w-lg rounded-3xl border border-gray-100 bg-white p-6 shadow-2xl transition-all dark:border-[#262626] dark:bg-[#1a1a1a] sm:p-7"
        >
            <!-- Header -->
            <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-[#262626]">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-[#f0efea]">
                        Jadwalkan meeting
                    </h3>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Atur waktu konsultasi tatap muka daring dengan klien.
                    </p>
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
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                            Tanggal
                        </label>
                        <div class="relative mt-1.5">
                            <Calendar class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            <input
                                v-model="tanggal"
                                type="text"
                                placeholder="Jumat, 14 Nov 2026"
                                required
                                class="w-full rounded-2xl border border-gray-200 bg-gray-50/50 py-2.5 pl-9 pr-3 text-xs font-medium text-gray-900 transition-colors focus:border-emerald-600 focus:bg-white focus:outline-none dark:border-[#262626] dark:bg-[#121212] dark:text-[#f0efea]"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                            Waktu Mulai
                        </label>
                        <div class="relative mt-1.5">
                            <Clock class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            <input
                                v-model="mulai"
                                type="text"
                                placeholder="19.00"
                                required
                                class="w-full rounded-2xl border border-gray-200 bg-gray-50/50 py-2.5 pl-9 pr-3 text-xs font-medium text-gray-900 transition-colors focus:border-emerald-600 focus:bg-white focus:outline-none dark:border-[#262626] dark:bg-[#121212] dark:text-[#f0efea]"
                            />
                        </div>
                    </div>
                </div>

                <!-- Durasi Segmented Control -->
                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                        Durasi
                    </label>
                    <div class="mt-1.5 grid grid-cols-4 gap-2 rounded-2xl border border-gray-100 bg-gray-100/70 p-1 dark:border-[#262626] dark:bg-[#121212]">
                        <button
                            v-for="opt in durasiOptions"
                            :key="opt"
                            type="button"
                            @click="durasi = opt"
                            class="rounded-xl py-2 text-xs font-bold transition-all"
                            :class="
                                durasi === opt
                                    ? 'bg-white text-gray-900 shadow-2xs dark:bg-[#262626] dark:text-[#f0efea]'
                                    : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'
                            "
                        >
                            {{ opt }}
                        </button>
                    </div>
                </div>

                <!-- Tautan Meeting -->
                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                        Tautan meeting
                    </label>
                    <div class="relative mt-1.5">
                        <Video class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input
                            v-model="link"
                            type="text"
                            placeholder="meet.google.com/abc-defg-hij"
                            required
                            class="w-full rounded-2xl border border-gray-200 bg-gray-50/50 py-2.5 pl-9 pr-3 text-xs font-medium text-gray-900 transition-colors focus:border-emerald-600 focus:bg-white focus:outline-none dark:border-[#262626] dark:bg-[#121212] dark:text-[#f0efea]"
                        />
                    </div>
                    <p class="mt-1.5 text-[11px] text-gray-400 dark:text-gray-500">
                        Tautan Google Meet atau Zoom. Klien melihatnya sebagai kartu di chat.
                    </p>
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
                        :disabled="loading"
                        class="inline-flex items-center gap-2 rounded-full bg-[#0f6b36] px-6 py-2.5 text-xs font-bold text-white shadow-xs transition-colors hover:bg-[#0c592c] disabled:opacity-50"
                    >
                        <Loader2 v-if="loading" class="h-3.5 w-3.5 animate-spin" />
                        <span>Kirim tautan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
