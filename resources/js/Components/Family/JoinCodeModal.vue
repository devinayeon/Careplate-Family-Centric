<script setup lang="ts">
import { ref, watch } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import { X, CheckCircle2, Users, Loader2, AlertCircle } from "lucide-vue-next";

const props = defineProps<{
    show: boolean;
}>();

const emit = defineEmits<{
    (e: "close"): void;
}>();

const form = useForm({
    kode_keluarga: "",
});

const previewData = ref<{
    id: number;
    nama_keluarga: string;
    kepala_keluarga: string;
    jumlah_anggota: number;
} | null>(null);

const isLoadingPreview = ref(false);
const previewError = ref("");
let previewTimeout: ReturnType<typeof setTimeout> | null = null;

const onCodeInput = () => {
    // Otomatis ubah ke huruf kapital dan batasi 6 karakter
    form.kode_keluarga = form.kode_keluarga.toUpperCase().replace(/[^A-Z0-9]/g, "").slice(0, 6);
    previewData.value = null;
    previewError.value = "";

    if (form.kode_keluarga.length === 6) {
        checkPreview(form.kode_keluarga);
    }
};

const checkPreview = async (code: string) => {
    try {
        isLoadingPreview.value = true;
        previewError.value = "";
        const response = await fetch(route("user.family.preview_code", { kode: code }), {
            headers: {
                Accept: "application/json",
            },
        });

        if (!response.ok) {
            throw new Error("Kode keluarga tidak ditemukan.");
        }

        const data = await response.json();
        if (data.found) {
            previewData.value = data;
        } else {
            previewError.value = "Kode tidak ditemukan.";
        }
    } catch {
        previewError.value = "Kode keluarga tidak ditemukan atau tidak valid.";
    } finally {
        isLoadingPreview.value = false;
    }
};

const submitJoin = () => {
    if (form.kode_keluarga.length !== 6) return;

    form.post(route("user.family.join_code"), {
        onSuccess: () => {
            emit("close");
            form.reset();
            previewData.value = null;
        },
    });
};

watch(
    () => props.show,
    (val) => {
        if (!val) {
            form.reset();
            previewData.value = null;
            previewError.value = "";
        }
    }
);
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs animate-in fade-in duration-150"
    >
        <div
            class="bg-white dark:bg-[#1c1c1c] rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-gray-100 dark:border-[#2e2e2e] relative"
        >
            <!-- Close button -->
            <button
                type="button"
                @click="emit('close')"
                class="absolute right-6 top-6 p-1.5 rounded-full text-gray-400 hover:text-gray-600 dark:hover:text-[#f0efea] hover:bg-gray-100 dark:hover:bg-[#262626] transition cursor-pointer"
            >
                <X class="w-5 h-5" />
            </button>

            <div class="mb-6">
                <h3 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-900 dark:text-[#f0efea]">
                    Gabung dengan kode
                </h3>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-[#a3a3a3] mt-1">
                    Minta 6 karakter kode grup ke Kepala Keluarga kamu.
                </p>
            </div>

            <form @submit.prevent="submitJoin" class="space-y-6">
                <!-- 6 Character Input Box -->
                <div>
                    <div class="relative flex items-center justify-center">
                        <input
                            v-model="form.kode_keluarga"
                            @input="onCodeInput"
                            type="text"
                            maxlength="6"
                            placeholder="KS7R2M"
                            autofocus
                            class="w-full text-center tracking-[0.5em] text-2xl sm:text-3xl font-mono font-extrabold uppercase py-3.5 px-4 rounded-2xl border-2 border-gray-200 dark:border-[#3d3d3d] bg-[#f8f9fa] dark:bg-[#262626] text-gray-900 dark:text-[#f0efea] placeholder-gray-300 dark:placeholder-[#555] focus:outline-none focus:border-[#0b8043] dark:focus:border-[#3fcb7a] focus:ring-4 focus:ring-[#0b8043]/15 transition"
                        />
                    </div>
                    <p v-if="form.errors.kode_keluarga" class="text-xs text-red-500 mt-2 text-center font-medium">
                        {{ form.errors.kode_keluarga }}
                    </p>
                </div>

                <!-- Preview Area -->
                <div v-if="isLoadingPreview" class="flex items-center justify-center gap-2 py-4 text-xs text-gray-500 dark:text-[#a3a3a3]">
                    <Loader2 class="w-4 h-4 animate-spin text-[#0b8043] dark:text-[#3fcb7a]" />
                    <span>Mencari informasi grup keluarga...</span>
                </div>

                <div
                    v-else-if="previewData"
                    class="p-4 rounded-2xl bg-[#e7f5ec] dark:bg-[#17321f] border border-emerald-200 dark:border-[#204a2c] flex items-center gap-3.5 animate-in fade-in duration-200"
                >
                    <div class="w-10 h-10 rounded-full bg-[#0b8043] dark:bg-[#3fcb7a] text-white dark:text-[#121212] flex items-center justify-center shrink-0">
                        <CheckCircle2 class="w-5 h-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h4 class="text-sm font-bold text-gray-900 dark:text-[#f0efea] truncate">
                            {{ previewData.nama_keluarga }}
                        </h4>
                        <p class="text-xs text-gray-600 dark:text-[#a3a3a3] mt-0.5">
                            Kepala Keluarga: <span class="font-medium text-gray-800 dark:text-gray-200">{{ previewData.kepala_keluarga }}</span> • {{ previewData.jumlah_anggota }} anggota
                        </p>
                    </div>
                </div>

                <div
                    v-else-if="previewError"
                    class="p-3.5 rounded-2xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900/50 flex items-center gap-2.5 text-xs text-red-600 dark:text-red-400"
                >
                    <AlertCircle class="w-4 h-4 shrink-0" />
                    <span>{{ previewError }}</span>
                </div>

                <!-- Action buttons -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="px-5 py-2.5 rounded-xl text-sm font-medium text-gray-600 dark:text-[#a3a3a3] hover:bg-gray-100 dark:hover:bg-[#262626] transition cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="form.kode_keluarga.length !== 6 || form.processing"
                        class="px-6 py-2.5 rounded-xl bg-[#0b8043] dark:bg-[#3fcb7a] text-white dark:text-[#121212] text-sm font-bold hover:bg-[#096836] dark:hover:bg-[#34aa66] disabled:opacity-40 shadow-sm transition cursor-pointer flex items-center gap-2"
                    >
                        <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
                        <span>Gabung ke Grup</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
