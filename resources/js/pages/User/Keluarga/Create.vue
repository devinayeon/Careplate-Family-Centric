<script setup lang="ts">
import { ref, watch, computed } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import UserLayout from "@/Layouts/UserLayout.vue";
import {
    ArrowLeft,
    Users,
    Copy,
    Lock,
    CheckCircle2,
    AlertCircle,
    Loader2,
    Sparkles,
} from "lucide-vue-next";

const form = useForm({
    nama_keluarga: "",
});

const isChecking = ref(false);
const isAvailable = ref<boolean | null>(null);
const checkMessage = ref("");
let checkTimeout: ReturnType<typeof setTimeout> | null = null;

const characterCount = computed(() => form.nama_keluarga.length);

const handleNameInput = () => {
    isAvailable.value = null;
    checkMessage.value = "";

    if (form.nama_keluarga.trim().length < 3) {
        return;
    }

    if (checkTimeout) clearTimeout(checkTimeout);
    checkTimeout = setTimeout(() => {
        checkAvailability();
    }, 400);
};

const checkAvailability = async () => {
    try {
        isChecking.value = true;
        const response = await fetch(route("user.family.check_name"), {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN":
                    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
                Accept: "application/json",
            },
            body: JSON.stringify({ nama_keluarga: form.nama_keluarga.trim() }),
        });

        const data = await response.json();
        isAvailable.value = data.available;
        checkMessage.value = data.message;
    } catch {
        // Fallback
    } finally {
        isChecking.value = false;
    }
};

const submitCreate = () => {
    if (form.nama_keluarga.trim().length < 3 || form.processing) return;

    form.post(route("user.family.store"));
};
</script>

<template>
    <UserLayout title="Buat Grup Keluarga">
        <Head title="Buat Grup Keluarga - CarePlate" />

        <div class="max-w-5xl mx-auto space-y-8 pb-12">
            <!-- Back navigation -->
            <div>
                <Link
                    :href="route('user.family')"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-gray-600 dark:text-[#a3a3a3] hover:text-[#0b8043] dark:hover:text-[#3fcb7a] transition-colors group cursor-pointer"
                >
                    <ArrowLeft class="w-4 h-4 group-hover:-translate-x-1 transition-transform" />
                    <span>Kembali ke Keluarga</span>
                </Link>
            </div>

            <!-- Page Title Header -->
            <div>
                <h1 class="font-display text-3xl sm:text-4xl font-extrabold text-gray-900 dark:text-[#f0efea]">
                    Buat grup keluarga
                </h1>
                <p class="text-sm text-gray-500 dark:text-[#a3a3a3] mt-1">
                    Beri nama untuk grup keluargamu dan mulailah melacak kebiasaan gizi bersama.
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <!-- Left Form Column (Figma 26:6540) -->
                <div class="lg:col-span-7 bg-white dark:bg-[#1c1c1c] border border-gray-100 dark:border-[#2e2e2e] rounded-3xl p-6 sm:p-8 shadow-xs space-y-6">
                    <form @submit.prevent="submitCreate" class="space-y-6">
                        <!-- Input Nama Grup -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-[#a3a3a3]">
                                    Nama grup keluarga <span class="text-red-500">*</span>
                                </label>
                                <span
                                    class="text-xs font-mono"
                                    :class="characterCount > 40 ? 'text-red-500 font-bold' : 'text-gray-400 dark:text-[#737373]'"
                                >
                                    {{ characterCount }}/40
                                </span>
                            </div>

                            <div class="relative">
                                <input
                                    v-model="form.nama_keluarga"
                                    @input="handleNameInput"
                                    type="text"
                                    maxlength="40"
                                    placeholder="Contoh: Keluarga Santoso"
                                    autofocus
                                    class="w-full px-4 py-3.5 text-base sm:text-lg font-semibold rounded-2xl border bg-white dark:bg-[#262626] text-gray-900 dark:text-[#f0efea] placeholder-gray-400 dark:placeholder-[#737373] focus:outline-none focus:ring-2 transition"
                                    :class="[
                                        isAvailable === true
                                            ? 'border-emerald-500 focus:border-emerald-500 focus:ring-emerald-500/15'
                                            : isAvailable === false
                                            ? 'border-red-500 focus:border-red-500 focus:ring-red-500/15'
                                            : 'border-gray-200 dark:border-[#3d3d3d] focus:border-[#0b8043] dark:focus:border-[#3fcb7a] focus:ring-[#0b8043]/15'
                                    ]"
                                />

                                <div class="absolute right-4 top-1/2 -translate-y-1/2 flex items-center">
                                    <Loader2 v-if="isChecking" class="w-4 h-4 animate-spin text-gray-400" />
                                    <CheckCircle2 v-else-if="isAvailable === true" class="w-5 h-5 text-emerald-500" />
                                    <AlertCircle v-else-if="isAvailable === false" class="w-5 h-5 text-red-500" />
                                </div>
                            </div>

                            <p v-if="checkMessage" class="text-xs mt-1.5 font-medium" :class="isAvailable ? 'text-emerald-600 dark:text-[#3fcb7a]' : 'text-red-500'">
                                {{ checkMessage }}
                            </p>
                            <p v-if="form.errors.nama_keluarga" class="text-xs text-red-500 mt-1.5 font-medium">
                                {{ form.errors.nama_keluarga }}
                            </p>
                        </div>

                        <!-- Hak & Keuntungan Kepala Keluarga (Figma 26:6563) -->
                        <div class="p-5 rounded-2xl bg-gray-50/80 dark:bg-[#262626] border border-gray-100 dark:border-[#2e2e2e] space-y-3.5">
                            <div class="flex items-center gap-3 text-xs sm:text-sm text-gray-700 dark:text-[#d4d4d4]">
                                <div class="w-7 h-7 rounded-lg bg-emerald-100/70 dark:bg-[#17321f] text-[#0b8043] dark:text-[#3fcb7a] flex items-center justify-center shrink-0">
                                    <Users class="w-4 h-4" />
                                </div>
                                <span>Kamu otomatis menjadi <strong>Kepala Keluarga</strong></span>
                            </div>

                            <div class="flex items-center gap-3 text-xs sm:text-sm text-gray-700 dark:text-[#d4d4d4]">
                                <div class="w-7 h-7 rounded-lg bg-emerald-100/70 dark:bg-[#17321f] text-[#0b8043] dark:text-[#3fcb7a] flex items-center justify-center shrink-0">
                                    <Copy class="w-4 h-4" />
                                </div>
                                <span>Mendapat kode 6 karakter & tautan untuk dibagikan</span>
                            </div>

                            <div class="flex items-center gap-3 text-xs sm:text-sm text-gray-700 dark:text-[#d4d4d4]">
                                <div class="w-7 h-7 rounded-lg bg-emerald-100/70 dark:bg-[#17321f] text-[#0b8043] dark:text-[#3fcb7a] flex items-center justify-center shrink-0">
                                    <Lock class="w-4 h-4" />
                                </div>
                                <span>Data medis, BMI, dan berat badan anggota tetap privat</span>
                            </div>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.nama_keluarga.trim().length < 3 || isAvailable === false || form.processing"
                            class="w-full py-3.5 px-6 rounded-2xl bg-[#0b8043] dark:bg-[#3fcb7a] text-white dark:text-[#121212] font-bold text-base hover:bg-[#096836] dark:hover:bg-[#34aa66] disabled:opacity-40 shadow-sm transition-all flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <Loader2 v-if="form.processing" class="w-5 h-5 animate-spin" />
                            <span>Buat Grup Keluarga</span>
                        </button>
                    </form>
                </div>

                <!-- Right Preview Column (Figma 26:6576) -->
                <div class="lg:col-span-5 bg-white dark:bg-[#1c1c1c] border border-gray-100 dark:border-[#2e2e2e] rounded-3xl p-6 sm:p-8 shadow-xs space-y-4">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-[#737373]">
                        Pratinjau Kartu Grup
                    </span>

                    <div class="p-6 rounded-2xl bg-[#fff2eb] dark:bg-[#261f1c] border border-orange-100 dark:border-[#3d2c26] space-y-3">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-[#f0efea] truncate">
                            {{ form.nama_keluarga.trim() || "Nama Keluarga Anda" }}
                        </h3>

                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-[#0b8043] text-white dark:bg-[#3fcb7a] dark:text-[#121212]">
                                Kepala Keluarga
                            </span>
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-medium bg-white/80 dark:bg-[#333] text-gray-700 dark:text-gray-300">
                                1 Anggota
                            </span>
                        </div>

                        <p class="text-xs text-gray-500 dark:text-[#a3a3a3] pt-2 border-t border-orange-200/50 dark:border-[#3d2c26]">
                            Kode grup 6 karakter dan tautan undangan akan otomatis dibuat setelah kamu menekan tombol Buat Grup.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
