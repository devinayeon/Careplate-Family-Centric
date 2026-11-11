<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import UserLayout from '@/Layouts/UserLayout.vue';
import {
    Search,
    UserCheck,
    Clock,
    DollarSign,
    CheckCircle2,
    AlertCircle,
    XCircle,
    MessageSquare,
    ArrowRight,
    Award,
    Calendar,
} from 'lucide-vue-next';

interface AhliGiziItem {
    id: number;
    spesialisasi: string;
    nomor_str: string;
    jam_layanan: string;
    bio: string;
    tarif_konsultasi: number;
    user: {
        id: number;
        name: string;
        email: string;
    };
}

interface KonsultasiSayaItem {
    id: number;
    nomor_konsultasi: string;
    target_tipe: string;
    nama_pasien: string;
    usia_pasien: number;
    topik: string;
    status: 'menunggu' | 'berlangsung' | 'selesai' | 'ditolak';
    alasan_penolakan?: string;
    disetujui_pada?: string;
    selesai_pada?: string;
    updated_at: string;
    created_at: string;
    ahli_gizi: {
        id: number;
        nama: string;
        spesialisasi: string;
        nomor_str: string;
    };
    pesan_terakhir?: {
        pesan: string;
        waktu: string;
        is_meeting: boolean;
    } | null;
}

const props = defineProps<{
    active_tab: string;
    filters: {
        q: string;
        kategori: string;
    };
    kategori_list: string[];
    ahli_gizi_list: {
        data: AhliGiziItem[];
        current_page: number;
        last_page: number;
        next_page_url: string | null;
        prev_page_url: string | null;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
    konsultasi_saya: KonsultasiSayaItem[];
}>();

const currentTab = ref(props.active_tab === 'saya' ? 'saya' : 'semua');
const searchQuery = ref(props.filters.q || '');
const selectedKategori = ref(props.filters.kategori || 'Semua');

// Switch Tab
const switchTab = (tab: 'semua' | 'saya') => {
    currentTab.value = tab;
    router.get(
        route('user.consultation'),
        {
            tab,
            q: searchQuery.value,
            kategori: selectedKategori.value === 'Semua' ? '' : selectedKategori.value,
        },
        { preserveState: true, preserveScroll: true }
    );
};

// Filter & Search
const applyFilter = (kategori: string) => {
    selectedKategori.value = kategori;
    router.get(
        route('user.consultation'),
        {
            tab: currentTab.value,
            q: searchQuery.value,
            kategori: kategori === 'Semua' ? '' : kategori,
        },
        { preserveState: true, preserveScroll: true }
    );
};

const handleSearch = () => {
    router.get(
        route('user.consultation'),
        {
            tab: currentTab.value,
            q: searchQuery.value,
            kategori: selectedKategori.value === 'Semua' ? '' : selectedKategori.value,
        },
        { preserveState: true, preserveScroll: true }
    );
};

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(val);
};

const getInitials = (name: string) => {
    const parts = name.split(' ');
    let init = '';
    for (const p of parts) {
        if (p) init += p[0].toUpperCase();
        if (init.length >= 2) break;
    }
    return init || 'DR';
};
</script>

<template>
    <Head title="Konsultasi Ahli Gizi" />

    <UserLayout>
        <div class="mx-auto max-w-6xl space-y-6 pb-12">
            <!-- Header Section -->
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-[#f0efea] sm:text-3xl">
                        Konsultasi Ahli Gizi
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Dapatkan pendampingan langsung dari ahli gizi tersertifikasi untuk Anda dan keluarga.
                    </p>
                </div>
            </div>

            <!-- Tab Switcher (Semua Ahli Gizi vs Konsultasi Saya) -->
            <div class="flex border-b border-gray-200 text-sm font-bold dark:border-[#262626]">
                <button
                    @click="switchTab('semua')"
                    class="relative pb-3.5 pr-6 transition-colors"
                    :class="
                        currentTab === 'semua'
                            ? 'border-b-2 border-[#0b8043] text-gray-900 dark:border-emerald-500 dark:text-[#f0efea]'
                            : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-200'
                    "
                >
                    Semua Ahli Gizi
                </button>
                <button
                    @click="switchTab('saya')"
                    class="relative pb-3.5 px-6 transition-colors"
                    :class="
                        currentTab === 'saya'
                            ? 'border-b-2 border-[#0b8043] text-gray-900 dark:border-emerald-500 dark:text-[#f0efea]'
                            : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-200'
                    "
                >
                    <span>Konsultasi Saya</span>
                    <span
                        v-if="konsultasi_saya.length > 0"
                        class="ml-2 rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                    >
                        {{ konsultasi_saya.length }}
                    </span>
                </button>
            </div>

            <!-- ============================================== -->
            <!-- TAB 1: SEMUA AHLI GIZI (KATALOG)               -->
            <!-- ============================================== -->
            <div v-if="currentTab === 'semua'" class="space-y-6">
                <!-- Search Bar & Category Chips -->
                <div class="space-y-4">
                    <!-- Search Input -->
                    <div class="relative max-w-md">
                        <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input
                            v-model="searchQuery"
                            @keydown.enter="handleSearch"
                            type="text"
                            placeholder="Cari nama ahli gizi, spesialisasi, atau keluhan..."
                            class="w-full rounded-full border border-gray-200 bg-white py-2.5 pl-10 pr-4 text-xs text-gray-900 shadow-2xs transition-colors placeholder:text-gray-400 focus:border-emerald-600 focus:outline-none dark:border-[#262626] dark:bg-[#1a1a1a] dark:text-[#f0efea]"
                        />
                    </div>

                    <!-- Category Chips -->
                    <div class="flex items-center gap-2 overflow-x-auto pb-1 custom-scrollbar">
                        <button
                            type="button"
                            @click="applyFilter('Semua')"
                            class="rounded-full px-4 py-2 text-xs font-bold transition-all whitespace-nowrap"
                            :class="
                                selectedKategori === 'Semua' || !selectedKategori
                                    ? 'bg-gray-900 text-white shadow-xs dark:bg-emerald-600'
                                    : 'bg-[#f4f3f0] text-gray-700 hover:bg-gray-200 dark:bg-[#262626] dark:text-gray-300 dark:hover:bg-[#333]'
                            "
                        >
                            Semua
                        </button>
                        <button
                            v-for="kat in kategori_list"
                            :key="kat"
                            type="button"
                            @click="applyFilter(kat)"
                            class="rounded-full px-4 py-2 text-xs font-bold transition-all whitespace-nowrap"
                            :class="
                                selectedKategori === kat
                                    ? 'bg-gray-900 text-white shadow-xs dark:bg-emerald-600'
                                    : 'bg-[#f4f3f0] text-gray-700 hover:bg-gray-200 dark:bg-[#262626] dark:text-gray-300 dark:hover:bg-[#333]'
                            "
                        >
                            {{ kat }}
                        </button>
                    </div>
                </div>

                <!-- Nutritionists Grid Cards -->
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-2">
                    <div
                        v-for="ag in ahli_gizi_list.data"
                        :key="ag.id"
                        class="flex flex-col justify-between rounded-3xl border border-gray-100 bg-white p-6 shadow-xs transition-all hover:shadow-md dark:border-[#262626] dark:bg-[#1a1a1a]"
                    >
                        <div>
                            <!-- Header Card: Avatar & Name -->
                            <div class="flex items-start gap-4">
                                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-base font-extrabold text-[#0f6b36] shadow-2xs dark:bg-emerald-950/80 dark:text-emerald-300">
                                    {{ getInitials(ag.user.name) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="truncate text-base font-bold text-gray-900 dark:text-[#f0efea]">
                                        {{ ag.user.name }}
                                    </h3>
                                    <div class="mt-1 flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400">
                                            <Award class="h-3 w-3" />
                                            {{ ag.spesialisasi || 'Gizi Umum' }}
                                        </span>
                                    </div>
                                    <div class="mt-1 text-[11px] text-gray-400">
                                        STR: {{ ag.nomor_str || 'Tersertifikasi' }}
                                    </div>
                                </div>
                            </div>

                            <!-- Bio -->
                            <p class="mt-4 line-clamp-3 text-xs leading-relaxed text-gray-600 dark:text-gray-400">
                                {{ ag.bio || 'Praktisi gizi berpengalaman yang siap memberikan konsultasi dan meal plan personal.' }}
                            </p>

                            <!-- Operational Details -->
                            <div class="mt-4 space-y-1.5 border-t border-gray-100 pt-3 text-xs text-gray-500 dark:border-[#262626] dark:text-gray-400">
                                <div class="flex items-center gap-2">
                                    <Clock class="h-3.5 w-3.5 text-gray-400" />
                                    <span>{{ ag.jam_layanan || 'Senin - Sabtu, 08.00 - 16.00 WIB' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Footer: Fee & Action Button -->
                        <div class="mt-5 flex items-center justify-between border-t border-gray-100 pt-4 dark:border-[#262626]">
                            <div>
                                <span class="text-[10px] text-gray-400 uppercase tracking-wider block">Biaya Sesi</span>
                                <span class="text-base font-extrabold text-gray-900 dark:text-[#f0efea]">
                                    {{ ag.tarif_konsultasi ? formatCurrency(ag.tarif_konsultasi) : 'Gratis / Tersubsidi' }}
                                </span>
                            </div>

                            <Link
                                :href="route('user.consultation.create', ag.id)"
                                class="inline-flex items-center gap-1.5 rounded-full bg-[#0b8043] px-5 py-2.5 text-xs font-bold text-white shadow-xs transition-all hover:bg-[#096a37]"
                            >
                                <span>Konsultasi</span>
                                <ArrowRight class="h-3.5 w-3.5" />
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="ahli_gizi_list.data.length === 0"
                    class="rounded-3xl border border-dashed border-gray-200 p-12 text-center dark:border-[#262626]"
                >
                    <UserCheck class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" />
                    <h3 class="mt-3 text-sm font-bold text-gray-900 dark:text-[#f0efea]">
                        Tidak ada ahli gizi yang sesuai
                    </h3>
                    <p class="mt-1 text-xs text-gray-400">
                        Coba ubah kata kunci pencarian atau pilih kategori spesialisasi lain.
                    </p>
                </div>

                <!-- Pagination -->
                <div
                    v-if="ahli_gizi_list.links && ahli_gizi_list.links.length > 3"
                    class="mt-6 flex items-center justify-center gap-1"
                >
                    <template v-for="(link, idx) in ahli_gizi_list.links" :key="idx">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="flex h-9 min-w-9 items-center justify-center rounded-xl px-3 text-xs font-semibold transition-colors"
                            :class="
                                link.active
                                    ? 'bg-[#0b8043] text-white shadow-xs'
                                    : 'bg-white text-gray-700 hover:bg-gray-100 dark:bg-[#1a1a1a] dark:text-gray-300 dark:hover:bg-[#262626]'
                            "
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="flex h-9 min-w-9 items-center justify-center px-3 text-xs text-gray-400"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>

            <!-- ============================================== -->
            <!-- TAB 2: KONSULTASI SAYA                         -->
            <!-- ============================================== -->
            <div v-else-if="currentTab === 'saya'" class="space-y-4">
                <div v-if="konsultasi_saya.length === 0" class="rounded-3xl border border-dashed border-gray-200 p-12 text-center dark:border-[#262626]">
                    <MessageSquare class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" />
                    <h3 class="mt-3 text-sm font-bold text-gray-900 dark:text-[#f0efea]">
                        Belum ada riwayat konsultasi
                    </h3>
                    <p class="mt-1 text-xs text-gray-400">
                        Anda belum mengajukan konsultasi. Pilih dokter dari tab "Semua Ahli Gizi" untuk memulai.
                    </p>
                    <button
                        @click="switchTab('semua')"
                        class="mt-4 rounded-full bg-[#0b8043] px-5 py-2.5 text-xs font-bold text-white shadow-xs transition-colors hover:bg-[#096a37]"
                    >
                        Lihat Ahli Gizi
                    </button>
                </div>

                <div
                    v-for="kns in konsultasi_saya"
                    :key="kns.id"
                    class="rounded-3xl border border-gray-100 bg-white p-5 shadow-xs transition-all hover:shadow-md dark:border-[#262626] dark:bg-[#1a1a1a]"
                >
                    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                        <div class="flex items-start gap-3.5">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-xs font-bold text-[#0f6b36] dark:bg-emerald-950 dark:text-emerald-300">
                                {{ getInitials(kns.ahli_gizi.nama) }}
                            </div>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-bold text-gray-900 dark:text-[#f0efea]">
                                        {{ kns.ahli_gizi.nama }}
                                    </h3>
                                    <!-- Status Badges -->
                                    <span
                                        v-if="kns.status === 'menunggu'"
                                        class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-bold text-amber-800 dark:bg-amber-950/60 dark:text-amber-400"
                                    >
                                        <Clock class="h-3 w-3" />
                                        Menunggu Persetujuan
                                    </span>
                                    <span
                                        v-else-if="kns.status === 'berlangsung'"
                                        class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400"
                                    >
                                        <CheckCircle2 class="h-3 w-3" />
                                        Berlangsung
                                    </span>
                                    <span
                                        v-else-if="kns.status === 'selesai'"
                                        class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-bold text-gray-700 dark:bg-[#262626] dark:text-gray-300"
                                    >
                                        Selesai
                                    </span>
                                    <span
                                        v-else-if="kns.status === 'ditolak'"
                                        class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-[11px] font-bold text-red-800 dark:bg-red-950/60 dark:text-red-400"
                                    >
                                        <XCircle class="h-3 w-3" />
                                        Ditolak
                                    </span>
                                </div>

                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Pasien: <strong>{{ kns.nama_pasien }}</strong> ({{ kns.usia_pasien }} tahun) • Topik: {{ kns.topik }}
                                </div>

                                <!-- Pesan Terakhir -->
                                <div v-if="kns.pesan_terakhir" class="mt-2 text-xs text-gray-600 dark:text-gray-300">
                                    <span class="text-gray-400">Pesan terakhir: </span>
                                    <span class="italic">"{{ kns.pesan_terakhir.pesan }}"</span>
                                    <span class="ml-1 text-[10px] text-gray-400">({{ kns.pesan_terakhir.waktu }})</span>
                                </div>

                                <!-- Alasan Penolakan Jika Ditolak -->
                                <div
                                    v-if="kns.status === 'ditolak' && kns.alasan_penolakan"
                                    class="mt-2 rounded-xl border border-red-200 bg-red-50 p-2.5 text-xs text-red-800 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300"
                                >
                                    <strong>Alasan Penolakan:</strong> {{ kns.alasan_penolakan }}
                                </div>
                            </div>
                        </div>

                        <!-- Action Button -->
                        <div class="flex items-center gap-2 self-end sm:self-center">
                            <Link
                                :href="route('user.consultation.show', kns.id)"
                                class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-xs font-bold transition-all shadow-xs"
                                :class="
                                    kns.status === 'berlangsung'
                                        ? 'bg-[#0b8043] text-white hover:bg-[#096a37]'
                                        : 'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-[#333] dark:bg-[#222] dark:text-gray-200'
                                "
                            >
                                <MessageSquare class="h-3.5 w-3.5" />
                                <span>{{ kns.status === 'berlangsung' ? 'Lanjutkan Chat' : 'Buka Detail' }}</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
