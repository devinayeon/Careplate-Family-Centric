<script setup lang="ts">
import { ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import UserLayout from "@/Layouts/UserLayout.vue";
import {
    Users,
    Copy,
    Check,
    Share2,
    Settings,
    LogOut,
    CheckCircle2,
    Circle,
    Flame,
    Utensils,
    Calendar,
    ChevronDown,
    ChevronUp,
    AlertCircle,
    Info,
} from "lucide-vue-next";

interface WeeklyDot {
    label: string;
    date: string;
    logged: boolean;
    is_today: boolean;
    is_future: boolean;
}

interface TodayMealItem {
    id: number;
    nama_makanan: string;
    kategori_makanan: string;
    waktu_konsumsi: string;
    jumlah_porsi: number;
    total_kalori: number;
}

interface MemberDetail {
    id: number;
    user_id: number;
    name: string;
    email: string;
    initials: string;
    is_kepala: boolean;
    role_label: string;
    usia: number | null;
    total_hari_minggu_ini: number;
    target_tercapai: boolean;
    weekly_dots: WeeklyDot[];
    total_kalori_hari_ini: number;
    target_kalori_harian: number;
    catatan_hari_ini: TodayMealItem[];
}

const props = defineProps<{
    keluarga: {
        id: number;
        nama_keluarga: string;
        kode_keluarga: string;
        kepala_keluarga_nama: string;
        total_anggota: number;
        link_undangan: string;
    };
    isKepalaKeluarga: boolean;
    currentUserId: number;
    periodeMinggu: string;
    targetTercapaiCount: number;
    totalAnggota: number;
    anggotaList: MemberDetail[];
}>();

const copiedCode = ref(false);
const copiedLink = ref(false);
const expandedMemberId = ref<number | null>(null);

const copyCode = async () => {
    try {
        await navigator.clipboard.writeText(props.keluarga.kode_keluarga);
        copiedCode.value = true;
        setTimeout(() => (copiedCode.value = false), 2000);
    } catch {
        // Fallback
    }
};

const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(props.keluarga.link_undangan);
        copiedLink.value = true;
        setTimeout(() => (copiedLink.value = false), 2000);
    } catch {
        // Fallback
    }
};

const toggleMemberAccordion = (id: number) => {
    expandedMemberId.value = expandedMemberId.value === id ? null : id;
};

const confirmLeaveGroup = () => {
    if (confirm("Apakah kamu yakin ingin keluar dari grup keluarga ini?")) {
        router.post(route("user.family.leave"));
    }
};

const formatWaktu = (waktu: string) => {
    const map: Record<string, string> = {
        sarapan: "Sarapan",
        makan_siang: "Makan Siang",
        makan_malam: "Makan Malam",
        camilan: "Camilan",
    };
    return map[waktu] || waktu;
};
</script>

<template>
    <UserLayout :title="keluarga.nama_keluarga">
        <Head :title="`${keluarga.nama_keluarga} - Meja Keluarga`" />

        <div class="space-y-8 pb-16">
            <!-- 1. Header Meja Keluarga (Figma 19:4004) -->
            <div
                class="relative overflow-hidden rounded-3xl bg-[#fff2eb] dark:bg-[#261f1c] border border-orange-100/70 dark:border-[#3d2c26] p-6 sm:p-8 md:p-10 transition-colors shadow-xs"
            >
                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="space-y-2">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-white/80 dark:bg-[#333] text-gray-700 dark:text-gray-300">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span>{{ isKepalaKeluarga ? "Kamu Kepala Keluarga" : `Kepala: ${keluarga.kepala_keluarga_nama}` }}</span>
                            <span>•</span>
                            <span>{{ totalAnggota }} Anggota</span>
                        </div>

                        <h1
                            class="font-display text-3xl sm:text-4xl md:text-5xl font-extrabold tracking-tight text-[#191919] dark:text-[#f0efea]"
                        >
                            {{ keluarga.nama_keluarga }}
                        </h1>
                        <p class="text-xs sm:text-sm text-gray-600 dark:text-[#a3a3a3]">
                            Saling memantau kebiasaan sehat dan aktivitas gizi keluarga setiap hari.
                        </p>
                    </div>

                    <!-- Right Controls: Kode, Link, Kelola -->
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Kode Grup Badge -->
                        <div
                            class="flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-white dark:bg-[#1c1c1c] border border-gray-200 dark:border-[#2e2e2e] shadow-2xs text-xs"
                        >
                            <span class="text-gray-400 dark:text-[#737373]">Kode:</span>
                            <span class="font-mono font-bold text-sm tracking-wider text-gray-900 dark:text-[#f0efea]">
                                {{ keluarga.kode_keluarga }}
                            </span>
                            <button
                                type="button"
                                @click="copyCode"
                                class="p-1 rounded-lg text-gray-400 hover:text-gray-700 dark:hover:text-[#f0efea] hover:bg-gray-100 dark:hover:bg-[#262626] transition cursor-pointer"
                                title="Salin kode keluarga"
                            >
                                <Check v-if="copiedCode" class="w-4 h-4 text-emerald-500" />
                                <Copy v-else class="w-4 h-4" />
                            </button>
                        </div>

                        <!-- Button Salin Link Undangan -->
                        <button
                            type="button"
                            @click="copyLink"
                            class="px-4 py-2.5 rounded-2xl bg-white dark:bg-[#1c1c1c] border border-gray-200 dark:border-[#2e2e2e] text-xs font-semibold text-gray-700 dark:text-[#f0efea] hover:bg-gray-50 dark:hover:bg-[#262626] shadow-2xs transition flex items-center gap-1.5 cursor-pointer"
                        >
                            <Check v-if="copiedLink" class="w-4 h-4 text-emerald-500" />
                            <Share2 v-else class="w-4 h-4 text-[#0b8043] dark:text-[#3fcb7a]" />
                            <span>{{ copiedLink ? "Tautan Disalin!" : "Salin Link Undangan" }}</span>
                        </button>

                        <!-- Tombol Kelola Grup (Khusus Kepala Keluarga) -->
                        <Link
                            v-if="isKepalaKeluarga"
                            :href="route('user.family.manage')"
                            class="px-4 py-2.5 rounded-2xl bg-[#0b8043] dark:bg-[#3fcb7a] text-white dark:text-[#121212] text-xs font-bold hover:bg-[#096836] dark:hover:bg-[#34aa66] shadow-xs transition flex items-center gap-1.5 cursor-pointer"
                        >
                            <Settings class="w-4 h-4" />
                            <span>Kelola Grup</span>
                        </Link>

                        <!-- Tombol Keluar dari Grup (Jika Anggota Biasa) -->
                        <button
                            v-else
                            type="button"
                            @click="confirmLeaveGroup"
                            class="px-4 py-2.5 rounded-2xl bg-white dark:bg-[#1c1c1c] border border-red-200 dark:border-red-900/50 text-xs font-semibold text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 transition flex items-center gap-1.5 cursor-pointer"
                        >
                            <LogOut class="w-4 h-4" />
                            <span>Keluar Grup</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <!-- 2. Left Column: Tabel Meja Keluarga Mingguan (Figma 19:4009) -->
                <div class="lg:col-span-7 space-y-6">
                    <div
                        class="bg-white dark:bg-[#1c1c1c] border border-gray-100 dark:border-[#2e2e2e] rounded-3xl p-6 sm:p-8 shadow-xs space-y-6"
                    >
                        <!-- Card Header & Progress -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <h2 class="text-lg sm:text-xl font-bold text-gray-900 dark:text-[#f0efea]">
                                    Meja Keluarga • {{ periodeMinggu }}
                                </h2>
                                <p class="text-xs sm:text-sm text-gray-500 dark:text-[#a3a3a3] mt-0.5">
                                    Target bersama: masing-masing mencatat 5 hari dalam seminggu.
                                </p>
                            </div>

                            <div class="text-right shrink-0">
                                <span class="text-2xl font-black text-[#0b8043] dark:text-[#3fcb7a]">
                                    {{ targetTercapaiCount }} dari {{ totalAnggota }}
                                </span>
                                <span class="block text-[11px] text-gray-400 dark:text-[#737373]">
                                    mencapai 5 hari
                                </span>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="w-full bg-gray-100 dark:bg-[#262626] rounded-full h-3 overflow-hidden">
                            <div
                                class="bg-[#0b8043] dark:bg-[#3fcb7a] h-full rounded-full transition-all duration-500"
                                :style="{ width: `${totalAnggota > 0 ? (targetTercapaiCount / totalAnggota) * 100 : 0}%` }"
                            ></div>
                        </div>

                        <!-- Weekly Grid Table -->
                        <div class="overflow-x-auto no-scrollbar pt-2">
                            <table class="w-full text-left border-collapse min-w-[540px]">
                                <thead>
                                    <tr class="border-b border-gray-100 dark:border-[#262626] text-xs font-semibold text-gray-400 dark:text-[#737373]">
                                        <th class="py-3 px-2">Anggota</th>
                                        <th class="py-3 px-2 text-center w-10">Sn</th>
                                        <th class="py-3 px-2 text-center w-10">Sl</th>
                                        <th class="py-3 px-2 text-center w-10">Rb</th>
                                        <th class="py-3 px-2 text-center w-10">Km</th>
                                        <th class="py-3 px-2 text-center w-10">Jm</th>
                                        <th class="py-3 px-2 text-center w-10">Sb</th>
                                        <th class="py-3 px-2 text-center w-10">Mg</th>
                                        <th class="py-3 px-2 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 dark:divide-[#262626]">
                                    <tr
                                        v-for="member in anggotaList"
                                        :key="member.id"
                                        class="hover:bg-gray-50/50 dark:hover:bg-[#222] transition-colors"
                                    >
                                        <!-- Member Profile Info -->
                                        <td class="py-3.5 px-2">
                                            <div class="flex items-center gap-3">
                                                <div
                                                    class="w-9 h-9 rounded-full bg-emerald-100 dark:bg-[#17321f] text-[#0b8043] dark:text-[#3fcb7a] font-bold text-xs flex items-center justify-center shrink-0 border border-emerald-200/50 dark:border-[#2e2e2e]"
                                                >
                                                    {{ member.initials }}
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="text-sm font-bold text-gray-900 dark:text-[#f0efea] truncate">
                                                        {{ member.name }}
                                                    </div>
                                                    <div class="text-[11px] text-gray-400 dark:text-[#737373]">
                                                        {{ member.role_label }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- 7 Days Dots (Sn - Mg) -->
                                        <td
                                            v-for="dot in member.weekly_dots"
                                            :key="dot.date"
                                            class="py-3.5 px-2 text-center"
                                        >
                                            <div class="flex items-center justify-center">
                                                <!-- Logged dot -->
                                                <div
                                                    v-if="dot.logged"
                                                    class="w-6 h-6 rounded-full bg-[#0b8043] dark:bg-[#3fcb7a] text-white dark:text-[#121212] flex items-center justify-center shadow-2xs"
                                                    title="Mencatat makanan"
                                                >
                                                    <Check class="w-3.5 h-3.5 stroke-[3]" />
                                                </div>

                                                <!-- Unlogged dot for today -->
                                                <div
                                                    v-else-if="dot.is_today"
                                                    class="w-6 h-6 rounded-full border-2 border-dashed border-[#0b8043] dark:border-[#3fcb7a] bg-emerald-50/40 dark:bg-[#17321f]/20"
                                                    title="Belum mencatat hari ini"
                                                ></div>

                                                <!-- Future / past empty dot -->
                                                <div
                                                    v-else
                                                    class="w-5 h-5 rounded-full bg-gray-100 dark:bg-[#262626] border border-gray-200/60 dark:border-[#333]"
                                                ></div>
                                            </div>
                                        </td>

                                        <!-- Total Days Badge -->
                                        <td class="py-3.5 px-2 text-right">
                                            <span
                                                class="inline-block px-2.5 py-1 rounded-full text-xs font-bold"
                                                :class="
                                                    member.target_tercapai
                                                        ? 'bg-emerald-100 text-[#0b8043] dark:bg-[#17321f] dark:text-[#3fcb7a]'
                                                        : 'bg-gray-100 text-gray-600 dark:bg-[#262626] dark:text-[#a3a3a3]'
                                                "
                                            >
                                                {{ member.total_hari_minggu_ini }}/5 hari
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Reset notice note -->
                        <div class="p-3.5 rounded-2xl bg-gray-50 dark:bg-[#262626] border border-gray-100 dark:border-[#2e2e2e] flex items-center gap-2.5 text-xs text-gray-500 dark:text-[#a3a3a3]">
                            <Info class="w-4 h-4 text-[#0b8043] dark:text-[#3fcb7a] shrink-0" />
                            <span>Aktivitas meja keluarga dihitung per minggu berjalan dan otomatis ter-reset setiap hari Senin.</span>
                        </div>
                    </div>
                </div>

                <!-- 3. Right Column: Aktivitas Makanan & Kalori Hari Ini (Permintaan Spesifik User) -->
                <div class="lg:col-span-5 space-y-6">
                    <div
                        class="bg-white dark:bg-[#1c1c1c] border border-gray-100 dark:border-[#2e2e2e] rounded-3xl p-6 sm:p-8 shadow-xs space-y-5"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-orange-100/70 dark:bg-[#3a261c] text-[#eb4416] dark:text-[#ff7a4d] flex items-center justify-center">
                                    <Utensils class="w-4 h-4" />
                                </div>
                                <h3 class="text-base font-bold text-gray-900 dark:text-[#f0efea]">
                                    Aktivitas Makan Hari Ini
                                </h3>
                            </div>
                            <span class="text-xs font-medium text-gray-400 dark:text-[#737373]">
                                Hari ini
                            </span>
                        </div>

                        <!-- List Anggota & Detail Makanan Hari Ini -->
                        <div class="space-y-4">
                            <div
                                v-for="member in anggotaList"
                                :key="member.id"
                                class="border border-gray-100 dark:border-[#2e2e2e] rounded-2xl overflow-hidden transition-all"
                            >
                                <!-- Accordion Header -->
                                <button
                                    type="button"
                                    @click="toggleMemberAccordion(member.id)"
                                    class="w-full p-4 flex items-center justify-between text-left hover:bg-gray-50/80 dark:hover:bg-[#262626] transition-colors cursor-pointer"
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-8 h-8 rounded-full bg-emerald-50 dark:bg-[#17321f] text-[#0b8043] dark:text-[#3fcb7a] font-bold text-xs flex items-center justify-center shrink-0"
                                        >
                                            {{ member.initials }}
                                        </div>
                                        <div>
                                            <div class="text-sm font-bold text-gray-900 dark:text-[#f0efea]">
                                                {{ member.name }}
                                            </div>
                                            <div class="text-[11px] text-gray-400 dark:text-[#737373]">
                                                {{ member.catatan_hari_ini.length }} makanan tercatat
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <div class="text-right">
                                            <div class="text-xs font-extrabold text-[#0b8043] dark:text-[#3fcb7a]">
                                                {{ Math.round(member.total_kalori_hari_ini) }} kkal
                                            </div>
                                            <div class="text-[10px] text-gray-400 dark:text-[#737373]">
                                                dari {{ Math.round(member.target_kalori_harian) }} kkal
                                            </div>
                                        </div>
                                        <component
                                            :is="expandedMemberId === member.id ? ChevronUp : ChevronDown"
                                            class="w-4 h-4 text-gray-400"
                                        />
                                    </div>
                                </button>

                                <!-- Accordion Body: Rincian Makanan -->
                                <div
                                    v-if="expandedMemberId === member.id"
                                    class="p-4 bg-gray-50/50 dark:bg-[#171717] border-t border-gray-100 dark:border-[#2e2e2e] space-y-3"
                                >
                                    <div v-if="member.catatan_hari_ini.length === 0" class="text-xs text-gray-400 dark:text-[#737373] text-center py-2">
                                        Belum ada makanan yang dicatat hari ini.
                                    </div>

                                    <div
                                        v-for="item in member.catatan_hari_ini"
                                        :key="item.id"
                                        class="flex items-center justify-between text-xs py-1.5 border-b border-gray-100 dark:border-[#262626] last:border-0"
                                    >
                                        <div class="min-w-0 pr-2">
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-200 dark:bg-[#262626] text-gray-700 dark:text-gray-300 mr-1.5">
                                                {{ formatWaktu(item.waktu_konsumsi) }}
                                            </span>
                                            <span class="font-medium text-gray-800 dark:text-[#f0efea]">
                                                {{ item.nama_makanan }}
                                            </span>
                                        </div>
                                        <div class="font-bold text-[#0b8043] dark:text-[#3fcb7a] shrink-0">
                                            {{ Math.round(item.total_kalori) }} kkal
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
