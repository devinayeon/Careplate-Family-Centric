<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import NutritionistLayout from '@/Layouts/NutritionistLayout.vue';
import {
    Users,
    MessageSquare,
    Clock,
    Star,
    ArrowRight,
    Video,
    CheckCircle2,
    Calendar,
} from 'lucide-vue-next';

defineProps<{
    stats: {
        klien_aktif: number;
        konsultasi_hari_ini: number;
        menunggu_respon: number;
        rating_kepuasan: string;
    };
    jadwal_hari_ini: Array<{
        id: number;
        klien_nama: string;
        topik: string;
        jam: string;
        status: string;
    }>;
    antrean_konsultasi: Array<{
        id: number;
        nama: string;
        subjudul: string;
        pesan_terakhir: string;
        waktu: string;
        status: string;
        unread: boolean;
    }>;
}>();
</script>

<template>
    <Head title="Dashboard Ahli Gizi" />

    <NutritionistLayout>
        <div class="max-w-6xl space-y-6">
            <!-- 1. Header & Direct CTA to Konsultasi Workspace -->
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-gray-900 dark:text-[#f0efea] sm:text-3xl"
                    >
                        Dashboard Ahli Gizi
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Pantau jadwal sesi, antrean klien, dan status konsultasi
                        gizi keluarga.
                    </p>
                </div>

                <Link
                    :href="route('nutritionist.consultations')"
                    class="inline-flex items-center gap-2 self-start rounded-full bg-[#0f6b36] px-5 py-2.5 text-xs font-semibold text-white shadow-xs transition-all hover:bg-[#0c592c] sm:self-center"
                >
                    <MessageSquare class="h-4 w-4" />
                    <span>Buka Ruang Konsultasi</span>
                </Link>
            </div>

            <!-- 2. Metric Overview Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    class="rounded-3xl border border-gray-100 bg-white p-5 shadow-xs dark:border-[#262626] dark:bg-[#1a1a1a]"
                >
                    <div
                        class="mb-3 flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400"
                    >
                        <Users class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                        <span>Klien Aktif</span>
                    </div>
                    <div
                        class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-[#f0efea]"
                    >
                        {{ stats.klien_aktif }}
                    </div>
                    <div class="mt-2 text-[11px] font-medium text-emerald-600 dark:text-[#3fcb7a]">
                        Dalam pemantauan rutin
                    </div>
                </div>

                <div
                    class="rounded-3xl border border-gray-100 bg-white p-5 shadow-xs dark:border-[#262626] dark:bg-[#1a1a1a]"
                >
                    <div
                        class="mb-3 flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400"
                    >
                        <Calendar class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                        <span>Konsultasi Hari Ini</span>
                    </div>
                    <div
                        class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-[#f0efea]"
                    >
                        {{ stats.konsultasi_hari_ini }}
                    </div>
                    <div class="mt-2 text-[11px] font-medium text-gray-400 dark:text-gray-500">
                        3 chat • 2 video meeting
                    </div>
                </div>

                <div
                    class="rounded-3xl border border-gray-100 bg-white p-5 shadow-xs dark:border-[#262626] dark:bg-[#1a1a1a]"
                >
                    <div
                        class="mb-3 flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400"
                    >
                        <Clock class="h-4 w-4 text-amber-500" />
                        <span>Menunggu Respon</span>
                    </div>
                    <div
                        class="text-3xl font-extrabold tracking-tight text-amber-600 dark:text-amber-400"
                    >
                        {{ stats.menunggu_respon }}
                    </div>
                    <div class="mt-2 text-[11px] font-medium text-amber-600 dark:text-amber-400">
                        Perlu balasan &gt; 1 jam
                    </div>
                </div>

                <div
                    class="rounded-3xl border border-gray-100 bg-white p-5 shadow-xs dark:border-[#262626] dark:bg-[#1a1a1a]"
                >
                    <div
                        class="mb-3 flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400"
                    >
                        <Star class="h-4 w-4 fill-current text-yellow-400" />
                        <span>Rating Kepuasan</span>
                    </div>
                    <div
                        class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-[#f0efea]"
                    >
                        {{ stats.rating_kepuasan }}
                    </div>
                    <div class="mt-2 text-[11px] font-medium text-gray-400 dark:text-gray-500">
                        Dari 89 ulasan klien
                    </div>
                </div>
            </div>

            <!-- 3. Bottom Grid: Antrean Konsultasi & Jadwal Hari Ini -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                <!-- Antrean Konsultasi Masuk (7 cols) -->
                <div
                    class="rounded-3xl border border-gray-100 bg-white p-6 shadow-xs dark:border-[#262626] dark:bg-[#1a1a1a] lg:col-span-7"
                >
                    <div class="mb-5 flex items-center justify-between">
                        <h2 class="text-base font-bold text-gray-900 dark:text-[#f0efea]">
                            Pesan Konsultasi Masuk
                        </h2>
                        <Link
                            :href="route('nutritionist.consultations')"
                            class="text-xs font-semibold text-[#0f6b36] hover:underline dark:text-[#3fcb7a]"
                        >
                            Lihat semua chat
                        </Link>
                    </div>

                    <div class="divide-y divide-gray-100 dark:divide-[#262626]">
                        <div
                            v-for="item in antrean_konsultasi"
                            :key="item.id"
                            class="-mx-3 flex items-start justify-between gap-4 rounded-2xl px-3 py-3.5 transition-colors hover:bg-gray-50/80 dark:hover:bg-[#222]"
                        >
                            <div class="flex items-start gap-3">
                                <div
                                    class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-[#0f6b36] dark:bg-[#17321f] dark:text-[#3fcb7a]"
                                >
                                    {{ item.nama.slice(0, 2).toUpperCase() }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-sm font-semibold text-gray-900 dark:text-[#f0efea]"
                                            >{{ item.nama }}</span
                                        >
                                        <span
                                            v-if="item.status === 'menunggu'"
                                            class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-950/60 dark:text-amber-400"
                                        >
                                            Butuh Verifikasi
                                        </span>
                                        <span
                                            v-else
                                            class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-[#17321f] dark:text-[#3fcb7a]"
                                        >
                                            Aktif
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        {{ item.subjudul }}
                                    </div>
                                    <div
                                        class="mt-1 line-clamp-1 max-w-md text-xs text-gray-600 dark:text-gray-300"
                                    >
                                        {{ item.pesan_terakhir }}
                                    </div>
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-col items-end gap-1.5">
                                <span class="text-[11px] font-medium text-gray-400">
                                    {{ item.waktu }}
                                </span>
                                <Link
                                    :href="route('nutritionist.consultations', { tab: item.status, id: item.id })"
                                    class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 shadow-2xs transition-colors hover:bg-gray-50 hover:text-emerald-700 dark:border-[#333] dark:bg-[#222] dark:text-gray-300 dark:hover:bg-[#2a2a2a] dark:hover:text-[#3fcb7a]"
                                >
                                    <span>Detail</span>
                                    <ArrowRight class="h-3 w-3" />
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Jadwal Konsultasi Hari Ini (5 cols) -->
                <div
                    class="rounded-3xl border border-gray-100 bg-white p-6 shadow-xs dark:border-[#262626] dark:bg-[#1a1a1a] lg:col-span-5"
                >
                    <div class="mb-5 flex items-center justify-between">
                        <h2 class="text-base font-bold text-gray-900 dark:text-[#f0efea]">
                            Jadwal Sesi Hari Ini
                        </h2>
                        <span class="text-xs font-medium text-gray-400">
                            {{ jadwal_hari_ini.length }} sesi
                        </span>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="sesi in jadwal_hari_ini"
                            :key="sesi.id"
                            class="rounded-2xl border p-3.5 transition-all"
                            :class="
                                sesi.status === 'Berlangsung'
                                    ? 'border-emerald-300 bg-emerald-50/40 dark:border-emerald-800 dark:bg-emerald-950/30'
                                    : 'border-gray-100 bg-gray-50/50 dark:border-[#262626] dark:bg-[#141414]'
                            "
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-gray-800 dark:text-[#f0efea]">{{
                                    sesi.jam
                                }}</span>
                                <span
                                    class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                    :class="
                                        sesi.status === 'Berlangsung'
                                            ? 'bg-emerald-600 text-white dark:bg-emerald-600'
                                            : 'bg-gray-200 text-gray-700 dark:bg-[#262626] dark:text-gray-300'
                                    "
                                >
                                    {{ sesi.status }}
                                </span>
                            </div>
                            <div
                                class="mt-1.5 text-sm font-semibold text-gray-900 dark:text-[#f0efea]"
                            >
                                {{ sesi.klien_nama }}
                            </div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                {{ sesi.topik }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </NutritionistLayout>
</template>
