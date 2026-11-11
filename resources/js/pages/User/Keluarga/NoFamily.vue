<script setup lang="ts">
import { ref } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import UserLayout from "@/Layouts/UserLayout.vue";
import JoinCodeModal from "@/Components/Family/JoinCodeModal.vue";
import { Users, Calendar, Lock, ArrowRight, Sparkles, PlusCircle, KeyRound } from "lucide-vue-next";

const isJoinModalOpen = ref(false);
</script>

<template>
    <UserLayout title="Keluarga">
        <Head title="Grup Keluarga - CarePlate" />

        <div class="space-y-8 pb-12">
            <!-- 1. Header Hero Banner (Figma 19:4307) -->
            <div
                class="relative overflow-hidden rounded-3xl bg-[#fff2eb] dark:bg-[#261f1c] border border-orange-100/70 dark:border-[#3d2c26] p-6 sm:p-8 md:p-10 transition-colors shadow-xs"
            >
                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="max-w-xl">
                        <h1
                            class="font-display text-3xl sm:text-4xl md:text-5xl font-extrabold tracking-tight text-[#191919] dark:text-[#f0efea]"
                        >
                            Keluarga
                        </h1>
                        <p class="mt-2 text-sm sm:text-base text-gray-600 dark:text-[#a3a3a3] leading-relaxed">
                            Catat bersama, lihat siapa saja yang sudah mencatat minggu ini, dan saling menyemangati untuk hidup lebih sehat.
                        </p>
                    </div>

                    <!-- Decorative Food/Meal Illustration -->
                    <div class="shrink-0 flex items-center justify-center md:pr-4">
                        <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-full bg-orange-100/70 dark:bg-[#382319] flex items-center justify-center text-4xl shadow-inner">
                            🥗
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Two Big Action Cards: Buat vs Gabung (Figma 19:4311) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8">
                <!-- Option 1: Buat Grup Keluarga (Figma 19:4312) -->
                <div
                    class="bg-white dark:bg-[#1c1c1c] border border-gray-100 dark:border-[#2e2e2e] rounded-3xl p-8 sm:p-10 flex flex-col items-center text-center justify-between shadow-xs hover:shadow-md transition-all group"
                >
                    <div class="flex flex-col items-center">
                        <div
                            class="w-24 h-24 rounded-3xl bg-emerald-50 dark:bg-[#17321f] text-[#0b8043] dark:text-[#3fcb7a] flex items-center justify-center mb-6 group-hover:scale-105 transition-transform"
                        >
                            <PlusCircle class="w-12 h-12 stroke-[1.5]" />
                        </div>

                        <h2 class="text-2xl font-bold text-gray-900 dark:text-[#f0efea] mb-3">
                            Buat grup keluarga
                        </h2>

                        <p class="text-sm text-gray-600 dark:text-[#a3a3a3] leading-relaxed max-w-sm mb-8">
                            Kamu otomatis menjadi <span class="font-semibold text-gray-800 dark:text-gray-200">Kepala Keluarga</span> dan mendapatkan kode 6 karakter serta tautan untuk mengundang seluruh anggota.
                        </p>
                    </div>

                    <Link
                        :href="route('user.family.create')"
                        class="w-full sm:w-auto px-8 py-3 rounded-xl bg-[#0b8043] dark:bg-[#3fcb7a] text-white dark:text-[#121212] font-bold text-sm hover:bg-[#096836] dark:hover:bg-[#34aa66] transition-all shadow-sm flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <span>Buat Grup</span>
                        <ArrowRight class="w-4 h-4" />
                    </Link>
                </div>

                <!-- Option 2: Gabung dengan Kode (Figma 19:4336) -->
                <div
                    class="bg-white dark:bg-[#1c1c1c] border border-gray-100 dark:border-[#2e2e2e] rounded-3xl p-8 sm:p-10 flex flex-col items-center text-center justify-between shadow-xs hover:shadow-md transition-all group"
                >
                    <div class="flex flex-col items-center">
                        <div
                            class="w-24 h-24 rounded-3xl bg-amber-50 dark:bg-amber-950/30 text-amber-600 dark:text-amber-400 flex items-center justify-center mb-6 group-hover:scale-105 transition-transform"
                        >
                            <KeyRound class="w-12 h-12 stroke-[1.5]" />
                        </div>

                        <h2 class="text-2xl font-bold text-gray-900 dark:text-[#f0efea] mb-3">
                            Gabung dengan kode
                        </h2>

                        <p class="text-sm text-gray-600 dark:text-[#a3a3a3] leading-relaxed max-w-sm mb-8">
                            Sudah ada anggota keluarga yang membuat grup? Masukkan 6 karakter kode undangan yang dibagikan oleh Kepala Keluarga.
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="isJoinModalOpen = true"
                        class="w-full sm:w-auto px-8 py-3 rounded-xl bg-gray-900 dark:bg-[#f0efea] text-white dark:text-[#121212] font-bold text-sm hover:bg-black dark:hover:bg-white transition-all shadow-sm flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <span>Gabung ke Grup</span>
                        <ArrowRight class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- 3. Bottom Information Banner: Yang bisa dilihat keluarga (Figma 19:4355) -->
            <div
                class="bg-white dark:bg-[#1c1c1c] border border-gray-100 dark:border-[#2e2e2e] rounded-3xl p-6 sm:p-8 shadow-xs"
            >
                <h3 class="text-base font-bold text-gray-900 dark:text-[#f0efea] mb-6">
                    Yang bisa dilihat keluarga
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="flex items-start gap-3.5">
                        <div
                            class="w-10 h-10 rounded-2xl bg-emerald-50 dark:bg-[#17321f] text-[#0b8043] dark:text-[#3fcb7a] flex items-center justify-center shrink-0"
                        >
                            <Users class="w-5 h-5" />
                        </div>
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-[#f0efea]">
                                Siapa saja anggota grup
                            </h4>
                            <p class="text-xs text-gray-500 dark:text-[#a3a3a3] mt-1 leading-relaxed">
                                Nama, status peran, serta kebersamaan dalam memantau kebiasaan sehat keluarga.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3.5">
                        <div
                            class="w-10 h-10 rounded-2xl bg-emerald-50 dark:bg-[#17321f] text-[#0b8043] dark:text-[#3fcb7a] flex items-center justify-center shrink-0"
                        >
                            <Calendar class="w-5 h-5" />
                        </div>
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-[#f0efea]">
                                Hari yang sudah tercatat
                            </h4>
                            <p class="text-xs text-gray-500 dark:text-[#a3a3a3] mt-1 leading-relaxed">
                                Keaktifan mingguan anggota dalam mencatat makanan untuk mencapai target bersama 5 hari.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3.5">
                        <div
                            class="w-10 h-10 rounded-2xl bg-emerald-50 dark:bg-[#17321f] text-[#0b8043] dark:text-[#3fcb7a] flex items-center justify-center shrink-0"
                        >
                            <Lock class="w-5 h-5" />
                        </div>
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-[#f0efea]">
                                Data medis tetap privat
                            </h4>
                            <p class="text-xs text-gray-500 dark:text-[#a3a3a3] mt-1 leading-relaxed">
                                Nilai BMI, kalkulasi berat badan, serta data sensitif kesehatan lainnya tetap terjaga privat.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Join with code Modal -->
        <JoinCodeModal
            :show="isJoinModalOpen"
            @close="isJoinModalOpen = false"
        />
    </UserLayout>
</template>
