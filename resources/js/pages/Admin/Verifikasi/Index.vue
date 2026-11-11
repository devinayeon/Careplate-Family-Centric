<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ChipFilter from '@/Components/UI/ChipFilter.vue';
import SearchBar from '@/Components/UI/SearchBar.vue';
import FoodStatusBadge from '@/Components/UI/FoodStatusBadge.vue';
import AiFoodChip from '@/Components/Ai/AiFoodChip.vue';
import FoodFormDrawer, {
    type FoodFormData,
} from '@/Components/Admin/FoodFormDrawer.vue';
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
    AlertCircle,
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

const applyStatusFilter = (
    statusKey: 'menunggu' | 'terverifikasi' | 'ditolak',
) => {
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
        },
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
        },
    );
};

const selectItem = (item: SubmissionItem) => {
    router.get(
        route('admin.verifications'),
        {
            status: activeStatusFilter.value,
            search: searchQuery.value || undefined,
            selected: item.id,
            page:
                props.submissions.current_page > 1
                    ? props.submissions.current_page
                    : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
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
        },
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
        },
    );
};
</script>

<template>
    <AdminLayout title="Verifikasi Pengajuan">
        <Head title="Verifikasi Pengajuan Makanan - CarePlate Admin" />

        <div class="mx-auto max-w-7xl space-y-6 pb-12">
            <!-- 1. Header Section -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1
                        class="text-3xl font-extrabold tracking-tight text-[#191919] sm:text-4xl dark:text-[#f0efea]"
                    >
                        Verifikasi pengajuan
                    </h1>
                    <p class="mt-1 text-sm text-[#5e5e5e] dark:text-[#a3a3a3]">
                        {{ counts.menunggu }} menunggu. Urut dari yang terlama.
                    </p>
                </div>
            </div>

            <!-- 2. Filter Bar: Status Tabs (Tanpa 'Semua') & Search Bar -->
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <!-- Status Chips (Default aktif: 'menunggu') -->
                <div
                    class="flex scrollbar-none items-center gap-2 overflow-x-auto pb-1"
                >
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
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
                <!-- Left Column (Col 4): Antrean Pengajuan List -->
                <div
                    class="flex flex-col overflow-hidden rounded-2xl border border-[#eceae5] bg-white shadow-xs lg:col-span-5 xl:col-span-4 dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
                >
                    <!-- List Header -->
                    <div
                        class="flex items-center justify-between border-b border-[#eceae5] bg-[#f8f9fa] px-5 py-3.5 dark:border-[#2e2e2e] dark:bg-[#262626]"
                    >
                        <span
                            class="text-xs font-bold tracking-wider text-[#191919] uppercase dark:text-[#f0efea]"
                        >
                            Daftar Pengajuan ({{ submissions.total }})
                        </span>
                        <span
                            class="text-[11px] font-semibold text-[#5e5e5e] dark:text-[#a3a3a3]"
                        >
                            Urut terlama
                        </span>
                    </div>

                    <!-- Items List -->
                    <div
                        v-if="submissions.data.length > 0"
                        class="divide-y divide-[#eceae5] dark:divide-[#2e2e2e]"
                    >
                        <button
                            v-for="item in submissions.data"
                            :key="item.id"
                            type="button"
                            @click="selectItem(item)"
                            :class="[
                                'flex w-full cursor-pointer items-center justify-between px-5 py-4 text-left transition',
                                selectedItem?.id === item.id
                                    ? 'bg-[#e4f6ea] hover:bg-[#d8f2df] dark:bg-[#17321f] dark:hover:bg-[#1f422a]'
                                    : 'bg-white hover:bg-[#faf9f7] dark:bg-[#1c1c1c] dark:hover:bg-[#262626]',
                            ]"
                        >
                            <div class="min-w-0 pr-3">
                                <h4
                                    class="truncate text-sm leading-snug font-bold text-[#191919] dark:text-[#f0efea]"
                                >
                                    {{ item.nama }}
                                </h4>
                                <p
                                    class="mt-1 flex items-center gap-1.5 truncate text-xs font-semibold text-[#5e5e5e] dark:text-[#a3a3a3]"
                                >
                                    <span>{{ item.pengaju }}</span>
                                    <span>•</span>
                                    <span>{{ item.waktu_pengajuan }}</span>
                                </p>
                            </div>

                            <ChevronRight
                                :class="[
                                    'h-4 w-4 shrink-0 transition',
                                    selectedItem?.id === item.id
                                        ? 'translate-x-0.5 text-[#0b8043] dark:text-[#3fcb7a]'
                                        : 'text-gray-400 dark:text-[#737373]',
                                ]"
                            />
                        </button>
                    </div>

                    <!-- Empty List State -->
                    <div v-else class="p-12 text-center">
                        <Inbox
                            class="mx-auto mb-2 h-10 w-10 text-gray-300 dark:text-[#3d3d3d]"
                        />
                        <p
                            class="text-sm font-bold text-gray-800 dark:text-[#f0efea]"
                        >
                            Tidak ada pengajuan
                        </p>
                        <p
                            class="mt-1 text-xs text-gray-400 dark:text-[#a3a3a3]"
                        >
                            Tidak ada data dengan status
                            {{ activeStatusFilter }}.
                        </p>
                    </div>

                    <!-- Pagination Footer (Left Panel) -->
                    <div
                        v-if="submissions.total > 0"
                        class="flex items-center justify-between border-t border-[#eceae5] bg-[#f8f9fa] px-5 py-3.5 text-xs font-medium text-[#5e5e5e] dark:border-[#2e2e2e] dark:bg-[#262626] dark:text-[#a3a3a3]"
                    >
                        <span>
                            {{ submissions.from || 0 }} -
                            {{ submissions.to || 0 }} dari
                            {{ submissions.total }}
                        </span>
                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                @click="
                                    handlePageChange(submissions.prev_page_url)
                                "
                                :disabled="!submissions.prev_page_url"
                                class="cursor-pointer rounded-lg border border-[#eceae5] bg-white p-1.5 text-gray-700 transition hover:bg-gray-100 disabled:opacity-40 dark:border-[#3d3d3d] dark:bg-[#1c1c1c] dark:text-[#f0efea] dark:hover:bg-[#3d3d3d]"
                                title="Sebelumnya"
                            >
                                <ChevronLeft class="h-4 w-4" />
                            </button>
                            <span
                                v-if="submissions.last_page > 1"
                                class="px-1.5 text-xs font-semibold text-gray-500 select-none dark:text-[#a3a3a3]"
                            >
                                {{ submissions.current_page }} /
                                {{ submissions.last_page }}
                            </span>
                            <button
                                type="button"
                                @click="
                                    handlePageChange(submissions.next_page_url)
                                "
                                :disabled="!submissions.next_page_url"
                                class="cursor-pointer rounded-lg border border-[#eceae5] bg-white p-1.5 text-gray-700 transition hover:bg-gray-100 disabled:opacity-40 dark:border-[#3d3d3d] dark:bg-[#1c1c1c] dark:text-[#f0efea] dark:hover:bg-[#3d3d3d]"
                                title="Berikutnya"
                            >
                                <ChevronRight class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right Column (Col 8): Detail Review & Actions Panel -->
                <div
                    class="space-y-6 rounded-2xl border border-[#eceae5] bg-white p-6 shadow-xs sm:p-8 lg:col-span-7 xl:col-span-8 dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
                >
                    <template v-if="selectedItem">
                        <!-- Detail Header -->
                        <div
                            class="flex flex-col justify-between gap-3 border-b border-[#eceae5] pb-5 sm:flex-row sm:items-start dark:border-[#2e2e2e]"
                        >
                            <div>
                                <h2
                                    class="text-2xl font-extrabold tracking-tight text-[#191919] sm:text-3xl dark:text-[#f0efea]"
                                >
                                    {{ selectedItem.nama }}
                                </h2>
                                <p
                                    class="mt-1.5 text-xs leading-relaxed font-medium text-[#5e5e5e] sm:text-sm dark:text-[#a3a3a3]"
                                >
                                    Diajukan
                                    <strong
                                        class="text-[#191919] dark:text-[#f0efea]"
                                        >{{ selectedItem.pengaju }}</strong
                                    >
                                    • {{ selectedItem.tanggal_lengkap }} •
                                    Sumber:
                                    <span
                                        class="text-gray-800 dark:text-[#f0efea]"
                                        >{{ selectedItem.sumber }}</span
                                    >
                                </p>
                            </div>

                            <FoodStatusBadge :status="selectedItem.status" />
                            <AiFoodChip
                                v-if="selectedItem.ai_status"
                                :status="selectedItem.ai_status"
                            />
                        </div>

                        <!-- 8 Nutrition Metric Cards Grid (Figma Node 22-5552) -->
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <!-- Card 1: Takaran Saji -->
                            <div
                                class="space-y-1 rounded-xl bg-[#f4f3f0] p-3.5 dark:bg-[#262626]"
                            >
                                <span
                                    class="block text-[11px] font-semibold tracking-wider text-[#5e5e5e] uppercase dark:text-[#a3a3a3]"
                                >
                                    Takaran saji
                                </span>
                                <span
                                    class="block text-base font-bold text-[#191919] dark:text-[#f0efea]"
                                >
                                    {{ selectedItem.takaran_saji }}
                                </span>
                            </div>

                            <!-- Card 2: Energi -->
                            <div
                                class="space-y-1 rounded-xl bg-[#f4f3f0] p-3.5 dark:bg-[#262626]"
                            >
                                <span
                                    class="block text-[11px] font-semibold tracking-wider text-[#5e5e5e] uppercase dark:text-[#a3a3a3]"
                                >
                                    Energi
                                </span>
                                <span
                                    class="block text-base font-bold text-[#191919] dark:text-[#f0efea]"
                                >
                                    {{ Math.round(selectedItem.energi) }} kkal
                                </span>
                            </div>

                            <!-- Card 3: Protein -->
                            <div
                                class="space-y-1 rounded-xl bg-[#f4f3f0] p-3.5 dark:bg-[#262626]"
                            >
                                <span
                                    class="block text-[11px] font-semibold tracking-wider text-[#5e5e5e] uppercase dark:text-[#a3a3a3]"
                                >
                                    Protein
                                </span>
                                <span
                                    class="block text-base font-bold text-[#191919] dark:text-[#f0efea]"
                                >
                                    {{ Math.round(selectedItem.protein) }} g
                                </span>
                            </div>

                            <!-- Card 4: Karbohidrat -->
                            <div
                                class="space-y-1 rounded-xl bg-[#f4f3f0] p-3.5 dark:bg-[#262626]"
                            >
                                <span
                                    class="block text-[11px] font-semibold tracking-wider text-[#5e5e5e] uppercase dark:text-[#a3a3a3]"
                                >
                                    Karbohidrat
                                </span>
                                <span
                                    class="block text-base font-bold text-[#191919] dark:text-[#f0efea]"
                                >
                                    {{ Math.round(selectedItem.karbohidrat) }} g
                                </span>
                            </div>

                            <!-- Card 5: Lemak -->
                            <div
                                class="space-y-1 rounded-xl bg-[#f4f3f0] p-3.5 dark:bg-[#262626]"
                            >
                                <span
                                    class="block text-[11px] font-semibold tracking-wider text-[#5e5e5e] uppercase dark:text-[#a3a3a3]"
                                >
                                    Lemak
                                </span>
                                <span
                                    class="block text-base font-bold text-[#191919] dark:text-[#f0efea]"
                                >
                                    {{ Math.round(selectedItem.lemak) }} g
                                </span>
                            </div>

                            <!-- Card 6: Gula -->
                            <div
                                class="space-y-1 rounded-xl bg-[#f4f3f0] p-3.5 dark:bg-[#262626]"
                            >
                                <span
                                    class="block text-[11px] font-semibold tracking-wider text-[#5e5e5e] uppercase dark:text-[#a3a3a3]"
                                >
                                    Gula
                                </span>
                                <span
                                    class="block text-base font-bold text-[#191919] dark:text-[#f0efea]"
                                >
                                    {{ selectedItem.gula }} g
                                </span>
                            </div>

                            <!-- Card 7: Natrium -->
                            <div
                                class="space-y-1 rounded-xl bg-[#f4f3f0] p-3.5 dark:bg-[#262626]"
                            >
                                <span
                                    class="block text-[11px] font-semibold tracking-wider text-[#5e5e5e] uppercase dark:text-[#a3a3a3]"
                                >
                                    Natrium
                                </span>
                                <span
                                    class="block text-base font-bold text-[#191919] dark:text-[#f0efea]"
                                >
                                    {{ selectedItem.natrium }} mg
                                </span>
                            </div>

                            <!-- Card 8: Kategori -->
                            <div
                                class="space-y-1 rounded-xl bg-[#f4f3f0] p-3.5 dark:bg-[#262626]"
                            >
                                <span
                                    class="block text-[11px] font-semibold tracking-wider text-[#5e5e5e] uppercase dark:text-[#a3a3a3]"
                                >
                                    Kategori
                                </span>
                                <span
                                    class="block truncate text-base font-bold text-[#191919] dark:text-[#f0efea]"
                                >
                                    {{ selectedItem.kategori }}
                                </span>
                            </div>
                        </div>

                        <!-- Cek Energi Atwater Box (Figma Node 22-5580) -->
                        <div
                            v-if="atwaterCheck"
                            class="flex items-start gap-3 rounded-xl border border-[#0b7a46]/20 bg-[#e6f6ec] p-4 text-xs leading-relaxed font-medium text-[#0b7a46] sm:items-center sm:text-sm dark:border-[#3fcb7a]/20 dark:bg-[#17321f] dark:text-[#3fcb7a]"
                        >
                            <CheckCircle2
                                class="mt-0.5 h-5 w-5 shrink-0 sm:mt-0"
                            />
                            <span>
                                <strong>Cek energi:</strong>
                                {{ atwaterCheck.formula_text }}
                            </span>
                        </div>

                        <!-- Similar Food Warning Box (Figma Node 22-5585) -->
                        <div
                            v-if="similarFood"
                            class="flex items-start justify-between gap-3 rounded-xl border border-[#9a4a00]/20 bg-[#fff1d6] p-4 text-xs font-medium text-[#9a4a00] sm:items-center sm:text-sm dark:border-[#fca052]/20 dark:bg-[#3a1e14] dark:text-[#fca052]"
                        >
                            <div class="flex items-start gap-3 sm:items-center">
                                <AlertTriangle
                                    class="mt-0.5 h-5 w-5 shrink-0 text-[#9a4a00] sm:mt-0 dark:text-[#fca052]"
                                />
                                <span>{{ similarFood.text }}</span>
                            </div>
                        </div>

                        <!-- Rejection Note Alert if already rejected -->
                        <div
                            v-if="
                                selectedItem.status === 'ditolak' &&
                                selectedItem.alasan_penolakan
                            "
                            class="flex items-start gap-3 rounded-xl border border-[#b42318]/20 bg-[#fde8e6] p-4 text-xs text-[#b42318] sm:text-sm dark:border-red-900/40 dark:bg-red-950/40 dark:text-red-400"
                        >
                            <AlertCircle class="mt-0.5 h-5 w-5 shrink-0" />
                            <div>
                                <span class="block font-bold"
                                    >Alasan Penolakan:</span
                                >
                                <span class="mt-0.5 block leading-relaxed">{{
                                    selectedItem.alasan_penolakan
                                }}</span>
                            </div>
                        </div>

                        <!-- Action Buttons Section -->
                        <div
                            class="flex flex-col items-stretch justify-end gap-3 border-t border-[#eceae5] pt-4 sm:flex-row sm:items-center dark:border-[#2e2e2e]"
                        >
                            <!-- Buttons when status is 'menunggu' -->
                            <template v-if="selectedItem.status === 'menunggu'">
                                <!-- 1. Tolak Button -->
                                <button
                                    type="button"
                                    @click="openRejectModal"
                                    class="flex cursor-pointer items-center justify-center gap-1.5 rounded-full border-[1.5px] border-[#b42318] px-5 py-2.5 text-xs font-bold text-[#b42318] shadow-2xs transition hover:bg-red-50 dark:border-red-500 dark:text-red-400 dark:hover:bg-red-950/40"
                                >
                                    <X class="h-4 w-4" />
                                    <span>Tolak</span>
                                </button>

                                <!-- 2. Ubah lalu Verifikasi Button -->
                                <button
                                    type="button"
                                    @click="openEditDrawer"
                                    class="flex cursor-pointer items-center justify-center gap-1.5 rounded-full border-[1.5px] border-[#191919] px-5 py-2.5 text-xs font-bold text-[#191919] shadow-2xs transition hover:bg-gray-100 dark:border-[#3d3d3d] dark:text-[#f0efea] dark:hover:bg-[#262626]"
                                >
                                    <Pencil
                                        class="h-3.5 w-3.5 text-gray-700 dark:text-[#a3a3a3]"
                                    />
                                    <span>Ubah lalu Verifikasi</span>
                                </button>

                                <!-- 3. Direct Approve (Verifikasi) -->
                                <button
                                    type="button"
                                    @click="approveSubmission"
                                    class="flex cursor-pointer items-center justify-center gap-1.5 rounded-full bg-[#0b8043] px-6 py-2.5 text-xs font-bold text-white shadow-xs transition hover:bg-[#086637] dark:bg-[#3fcb7a] dark:text-[#121212] dark:hover:bg-[#34aa66]"
                                >
                                    <Check class="h-4 w-4" />
                                    <span>Verifikasi</span>
                                </button>
                            </template>

                            <!-- Buttons when status is 'ditolak' -->
                            <template
                                v-else-if="selectedItem.status === 'ditolak'"
                            >
                                <button
                                    type="button"
                                    @click="openEditDrawer"
                                    class="flex cursor-pointer items-center justify-center gap-1.5 rounded-full border-[1.5px] border-[#191919] px-5 py-2.5 text-xs font-bold text-[#191919] transition hover:bg-gray-100 dark:border-[#3d3d3d] dark:text-[#f0efea] dark:hover:bg-[#262626]"
                                >
                                    <Pencil class="h-3.5 w-3.5" />
                                    <span>Perbaiki & Verifikasi Ulang</span>
                                </button>
                            </template>

                            <!-- State when status is 'terverifikasi' -->
                            <template v-else>
                                <span
                                    class="flex items-center gap-1.5 rounded-full bg-[#e6f6ec] px-4 py-2 text-xs font-semibold text-[#0b7a46] dark:bg-[#17321f] dark:text-[#3fcb7a]"
                                >
                                    <CheckCircle2 class="h-4 w-4" />
                                    <span
                                        >Makanan ini telah diverifikasi dan
                                        aktif di katalog.</span
                                    >
                                </span>
                            </template>
                        </div>

                        <!-- Database Sync Info Note (Figma Node 22-5606) -->
                        <div
                            class="flex items-center gap-2 border-t border-dashed border-[#eceae5] pt-2 text-xs font-medium text-[#5e5e5e] dark:border-[#2e2e2e] dark:text-[#a3a3a3]"
                        >
                            <Database
                                class="h-4 w-4 shrink-0 text-gray-400 dark:text-[#737373]"
                            />
                            <span
                                >Setelah diverifikasi, Chat Gizi bisa mengutip
                                makanan ini dalam sekitar 2 menit.</span
                            >
                        </div>
                    </template>

                    <!-- Empty Selected State -->
                    <div v-else class="space-y-2 py-20 text-center">
                        <Inbox
                            class="mx-auto h-12 w-12 text-gray-300 dark:text-[#3d3d3d]"
                        />
                        <h3
                            class="text-base font-bold text-gray-800 dark:text-[#f0efea]"
                        >
                            Pilih Pengajuan Makanan
                        </h3>
                        <p
                            class="mx-auto max-w-sm text-xs text-gray-500 dark:text-[#a3a3a3]"
                        >
                            Klik salah satu makanan di antrean sebelah kiri
                            untuk meninjau detail nutrisi, cek Atwater, dan
                            melakukan verifikasi.
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
