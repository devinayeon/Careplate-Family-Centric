<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import { X, Trash2, Loader2, AlertTriangle } from "lucide-vue-next";

const props = defineProps<{
    show: boolean;
    familyName: string;
}>();

const emit = defineEmits<{
    (e: "close"): void;
}>();

const form = useForm({});

const submitDelete = () => {
    form.delete(route("user.family.delete_group"), {
        onSuccess: () => {
            emit("close");
        },
    });
};
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs animate-in fade-in duration-150"
    >
        <div
            class="bg-white dark:bg-[#1c1c1c] rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-gray-100 dark:border-[#2e2e2e] relative"
        >
            <button
                type="button"
                @click="emit('close')"
                class="absolute right-6 top-6 p-1.5 rounded-full text-gray-400 hover:text-gray-600 dark:hover:text-[#f0efea] hover:bg-gray-100 dark:hover:bg-[#262626] transition cursor-pointer"
            >
                <X class="w-5 h-5" />
            </button>

            <div class="mb-5">
                <div class="w-10 h-10 rounded-2xl bg-red-100 dark:bg-red-950/60 text-red-600 dark:text-red-400 flex items-center justify-center mb-3">
                    <AlertTriangle class="w-5 h-5" />
                </div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-[#f0efea]">
                    Hapus grup keluarga?
                </h3>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-[#a3a3a3] mt-2 leading-relaxed">
                    Semua anggota akan otomatis keluar dari grup <span class="font-bold text-gray-900 dark:text-[#f0efea]">{{ familyName }}</span> dan kode grup tidak lagi aktif. Seluruh catatan makan masing-masing anggota tetap aman tersimpan.
                </p>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3">
                <button
                    type="button"
                    @click="emit('close')"
                    class="px-5 py-2.5 rounded-xl text-sm font-medium text-gray-600 dark:text-[#a3a3a3] hover:bg-gray-100 dark:hover:bg-[#262626] transition cursor-pointer"
                >
                    Batal
                </button>
                <button
                    type="button"
                    @click="submitDelete"
                    :disabled="form.processing"
                    class="px-6 py-2.5 rounded-xl bg-red-600 text-white text-sm font-bold hover:bg-red-700 disabled:opacity-40 shadow-sm transition cursor-pointer flex items-center gap-2"
                >
                    <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
                    <span>Ya, Hapus Grup</span>
                </button>
            </div>
        </div>
    </div>
</template>
