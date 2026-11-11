<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import { X, UserMinus, Loader2 } from "lucide-vue-next";

interface TargetMember {
    id: number;
    name: string;
}

const props = defineProps<{
    show: boolean;
    member: TargetMember | null;
}>();

const emit = defineEmits<{
    (e: "close"): void;
}>();

const form = useForm({});

const submitRemove = () => {
    if (!props.member) return;

    form.delete(route("user.family.remove_member", { member: props.member.id }), {
        onSuccess: () => {
            emit("close");
        },
    });
};
</script>

<template>
    <div
        v-if="show && member"
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
                <div class="w-10 h-10 rounded-2xl bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400 flex items-center justify-center mb-3">
                    <UserMinus class="w-5 h-5" />
                </div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-[#f0efea]">
                    Keluarkan anggota?
                </h3>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-[#a3a3a3] mt-2 leading-relaxed">
                    Apakah kamu yakin ingin mengeluarkan <span class="font-bold text-gray-900 dark:text-[#f0efea]">{{ member.name }}</span> dari grup keluarga? Riwayat catatan makannya akan tetap tersimpan di akun pribadinya.
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
                    @click="submitRemove"
                    :disabled="form.processing"
                    class="px-6 py-2.5 rounded-xl bg-red-600 text-white text-sm font-bold hover:bg-red-700 disabled:opacity-40 shadow-sm transition cursor-pointer flex items-center gap-2"
                >
                    <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
                    <span>Keluarkan</span>
                </button>
            </div>
        </div>
    </div>
</template>
