<script setup lang="ts">
import { ref } from "vue";
import { Head, Link, useForm, router } from "@inertiajs/vue3";
import UserLayout from "@/Layouts/UserLayout.vue";
import TransferHeadModal from "@/Components/Family/TransferHeadModal.vue";
import ConfirmRemoveMemberModal from "@/Components/Family/ConfirmRemoveMemberModal.vue";
import ConfirmDeleteGroupModal from "@/Components/Family/ConfirmDeleteGroupModal.vue";
import {
    ArrowLeft,
    Copy,
    Check,
    RefreshCw,
    UserMinus,
    Shield,
    Trash2,
    Loader2,
    Save,
} from "lucide-vue-next";

interface MemberItem {
    id: number;
    user_id: number;
    name: string;
    email: string;
    initials: string;
    is_kepala: boolean;
    role_label: string;
    usia: number | null;
}

const props = defineProps<{
    keluarga: {
        id: number;
        nama_keluarga: string;
        kode_keluarga: string;
        link_undangan: string;
    };
    anggota: MemberItem[];
}>();

const nameForm = useForm({
    nama_keluarga: props.keluarga.nama_keluarga,
});

const copiedCode = ref(false);
const isRegeneratingCode = ref(false);

// Modals state
const isTransferModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const memberToRemove = ref<MemberItem | null>(null);

const copyCode = async () => {
    try {
        await navigator.clipboard.writeText(props.keluarga.kode_keluarga);
        copiedCode.value = true;
        setTimeout(() => (copiedCode.value = false), 2000);
    } catch {
        // Fallback
    }
};

const handleUpdateName = () => {
    nameForm.put(route("user.family.update_name"), {
        preserveScroll: true,
    });
};

const handleRegenerateCode = () => {
    if (confirm("Apakah kamu yakin ingin membuat kode grup baru? Kode lama tidak akan bisa digunakan lagi.")) {
        isRegeneratingCode.value = true;
        router.post(
            route("user.family.regenerate_code"),
            {},
            {
                onFinish: () => (isRegeneratingCode.value = false),
            }
        );
    }
};

const openRemoveModal = (member: MemberItem) => {
    memberToRemove.value = member;
};
</script>

<template>
    <UserLayout title="Kelola Grup Keluarga">
        <Head :title="`Kelola Grup ${keluarga.nama_keluarga} - CarePlate`" />

        <div class="max-w-5xl mx-auto space-y-8 pb-16">
            <!-- Back Navigation (Figma 35:9787) -->
            <div>
                <Link
                    :href="route('user.family')"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-gray-600 dark:text-[#a3a3a3] hover:text-[#0b8043] dark:hover:text-[#3fcb7a] transition-colors group cursor-pointer"
                >
                    <ArrowLeft class="w-4 h-4 group-hover:-translate-x-1 transition-transform" />
                    <span>{{ keluarga.nama_keluarga }}</span>
                </Link>
            </div>

            <!-- Page Title -->
            <div>
                <h1 class="font-display text-3xl sm:text-4xl font-extrabold text-gray-900 dark:text-[#f0efea]">
                    Kelola grup
                </h1>
                <p class="text-sm text-gray-500 dark:text-[#a3a3a3] mt-1">
                    Atur nama grup, kode undangan, serta peran anggota keluarga kamu.
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <!-- Left Column (Figma 35:9790) -->
                <div class="lg:col-span-7 space-y-6">
                    <!-- Card 1: Ubah Nama Grup (Figma 35:9791) -->
                    <div class="bg-white dark:bg-[#1c1c1c] border border-gray-100 dark:border-[#2e2e2e] rounded-3xl p-6 sm:p-8 shadow-xs space-y-5">
                        <div>
                            <h3 class="text-base font-bold text-gray-900 dark:text-[#f0efea]">
                                Nama grup
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-[#a3a3a3] mt-0.5">
                                Ganti nama keluarga yang tampil di dashboard meja bersama.
                            </p>
                        </div>

                        <form @submit.prevent="handleUpdateName" class="space-y-4">
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block text-xs font-semibold text-gray-600 dark:text-[#a3a3a3]">
                                        Nama keluarga
                                    </label>
                                    <span class="text-xs font-mono text-gray-400">
                                        {{ nameForm.nama_keluarga.length }}/40
                                    </span>
                                </div>
                                <input
                                    v-model="nameForm.nama_keluarga"
                                    type="text"
                                    maxlength="40"
                                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-[#3d3d3d] bg-white dark:bg-[#262626] text-gray-900 dark:text-[#f0efea] text-sm focus:outline-none focus:border-[#0b8043] dark:focus:border-[#3fcb7a] focus:ring-2 focus:ring-[#0b8043]/15 transition"
                                />
                                <p v-if="nameForm.errors.nama_keluarga" class="text-xs text-red-500 mt-1 font-medium">
                                    {{ nameForm.errors.nama_keluarga }}
                                </p>
                            </div>

                            <button
                                type="submit"
                                :disabled="nameForm.processing || nameForm.nama_keluarga === keluarga.nama_keluarga"
                                class="px-5 py-2.5 rounded-xl bg-[#0b8043] dark:bg-[#3fcb7a] text-white dark:text-[#121212] text-xs font-bold hover:bg-[#096836] dark:hover:bg-[#34aa66] disabled:opacity-40 transition cursor-pointer flex items-center gap-1.5"
                            >
                                <Loader2 v-if="nameForm.processing" class="w-3.5 h-3.5 animate-spin" />
                                <Save v-else class="w-3.5 h-3.5" />
                                <span>Simpan Nama</span>
                            </button>
                        </form>
                    </div>

                    <!-- Card 2: Daftar Anggota (Figma 35:9800) -->
                    <div class="bg-white dark:bg-[#1c1c1c] border border-gray-100 dark:border-[#2e2e2e] rounded-3xl p-6 sm:p-8 shadow-xs space-y-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-base font-bold text-gray-900 dark:text-[#f0efea]">
                                    Anggota ({{ anggota.length }})
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-[#a3a3a3] mt-0.5">
                                    Daftar orang yang saat ini terhubung di grup keluarga kamu.
                                </p>
                            </div>
                        </div>

                        <div class="divide-y divide-gray-100 dark:divide-[#262626]">
                            <div
                                v-for="member in anggota"
                                :key="member.id"
                                class="py-3.5 flex items-center justify-between gap-4"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <div
                                        class="w-10 h-10 rounded-full bg-emerald-100 dark:bg-[#17321f] text-[#0b8043] dark:text-[#3fcb7a] font-bold text-xs flex items-center justify-center shrink-0 border border-emerald-200/50 dark:border-[#2e2e2e]"
                                    >
                                        {{ member.initials }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-bold text-gray-900 dark:text-[#f0efea] truncate">
                                            {{ member.name }}
                                        </div>
                                        <div class="text-xs text-gray-400 dark:text-[#737373]">
                                            {{ member.role_label }}
                                            <span v-if="member.usia">• {{ member.usia }} tahun</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action: Keluarkan jika bukan Kepala Keluarga -->
                                <button
                                    v-if="!member.is_kepala"
                                    type="button"
                                    @click="openRemoveModal(member)"
                                    class="px-3.5 py-1.5 rounded-xl border border-red-200 dark:border-red-900/50 text-red-600 dark:text-red-400 text-xs font-semibold hover:bg-red-50 dark:hover:bg-red-950/30 transition cursor-pointer shrink-0"
                                >
                                    Keluarkan
                                </button>
                                <span
                                    v-else
                                    class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 dark:bg-[#17321f] text-[#0b8043] dark:text-[#3fcb7a] shrink-0"
                                >
                                    Kepala Keluarga
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column (Figma 35:9840) -->
                <div class="lg:col-span-5 space-y-6">
                    <!-- Card 1: Kode Grup (Figma 35:9841) -->
                    <div class="bg-white dark:bg-[#1c1c1c] border border-gray-100 dark:border-[#2e2e2e] rounded-3xl p-6 sm:p-8 shadow-xs space-y-4">
                        <h3 class="text-base font-bold text-gray-900 dark:text-[#f0efea]">
                            Kode grup
                        </h3>

                        <div class="p-4 rounded-2xl bg-gray-50 dark:bg-[#262626] border border-gray-100 dark:border-[#2e2e2e] flex items-center justify-between">
                            <span class="font-mono text-2xl font-black tracking-widest text-gray-900 dark:text-[#f0efea]">
                                {{ keluarga.kode_keluarga }}
                            </span>
                            <button
                                type="button"
                                @click="copyCode"
                                class="p-2 rounded-xl text-gray-500 hover:text-gray-900 dark:hover:text-[#f0efea] hover:bg-gray-200 dark:hover:bg-[#333] transition cursor-pointer"
                                title="Salin kode"
                            >
                                <Check v-if="copiedCode" class="w-5 h-5 text-emerald-500" />
                                <Copy v-else class="w-5 h-5" />
                            </button>
                        </div>

                        <p class="text-xs text-gray-500 dark:text-[#a3a3a3] leading-relaxed">
                            Membuat kode baru akan membuat kode lama tidak berlaku lagi bagi anggota baru.
                        </p>

                        <button
                            type="button"
                            @click="handleRegenerateCode"
                            :disabled="isRegeneratingCode"
                            class="px-4 py-2.5 rounded-xl border border-gray-200 dark:border-[#2e2e2e] text-xs font-semibold text-gray-700 dark:text-[#f0efea] hover:bg-gray-50 dark:hover:bg-[#262626] transition flex items-center gap-2 cursor-pointer"
                        >
                            <Loader2 v-if="isRegeneratingCode" class="w-3.5 h-3.5 animate-spin" />
                            <RefreshCw v-else class="w-3.5 h-3.5 text-[#0b8043] dark:text-[#3fcb7a]" />
                            <span>Buat kode baru</span>
                        </button>
                    </div>

                    <!-- Card 2: Alihkan Kepala Keluarga (Figma 57:11117) -->
                    <div class="bg-white dark:bg-[#1c1c1c] border border-gray-100 dark:border-[#2e2e2e] rounded-3xl p-6 sm:p-8 shadow-xs space-y-3.5">
                        <h3 class="text-base font-bold text-gray-900 dark:text-[#f0efea]">
                            Kepala keluarga
                        </h3>

                        <p class="text-xs text-gray-500 dark:text-[#a3a3a3] leading-relaxed">
                            Alihkan peran Kepala Keluarga ke anggota keluarga lain jika kamu ingin melepaskan hak kelola grup.
                        </p>

                        <button
                            type="button"
                            @click="isTransferModalOpen = true"
                            :disabled="anggota.length <= 1"
                            class="px-4 py-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/40 text-xs font-bold text-amber-700 dark:text-amber-300 hover:bg-amber-100 dark:hover:bg-amber-900/50 disabled:opacity-40 transition flex items-center gap-2 cursor-pointer"
                        >
                            <Shield class="w-4 h-4" />
                            <span>Alihkan Kepala Keluarga</span>
                        </button>
                        <p v-if="anggota.length <= 1" class="text-[11px] text-gray-400">
                            Undang anggota lain terlebih dahulu untuk mengalihkan peran.
                        </p>
                    </div>

                    <!-- Card 3: Hapus Grup (Figma 35:9852) -->
                    <div class="bg-white dark:bg-[#1c1c1c] border border-red-100 dark:border-red-900/30 rounded-3xl p-6 sm:p-8 shadow-xs space-y-3.5">
                        <h3 class="text-base font-bold text-red-600 dark:text-red-400">
                            Hapus grup
                        </h3>

                        <p class="text-xs text-gray-500 dark:text-[#a3a3a3] leading-relaxed">
                            Semua anggota akan keluar dari grup. Catatan makan harian masing-masing tetap tersimpan.
                        </p>

                        <button
                            type="button"
                            @click="isDeleteModalOpen = true"
                            class="px-4 py-2.5 rounded-xl bg-red-600 text-white text-xs font-bold hover:bg-red-700 transition flex items-center gap-2 cursor-pointer"
                        >
                            <Trash2 class="w-4 h-4" />
                            <span>Hapus Grup</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <TransferHeadModal
            :show="isTransferModalOpen"
            :members="anggota"
            @close="isTransferModalOpen = false"
        />

        <ConfirmRemoveMemberModal
            :show="!!memberToRemove"
            :member="memberToRemove"
            @close="memberToRemove = null"
        />

        <ConfirmDeleteGroupModal
            :show="isDeleteModalOpen"
            :family-name="keluarga.nama_keluarga"
            @close="isDeleteModalOpen = false"
        />
    </UserLayout>
</template>
