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
                        class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl"
                    >
                        Dashboard Ahli Gizi
                    </h1>
                    <p class="mt-1 text-sm text-gray-500">
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
                    class="rounded-3xl border border-gray-100 bg-white p-5 shadow-xs"
                >
                    <div
                        class="mb-3 flex items-center gap-2 text-xs font-medium text-gray-500"
                    >
                        <Users class="h-4 w-4 text-gray-400" />
                        <span>Klien Aktif</span>
                    </div>
                    <div
                        class="text-3xl font-extrabold tracking-tight text-gray-900"
                    >
                        {{ stats.klien_aktif }}
                    </div>
                    <div class="mt-2 text-[11px] font-medium text-emerald-600">
                        Dalam pemantauan rutin
                    </div>
                </div>

                <div
                    class="rounded-3xl border border-gray-100 bg-white p-5 shadow-xs"
                >
                    <div
                        class="mb-3 flex items-center gap-2 text-xs font-medium text-gray-500"
                    >
                        <Calendar class="h-4 w-4 text-gray-400" />
                        <span>Konsultasi Hari Ini</span>
                    </div>
                    <div
                        class="text-3xl font-extrabold tracking-tight text-gray-900"
                    >
                        {{ stats.konsultasi_hari_ini }}
                    </div>
                    <div class="mt-2 text-[11px] font-medium text-gray-400">
                        3 chat • 2 video meeting
                    </div>
                </div>

                <div
                    class="rounded-3xl border border-gray-100 bg-white p-5 shadow-xs"
                >
                    <div
                        class="mb-3 flex items-center gap-2 text-xs font-medium text-gray-500"
                    >
                        <Clock class="h-4 w-4 text-amber-500" />
                        <span>Menunggu Respon</span>
                    </div>
                    <div
                        class="text-3xl font-extrabold tracking-tight text-amber-600"
                    >
                        {{ stats.menunggu_respon }}
                    </div>
                    <div class="mt-2 text-[11px] font-medium text-amber-600">
                        Perlu balasan &gt; 1 jam
                    </div>
                </div>

                <div
                    class="rounded-3xl border border-gray-100 bg-white p-5 shadow-xs"
                >
                    <div
                        class="mb-3 flex items-center gap-2 text-xs font-medium text-gray-500"
                    >
                        <Star class="h-4 w-4 fill-current text-yellow-400" />
                        <span>Rating Kepuasan</span>
                    </div>
                    <div
                        class="text-3xl font-extrabold tracking-tight text-gray-900"
                    >
                        {{ stats.rating_kepuasan }}
                    </div>
                    <div class="mt-2 text-[11px] font-medium text-gray-400">
                        Dari 89 ulasan klien
                    </div>
                </div>
            </div>

            <!-- 3. Bottom Grid: Antrean Konsultasi & Jadwal Hari Ini -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                <!-- Antrean Konsultasi Masuk (7 cols) -->
                <div
                    class="rounded-3xl border border-gray-100 bg-white p-6 shadow-xs lg:col-span-7"
                >
                    <div class="mb-5 flex items-center justify-between">
                        <h2 class="text-base font-bold text-gray-900">
                            Pesan Konsultasi Masuk
                        </h2>
                        <Link
                            :href="route('nutritionist.consultations')"
                            class="text-xs font-semibold text-[#0f6b36] hover:underline"
                        >
                            Lihat semua chat
                        </Link>
                    </div>

                    <div class="divide-y divide-gray-100">
                        <Link
                            v-for="item in antrean_konsultasi"
                            :key="item.id"
                            :href="route('nutritionist.consultations')"
                            class="-mx-3 flex items-start justify-between gap-4 rounded-2xl px-3 py-3.5 transition-colors hover:bg-gray-50/80"
                        >
                            <div class="flex items-start gap-3">
                                <div
                                    class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-[#0f6b36]"
                                >
                                    {{ item.nama.slice(0, 2).toUpperCase() }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-sm font-semibold text-gray-900"
                                            >{{ item.nama }}</span
                                        >
                                        <span
                                            v-if="item.unread"
                                            class="h-2 w-2 rounded-full bg-orange-500"
                                        ></span>
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        {{ item.subjudul }}
                                    </div>
                                    <div
                                        class="mt-1 line-clamp-1 max-w-md text-xs text-gray-600"
                                    >
                                        {{ item.pesan_terakhir }}
                                    </div>
                                </div>
                            </div>

                            <div
                                class="shrink-0 text-[11px] font-medium text-gray-400"
                            >
                                {{ item.waktu }}
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- Jadwal Konsultasi Hari Ini (5 cols) -->
                <div
                    class="rounded-3xl border border-gray-100 bg-white p-6 shadow-xs lg:col-span-5"
                >
                    <div class="mb-5 flex items-center justify-between">
                        <h2 class="text-base font-bold text-gray-900">
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
                                    ? 'border-emerald-300 bg-emerald-50/40'
                                    : 'border-gray-100 bg-gray-50/50'
                            "
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-gray-800">{{
                                    sesi.jam
                                }}</span>
                                <span
                                    class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                    :class="
                                        sesi.status === 'Berlangsung'
                                            ? 'bg-emerald-600 text-white'
                                            : 'bg-gray-200 text-gray-700'
                                    "
                                >
                                    {{ sesi.status }}
                                </span>
                            </div>
                            <div
                                class="mt-1.5 text-sm font-semibold text-gray-900"
                            >
                                {{ sesi.klien_nama }}
                            </div>
                            <div class="mt-0.5 text-xs text-gray-500">
                                {{ sesi.topik }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </NutritionistLayout>
</template>
