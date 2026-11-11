<script setup lang="ts">
import { ref, computed } from "vue";
import { useForm } from "@inertiajs/vue3";
import { X, ShieldAlert, Loader2, ChevronDown, UserCheck } from "lucide-vue-next";

interface MemberOption {
    id: number;
    name: string;
    is_kepala: boolean;
    role_label: string;
}

const props = defineProps<{
    show: boolean;
    members: MemberOption[];
}>();

const emit = defineEmits<{
    (e: "close"): void;
}>();

const form = useForm({
    new_head_id: "",
});

// Hanya anggota yang bukan kepala keluarga saat ini
const availableMembers = computed(() => {
    return props.members.filter((m) => !m.is_kepala);
});

const selectedMemberName = computed(() => {
    const found = availableMembers.value.find((m) => String(m.id) === String(form.new_head_id));
    return found ? found.name : "";
});

const submitTransfer = () => {
    if (!form.new_head_id) return;

    form.post(route("user.family.transfer_head"), {
        onSuccess: () => {
            emit("close");
            form.reset();
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
            class="bg-white dark:bg-[#1c1c1c] rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-gray-100 dark:border-[#2e2e2e] relative"
        >
            <button
                type="button"
                @click="emit('close')"
                class="absolute right-6 top-6 p-1.5 rounded-full text-gray-400 hover:text-gray-600 dark:hover:text-[#f0efea] hover:bg-gray-100 dark:hover:bg-[#262626] transition cursor-pointer"
            >
                <X class="w-5 h-5" />
            </button>

            <div class="mb-5">
                <div class="w-10 h-10 rounded-2xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 flex items-center justify-center mb-3">
                    <ShieldAlert class="w-5 h-5" />
                </div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-[#f0efea]">
                    Alihkan Kepala Keluarga?
                </h3>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-[#a3a3a3] mt-1">
                    Pilih anggota keluarga yang akan mengelola grup. Setelah dialihkan, kamu akan tetap berada di dalam grup sebagai Anggota.
                </p>
            </div>

            <form @submit.prevent="submitTransfer" class="space-y-5">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-[#a3a3a3] mb-2">
                        Pilih Kepala Keluarga Baru
                    </label>

                    <div class="relative">
                        <select
                            v-model="form.new_head_id"
                            class="w-full px-4 py-3 rounded-2xl border border-gray-200 dark:border-[#3d3d3d] bg-white dark:bg-[#262626] text-gray-900 dark:text-[#f0efea] text-sm appearance-none focus:outline-none focus:border-[#0b8043] dark:focus:border-[#3fcb7a] focus:ring-2 focus:ring-[#0b8043]/15 transition pr-10 cursor-pointer"
                        >
                            <option value="" disabled>-- Pilih dari anggota keluarga --</option>
                            <option
                                v-for="member in availableMembers"
                                :key="member.id"
                                :value="member.id"
                            >
                                {{ member.name }}
                            </option>
                        </select>
                        <ChevronDown class="w-4 h-4 text-gray-400 absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none" />
                    </div>

                    <p v-if="form.errors.new_head_id" class="text-xs text-red-500 mt-1.5 font-medium">
                        {{ form.errors.new_head_id }}
                    </p>
                </div>

                <!-- Confirmation Notice Preview -->
                <div
                    v-if="selectedMemberName"
                    class="p-4 rounded-2xl bg-amber-50/70 dark:bg-amber-950/20 border border-amber-200/60 dark:border-amber-900/40 text-xs text-amber-800 dark:text-amber-300 leading-relaxed flex items-start gap-3"
                >
                    <UserCheck class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                    <div>
                        <span class="font-bold">{{ selectedMemberName }}</span> akan menjadi Kepala Keluarga dan memiliki hak kelola grup. Statusmu akan beralih menjadi Anggota.
                    </div>
                </div>

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
                        :disabled="!form.new_head_id || form.processing"
                        class="px-6 py-2.5 rounded-xl bg-amber-600 text-white text-sm font-bold hover:bg-amber-700 disabled:opacity-40 shadow-sm transition cursor-pointer flex items-center gap-2"
                    >
                        <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
                        <span>Alihkan Peran</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
