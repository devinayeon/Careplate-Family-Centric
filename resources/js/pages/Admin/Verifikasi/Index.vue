<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ChipFilter from '@/Components/UI/ChipFilter.vue';
import SearchBar from '@/Components/UI/SearchBar.vue';
import FoodStatusBadge from '@/Components/UI/FoodStatusBadge.vue';
import AiFoodChip from '@/Components/Ai/AiFoodChip.vue';
import FoodFormDrawer, { type FoodFormData } from '@/Components/Admin/FoodFormDrawer.vue';
import RejectSubmissionModal from '@/Components/Admin/RejectSubmissionModal.vue';
import {
    ChevronRight,
    CheckCircle2,
    AlertTriangle,
    X,
    Pencil,
    Check,
    Database,
    Inbox,
    ChevronLeft,
    Clock,
    User as UserIcon,
    AlertCircle
} from 'lucide-vue-next';

export interface SubmissionItem {
    id: number;
    nama: string;
    kuantitas: number;
    satuan: string;
    berat_per_satuan_gram?: number | null;
    ai_status?: { state: string; label: string } | null;
    takaran_saji: string;
    kategori: string;
    kategori_makanan_id: number;
    energi: number;
    protein: number;
    karbohidrat: number;
    lemak: number;
    gula: number;
    natrium: number;
    sumber: string;
    status: 'terverifikasi' | 'menunggu' | 'ditolak';
    pengaju: string;
    foto_makanan_url: string | null;
    alasan_penolakan: string | null;
    waktu_pengajuan: string;
    tanggal_lengkap: string;
}

export interface AtwaterCheckData {
    protein: number;
    karbohidrat: number;
    lemak: number;
    energi_hitung: number;
    energi_input: number;
    persen_selisih: number;
    formula_text: string;
}

export interface SimilarFoodData {
    id: number;
    nama: string;
    energi: number;
    takaran: string;
    text: string;
}

const props = defineProps<{
    submissions: {
        data: SubmissionItem[];
        total: number;
        from: number | null;
        to: number | null;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    selectedItem: SubmissionItem | null;
    atwaterCheck: AtwaterCheckData | null;
    similarFood: SimilarFoodData | null;
    categories: Array<{ id: number; kategori: string }>;
    filters: {
        status: 'menunggu' | 'terverifikasi' | 'ditolak';
        search: string;
        selected: number | null;
        page?: number;
    };
    counts: {
        menunggu: number;
        terverifikasi: number;
        ditolak: number;
    };
}>();

const activeStatusFilter = ref(props.filters.status || 'menunggu');
const searchQuery = ref(props.filters.search || '');

// Drawer state for "Ubah lalu Verifikasi"
const isDrawerOpen = ref(false);
const foodToEdit = ref<FoodFormData | null>(null);

// Reject modal state
const isRejectModalOpen = ref(false);
const isSubmittingReject = ref(false);

const applyStatusFilter = (statusKey: 'menunggu' | 'terverifikasi' | 'ditolak') => {
    activeStatusFilter.value = statusKey;
    router.get(
        route('admin.verifications'),
        {
            status: statusKey,
            search: searchQuery.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

const handleSearch = () => {
    router.get(
        route('admin.verifications'),
        {
            status: activeStatusFilter.value,
            search: searchQuery.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

const selectItem = (item: SubmissionItem) => {
    router.get(
        route('admin.verifications'),
        {
            status: activeStatusFilter.value,
            search: searchQuery.value || undefined,
            selected: item.id,
            page: props.submissions.current_page > 1 ? props.submissions.current_page : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

const handlePageChange = (url: string | null) => {
    if (!url) return;
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
    });
};

// 1. Direct Approve (Verifikasi)
const approveSubmission = () => {
    if (!props.selectedItem) return;
    router.post(
        route('admin.verifications.approve', props.selectedItem.id),
        {},
        {
            preserveScroll: true,
        }
    );
};

// 2. Open Drawer for "Ubah lalu Verifikasi"
const openEditDrawer = () => {
    if (!props.selectedItem) return;
    foodToEdit.value = {
        id: props.selectedItem.id,
        nama: props.selectedItem.nama,
        kategori_makanan_id: props.selectedItem.kategori_makanan_id,
        kuantitas: props.selectedItem.kuantitas,
        satuan: props.selectedItem.satuan,
        berat_per_satuan_gram: props.selectedItem.berat_per_satuan_gram,
        energi: props.selectedItem.energi,
        protein: props.selectedItem.protein,
        karbohidrat: props.selectedItem.karbohidrat,
        lemak: props.selectedItem.lemak,
        gula: props.selectedItem.gula,
        natrium: props.selectedItem.natrium,
        sumber: props.selectedItem.sumber,
        status: 'terverifikasi',
        foto_makanan_url: props.selectedItem.foto_makanan_url,
    };
    isDrawerOpen.value = true;
};

// 3. Reject submission
const openRejectModal = () => {
    isRejectModalOpen.value = true;
};

const confirmReject = (reason: string) => {
    if (!props.selectedItem) return;
    isSubmittingReject.value = true;
    router.post(
        route('admin.verifications.reject', props.selectedItem.id),
        {
            alasan_penolakan: reason,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                isRejectModalOpen.value = false;
            },
            onFinish: () => {
                isSubmittingReject.value = false;
            },
        }
    );
};
</script>

<template>
    <AdminLayout title="Verifikasi Pengajuan">
        <Head title="Verifikasi Pengajuan Makanan - CarePlate Admin" />

        <div class="space-y-6 max-w-7xl mx-auto pb-12">
            <!-- 1. Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold text-[#191919] dark:text-[#f0efea] tracking-tight">
                        Verifikasi pengajuan
                    </h1>
                    <p class="text-sm text-[#5e5e5e] dark:text-[#a3a3a3] mt-1">
                        {{ counts.menunggu }} menunggu. Urut dari yang terlama.
                    </p>
                </div>
            </div>

            <!-- 2. Filter Bar: Status Tabs (Tanpa 'Semua') & Search Bar -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <!-- Status Chips (Default aktif: 'menunggu') -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
                    <ChipFilter
                        label="Menunggu"
                        :count="counts.menunggu"
                        :active="activeStatusFilter === 'menunggu'"
                        @click="applyStatusFilter('menunggu')"
                    />
                    <ChipFilter
                        label="Terverifikasi"
                        :count="counts.terverifikasi"
                        :active="activeStatusFilter === 'terverifikasi'"
                        @click="applyStatusFilter('terverifikasi')"
                    />
                    <ChipFilter
                        label="Ditolak"
                        :count="counts.ditolak"
                        :active="activeStatusFilter === 'ditolak'"
                        @click="applyStatusFilter('ditolak')"
                    />
                </div>

                <!-- Search Input -->
                <div class="w-full md:w-80">
                    <SearchBar
                        v-model="searchQuery"
                        placeholder="Cari pengajuan makanan..."
                        @search="handleSearch"
                        @clear="handleSearch"
                    />
                </div>
            </div>

            <!-- 3. Master-Detail Layout Grid (Figma Node 22-5458) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- Left Column (Col 4): Antrean Pengajuan List -->
                <div class="lg:col-span-5 xl:col-span-4 bg-white dark:bg-[#1c1c1c] border border-[#eceae5] dark:border-[#2e2e2e] rounded-2xl overflow-hidden shadow-xs flex flex-col">
                    <!-- List Header -->
                    <div class="px-5 py-3.5 bg-[#f8f9fa] dark:bg-[#262626] border-b border-[#eceae5] dark:border-[#2e2e2e] flex items-center justify-between">
                        <span class="text-xs font-bold text-[#191919] dark:text-[#f0efea] uppercase tracking-wider">
                            Daftar Pengajuan ({{ submissions.total }})
                        </span>
                        <span class="text-[11px] font-semibold text-[#5e5e5e] dark:text-[#a3a3a3]">
                            Urut terlama
                        </span>
                    </div>

                    <!-- Items List -->
                    <div v-if="submissions.data.length > 0" class="divide-y divide-[#eceae5] dark:divide-[#2e2e2e]">
                        <button
                            v-for="item in submissions.data"
                            :key="item.id"
                            type="button"
                            @click="selectItem(item)"
                            :class="[
                                'w-full px-5 py-4 flex items-center justify-between text-left transition cursor-pointer',
                                selectedItem?.id === item.id
                                    ? 'bg-[#e4f6ea] dark:bg-[#17321f] hover:bg-[#d8f2df] dark:hover:bg-[#1f422a]'
                                    : 'bg-white dark:bg-[#1c1c1c] hover:bg-[#faf9f7] dark:hover:bg-[#262626]'
                            ]"
                        >
                            <div class="min-w-0 pr-3">
                                <h4 class="font-bold text-sm text-[#191919] dark:text-[#f0efea] truncate leading-snug">
                                    {{ item.nama }}
                                </h4>
                                <p class="text-xs text-[#5e5e5e] dark:text-[#a3a3a3] font-semibold mt-1 flex items-center gap-1.5 truncate">
                                    <span>{{ item.pengaju }}</span>
                                    <span>•</span>
                                    <span>{{ item.waktu_pengajuan }}</span>
                                </p>
                            </div>

                            <ChevronRight
                                :class="[
                                    'w-4 h-4 shrink-0 transition',
                                    selectedItem?.id === item.id ? 'text-[#0b8043] dark:text-[#3fcb7a] translate-x-0.5' : 'text-gray-400 dark:text-[#737373]'
                                ]"
                            />
                        </button>
                    </div>

                    <!-- Empty List State -->
                    <div v-else class="p-12 text-center">
                        <Inbox class="w-10 h-10 text-gray-300 dark:text-[#3d3d3d] mx-auto mb-2" />
                        <p class="text-sm font-bold text-gray-800 dark:text-[#f0efea]">Tidak ada pengajuan</p>
                        <p class="text-xs text-gray-400 dark:text-[#a3a3a3] mt-1">
                            Tidak ada data dengan status {{ activeStatusFilter }}.
                        </p>
                    </div>

                    <!-- Pagination Footer (Left Panel) -->
                    <div
                        v-if="submissions.total > 0"
                        class="px-5 py-3.5 bg-[#f8f9fa] dark:bg-[#262626] border-t border-[#eceae5] dark:border-[#2e2e2e] flex items-center justify-between text-xs text-[#5e5e5e] dark:text-[#a3a3a3] font-medium"
                    >
                        <span>
                            {{ submissions.from || 0 }} - {{ submissions.to || 0 }} dari {{ submissions.total }}
                        </span>
                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                @click="handlePageChange(submissions.prev_page_url)"
                                :disabled="!submissions.prev_page_url"
                                class="p-1.5 rounded-lg border border-[#eceae5] dark:border-[#3d3d3d] bg-white dark:bg-[#1c1c1c] text-gray-700 dark:text-[#f0efea] hover:bg-gray-100 dark:hover:bg-[#3d3d3d] disabled:opacity-40 transition cursor-pointer"
                                title="Sebelumnya"
                            >
                                <ChevronLeft class="w-4 h-4" />
                            </button>
                            <span v-if="submissions.last_page > 1" class="px-1.5 text-xs text-gray-500 dark:text-[#a3a3a3] font-semibold select-none">
                                {{ submissions.current_page }} / {{ submissions.last_page }}
                            </span>
                            <button
                                type="button"
                                @click="handlePageChange(submissions.next_page_url)"
                                :disabled="!submissions.next_page_url"
                                class="p-1.5 rounded-lg border border-[#eceae5] dark:border-[#3d3d3d] bg-white dark:bg-[#1c1c1c] text-gray-700 dark:text-[#f0efea] hover:bg-gray-100 dark:hover:bg-[#3d3d3d] disabled:opacity-40 transition cursor-pointer"
                                title="Berikutnya"
                            >
                                <ChevronRight class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right Column (Col 8): Detail Review & Actions Panel -->
                <div class="lg:col-span-7 xl:col-span-8 bg-white dark:bg-[#1c1c1c] border border-[#eceae5] dark:border-[#2e2e2e] rounded-2xl p-6 sm:p-8 shadow-xs space-y-6">
                    <template v-if="selectedItem">
                        <!-- Detail Header -->
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 border-b border-[#eceae5] dark:border-[#2e2e2e] pb-5">
                            <div>
                                <h2 class="text-2xl sm:text-3xl font-extrabold text-[#191919] dark:text-[#f0efea] tracking-tight">
                                    {{ selectedItem.nama }}
                                </h2>
                                <p class="text-xs sm:text-sm text-[#5e5e5e] dark:text-[#a3a3a3] mt-1.5 font-medium leading-relaxed">
                                    Diajukan <strong class="text-[#191919] dark:text-[#f0efea]">{{ selectedItem.pengaju }}</strong> • {{ selectedItem.tanggal_lengkap }} • Sumber: <span class="text-gray-800 dark:text-[#f0efea]">{{ selectedItem.sumber }}</span>
                                </p>
                            </div>

                            <FoodStatusBadge :status="selectedItem.status" />
                            <AiFoodChip v-if="selectedItem.ai_status" :status="selectedItem.ai_status" />
                        </div>

                        <!-- 8 Nutrition Metric Cards Grid (Figma Node 22-5552) -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <!-- Card 1: Takaran Saji -->
                            <div class="bg-[#f4f3f0] dark:bg-[#262626] p-3.5 rounded-xl space-y-1">
                                <span class="block text-[11px] font-semibold text-[#5e5e5e] dark:text-[#a3a3a3] uppercase tracking-wider">
                                    Takaran saji
                                </span>
                                <span class="block text-base font-bold text-[#191919] dark:text-[#f0efea]">
                                    {{ selectedItem.takaran_saji }}
                                </span>
                            </div>

                            <!-- Card 2: Energi -->
                            <div class="bg-[#f4f3f0] dark:bg-[#262626] p-3.5 rounded-xl space-y-1">
                                <span class="block text-[11px] font-semibold text-[#5e5e5e] dark:text-[#a3a3a3] uppercase tracking-wider">
                                    Energi
                                </span>
                                <span class="block text-base font-bold text-[#191919] dark:text-[#f0efea]">
                                    {{ Math.round(selectedItem.energi) }} kkal
                                </span>
                            </div>

                            <!-- Card 3: Protein -->
                            <div class="bg-[#f4f3f0] dark:bg-[#262626] p-3.5 rounded-xl space-y-1">
                                <span class="block text-[11px] font-semibold text-[#5e5e5e] dark:text-[#a3a3a3] uppercase tracking-wider">
                                    Protein
                                </span>
                                <span class="block text-base font-bold text-[#191919] dark:text-[#f0efea]">
                                    {{ Math.round(selectedItem.protein) }} g
                                </span>
                            </div>

                            <!-- Card 4: Karbohidrat -->
                            <div class="bg-[#f4f3f0] dark:bg-[#262626] p-3.5 rounded-xl space-y-1">
                                <span class="block text-[11px] font-semibold text-[#5e5e5e] dark:text-[#a3a3a3] uppercase tracking-wider">
                                    Karbohidrat
                                </span>
                                <span class="block text-base font-bold text-[#191919] dark:text-[#f0efea]">
                                    {{ Math.round(selectedItem.karbohidrat) }} g
                                </span>
                            </div>

                            <!-- Card 5: Lemak -->
                            <div class="bg-[#f4f3f0] dark:bg-[#262626] p-3.5 rounded-xl space-y-1">
                                <span class="block text-[11px] font-semibold text-[#5e5e5e] dark:text-[#a3a3a3] uppercase tracking-wider">
                                    Lemak
                                </span>
                                <span class="block text-base font-bold text-[#191919] dark:text-[#f0efea]">
                                    {{ Math.round(selectedItem.lemak) }} g
                                </span>
                            </div>

                            <!-- Card 6: Gula -->
                            <div class="bg-[#f4f3f0] dark:bg-[#262626] p-3.5 rounded-xl space-y-1">
                                <span class="block text-[11px] font-semibold text-[#5e5e5e] dark:text-[#a3a3a3] uppercase tracking-wider">
                                    Gula
                                </span>
                                <span class="block text-base font-bold text-[#191919] dark:text-[#f0efea]">
                                    {{ selectedItem.gula }} g
                                </span>
                            </div>

                            <!-- Card 7: Natrium -->
                            <div class="bg-[#f4f3f0] dark:bg-[#262626] p-3.5 rounded-xl space-y-1">
                                <span class="block text-[11px] font-semibold text-[#5e5e5e] dark:text-[#a3a3a3] uppercase tracking-wider">
                                    Natrium
                                </span>
                                <span class="block text-base font-bold text-[#191919] dark:text-[#f0efea]">
                                    {{ selectedItem.natrium }} mg
                                </span>
                            </div>

                            <!-- Card 8: Kategori -->
                            <div class="bg-[#f4f3f0] dark:bg-[#262626] p-3.5 rounded-xl space-y-1">
                                <span class="block text-[11px] font-semibold text-[#5e5e5e] dark:text-[#a3a3a3] uppercase tracking-wider">
                                    Kategori
                                </span>
                                <span class="block text-base font-bold text-[#191919] dark:text-[#f0efea] truncate">
                                    {{ selectedItem.kategori }}
                                </span>
                            </div>
                        </div>

                        <!-- Cek Energi Atwater Box (Figma Node 22-5580) -->
                        <div
                            v-if="atwaterCheck"
                            class="bg-[#e6f6ec] dark:bg-[#17321f] rounded-xl p-4 flex items-start sm:items-center gap-3 text-xs sm:text-sm text-[#0b7a46] dark:text-[#3fcb7a] font-medium leading-relaxed border border-[#0b7a46]/20 dark:border-[#3fcb7a]/20"
                        >
                            <CheckCircle2 class="w-5 h-5 shrink-0 mt-0.5 sm:mt-0" />
                            <span>
                                <strong>Cek energi:</strong> {{ atwaterCheck.formula_text }}
                            </span>
                        </div>

                        <!-- Similar Food Warning Box (Figma Node 22-5585) -->
                        <div
                            v-if="similarFood"
                            class="bg-[#fff1d6] dark:bg-[#3a1e14] rounded-xl p-4 flex items-start sm:items-center justify-between gap-3 text-xs sm:text-sm text-[#9a4a00] dark:text-[#fca052] font-medium border border-[#9a4a00]/20 dark:border-[#fca052]/20"
                        >
                            <div class="flex items-start sm:items-center gap-3">
                                <AlertTriangle class="w-5 h-5 shrink-0 text-[#9a4a00] dark:text-[#fca052] mt-0.5 sm:mt-0" />
                                <span>{{ similarFood.text }}</span>
                            </div>
                        </div>

                        <!-- Rejection Note Alert if already rejected -->
                        <div
                            v-if="selectedItem.status === 'ditolak' && selectedItem.alasan_penolakan"
                            class="bg-[#fde8e6] dark:bg-red-950/40 rounded-xl p-4 flex items-start gap-3 text-xs sm:text-sm text-[#b42318] dark:text-red-400 border border-[#b42318]/20 dark:border-red-900/40"
                        >
                            <AlertCircle class="w-5 h-5 shrink-0 mt-0.5" />
                            <div>
                                <span class="font-bold block">Alasan Penolakan:</span>
                                <span class="mt-0.5 block leading-relaxed">{{ selectedItem.alasan_penolakan }}</span>
                            </div>
                        </div>

                        <!-- Action Buttons Section -->
                        <div class="pt-4 border-t border-[#eceae5] dark:border-[#2e2e2e] flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-3">
                            <!-- Buttons when status is 'menunggu' -->
                            <template v-if="selectedItem.status === 'menunggu'">
                                <!-- 1. Tolak Button -->
                                <button
                                    type="button"
                                    @click="openRejectModal"
                                    class="px-5 py-2.5 rounded-full border-[1.5px] border-[#b42318] dark:border-red-500 text-[#b42318] dark:text-red-400 text-xs font-bold hover:bg-red-50 dark:hover:bg-red-950/40 transition flex items-center justify-center gap-1.5 cursor-pointer shadow-2xs"
                                >
                                    <X class="w-4 h-4" />
                                    <span>Tolak</span>
                                </button>

                                <!-- 2. Ubah lalu Verifikasi Button -->
                                <button
                                    type="button"
                                    @click="openEditDrawer"
                                    class="px-5 py-2.5 rounded-full border-[1.5px] border-[#191919] dark:border-[#3d3d3d] text-[#191919] dark:text-[#f0efea] text-xs font-bold hover:bg-gray-100 dark:hover:bg-[#262626] transition flex items-center justify-center gap-1.5 cursor-pointer shadow-2xs"
                                >
                                    <Pencil class="w-3.5 h-3.5 text-gray-700 dark:text-[#a3a3a3]" />
                                    <span>Ubah lalu Verifikasi</span>
                                </button>

                                <!-- 3. Direct Approve (Verifikasi) -->
                                <button
                                    type="button"
                                    @click="approveSubmission"
                                    class="px-6 py-2.5 rounded-full bg-[#0b8043] dark:bg-[#3fcb7a] text-white dark:text-[#121212] text-xs font-bold hover:bg-[#086637] dark:hover:bg-[#34aa66] shadow-xs transition flex items-center justify-center gap-1.5 cursor-pointer"
                                >
                                    <Check class="w-4 h-4" />
                                    <span>Verifikasi</span>
                                </button>
                            </template>

                            <!-- Buttons when status is 'ditolak' -->
                            <template v-else-if="selectedItem.status === 'ditolak'">
                                <button
                                    type="button"
                                    @click="openEditDrawer"
                                    class="px-5 py-2.5 rounded-full border-[1.5px] border-[#191919] dark:border-[#3d3d3d] text-[#191919] dark:text-[#f0efea] text-xs font-bold hover:bg-gray-100 dark:hover:bg-[#262626] transition flex items-center justify-center gap-1.5 cursor-pointer"
                                >
                                    <Pencil class="w-3.5 h-3.5" />
                                    <span>Perbaiki & Verifikasi Ulang</span>
                                </button>
                            </template>

                            <!-- State when status is 'terverifikasi' -->
                            <template v-else>
                                <span class="text-xs font-semibold text-[#0b7a46] dark:text-[#3fcb7a] flex items-center gap-1.5 bg-[#e6f6ec] dark:bg-[#17321f] px-4 py-2 rounded-full">
                                    <CheckCircle2 class="w-4 h-4" />
                                    <span>Makanan ini telah diverifikasi dan aktif di katalog.</span>
                                </span>
                            </template>
                        </div>

                        <!-- Database Sync Info Note (Figma Node 22-5606) -->
                        <div class="pt-2 flex items-center gap-2 text-xs text-[#5e5e5e] dark:text-[#a3a3a3] font-medium border-t border-dashed border-[#eceae5] dark:border-[#2e2e2e]">
                            <Database class="w-4 h-4 text-gray-400 dark:text-[#737373] shrink-0" />
                            <span>Setelah diverifikasi, Chat Gizi bisa mengutip makanan ini dalam sekitar 2 menit.</span>
                        </div>
                    </template>

                    <!-- Empty Selected State -->
                    <div v-else class="py-20 text-center space-y-2">
                        <Inbox class="w-12 h-12 text-gray-300 dark:text-[#3d3d3d] mx-auto" />
                        <h3 class="text-base font-bold text-gray-800 dark:text-[#f0efea]">
                            Pilih Pengajuan Makanan
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-[#a3a3a3] max-w-sm mx-auto">
                            Klik salah satu makanan di antrean sebelah kiri untuk meninjau detail nutrisi, cek Atwater, dan melakukan verifikasi.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 1. Drawer for "Ubah lalu Verifikasi" -->
        <FoodFormDrawer
            :show="isDrawerOpen"
            :food-to-edit="foodToEdit"
            :categories="categories"
            @close="isDrawerOpen = false"
            @success="isDrawerOpen = false"
        />

        <!-- 2. Rejection Popup Modal (Figma Node 36-10381) -->
        <RejectSubmissionModal
            :show="isRejectModalOpen"
            :food-name="selectedItem?.nama || 'Makanan'"
            :submitter-name="selectedItem?.pengaju || 'Pengguna'"
            :similar-food-name="similarFood?.nama"
            :is-submitting="isSubmittingReject"
            @close="isRejectModalOpen = false"
            @confirm="confirmReject"
        />
    </AdminLayout>
</template>

