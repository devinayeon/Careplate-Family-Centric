<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ChipFilter from '@/Components/UI/ChipFilter.vue';
import SearchBar from '@/Components/UI/SearchBar.vue';
import PillButton from '@/Components/UI/PillButton.vue';
import DataTable, { type TableColumn } from '@/Components/UI/DataTable.vue';
import FoodStatusBadge from '@/Components/UI/FoodStatusBadge.vue';
import AiFoodChip from '@/Components/Ai/AiFoodChip.vue';
import FoodFormDrawer, {
    type FoodFormData,
} from '@/Components/Admin/FoodFormDrawer.vue';
import {
    Plus,
    Pencil,
    Trash2,
    Utensils,
    AlertCircle,
    CheckCircle2,
} from 'lucide-vue-next';

export interface FoodItem {
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
}

const props = defineProps<{
    foods: {
        data: FoodItem[];
        total: number;
        from: number | null;
        to: number | null;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    categories: Array<{ id: number; kategori: string }>;
    filters: {
        search: string;
        status: string;
    };
    counts: {
        semua: number;
        terverifikasi: number;
        menunggu: number;
        ditolak: number;
    };
}>();

const activeStatusFilter = ref(props.filters.status || 'semua');
const searchQuery = ref(props.filters.search || '');

// Drawer state
const isDrawerOpen = ref(false);
const foodToEdit = ref<FoodFormData | null>(null);

// Soft delete modal state
const isDeleteModalOpen = ref(false);
const foodToDelete = ref<FoodItem | null>(null);
const isDeleting = ref(false);

const tableColumns: TableColumn[] = [
    { key: 'nama', label: 'Nama', width: '320px' },
    { key: 'kategori', label: 'Kategori', width: '150px' },
    { key: 'energi', label: 'Energi', width: '110px', align: 'right' },
    { key: 'protein', label: 'Protein', width: '100px', align: 'right' },
    { key: 'status', label: 'Status', width: '160px' },
    { key: 'pengaju', label: 'Pengaju', width: '140px' },
    { key: 'actions', label: '', width: '96px', align: 'right' },
];

const applyStatusFilter = (statusKey: string) => {
    activeStatusFilter.value = statusKey;
    router.get(
        route('admin.foods'),
        {
            status: statusKey !== 'semua' ? statusKey : undefined,
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
        route('admin.foods'),
        {
            status:
                activeStatusFilter.value !== 'semua'
                    ? activeStatusFilter.value
                    : undefined,
            search: searchQuery.value || undefined,
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

const openCreateDrawer = () => {
    foodToEdit.value = null;
    isDrawerOpen.value = true;
};

const openEditDrawer = (item: FoodItem) => {
    foodToEdit.value = {
        id: item.id,
        nama: item.nama,
        kategori_makanan_id: item.kategori_makanan_id,
        kuantitas: item.kuantitas,
        satuan: item.satuan,
        berat_per_satuan_gram: item.berat_per_satuan_gram,
        energi: item.energi,
        protein: item.protein,
        karbohidrat: item.karbohidrat,
        lemak: item.lemak,
        gula: item.gula,
        natrium: item.natrium,
        sumber: item.sumber,
        status: item.status,
        foto_makanan_url: item.foto_makanan_url,
    };
    isDrawerOpen.value = true;
};

const confirmDelete = (item: FoodItem) => {
    foodToDelete.value = item;
    isDeleteModalOpen.value = true;
};

const executeSoftDelete = () => {
    if (!foodToDelete.value) return;
    isDeleting.value = true;
    router.delete(route('admin.foods.destroy', foodToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            foodToDelete.value = null;
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};
</script>

<template>
    <AdminLayout title="Data Makanan">
        <Head title="Data Makanan - CarePlate Admin" />

        <div class="max-w-6xl space-y-6">
            <!-- 1. Header Section -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1
                        class="text-3xl font-extrabold tracking-tight text-[#191919] sm:text-4xl dark:text-[#f0efea]"
                    >
                        Data Makanan
                    </h1>
                    <p class="mt-1 text-sm text-[#5e5e5e] dark:text-[#a3a3a3]">
                        {{ counts.terverifikasi }} makanan terverifikasi.
                        Perubahan otomatis tersinkron ke Chat Gizi.
                    </p>
                </div>

                <PillButton
                    variant="primary"
                    @click="openCreateDrawer"
                    class="cursor-pointer self-start shadow-xs sm:self-center"
                >
                    <Plus class="mr-1.5 h-4 w-4" />
                    <span>Tambah Makanan</span>
                </PillButton>
            </div>

            <!-- 2. Filter Bar: Status Tabs & Search Bar -->
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <!-- Status Filter Chips -->
                <div
                    class="flex scrollbar-none items-center gap-2 overflow-x-auto pb-1"
                >
                    <ChipFilter
                        label="Semua"
                        :count="counts.semua"
                        :active="activeStatusFilter === 'semua'"
                        @click="applyStatusFilter('semua')"
                    />
                    <ChipFilter
                        label="Terverifikasi"
                        :count="counts.terverifikasi"
                        :active="activeStatusFilter === 'terverifikasi'"
                        @click="applyStatusFilter('terverifikasi')"
                    />
                    <ChipFilter
                        label="Menunggu"
                        :count="counts.menunggu"
                        :active="activeStatusFilter === 'menunggu'"
                        @click="applyStatusFilter('menunggu')"
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
                        placeholder="Cari nama makanan..."
                        @search="handleSearch"
                        @clear="handleSearch"
                    />
                </div>
            </div>

            <!-- 3. Table Card -->
            <div
                class="overflow-hidden rounded-2xl border border-[#eceae5] bg-white shadow-xs dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
            >
                <DataTable
                    :columns="tableColumns"
                    :items="foods.data"
                    :total="foods.total"
                    :from="foods.from"
                    :to="foods.to"
                    :prev-url="foods.prev_page_url"
                    :next-url="foods.next_page_url"
                    empty-title="Belum ada data makanan"
                    empty-description="Belum ada makanan yang cocok dengan kriteria filter atau pencarian saat ini."
                    @page-change="handlePageChange"
                >
                    <template #row="{ item }: { item: FoodItem }">
                        <!-- Col 1: Nama, Foto Makanan, dan Takaran Saji -->
                        <td class="px-6 py-3.5">
                            <div class="flex items-center gap-3">
                                <!-- Thumbnail Foto Makanan 48x48 -->
                                <div
                                    class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-[#eceae5] bg-[#f4f3f0] dark:border-[#2e2e2e] dark:bg-[#262626]"
                                >
                                    <img
                                        v-if="item.foto_makanan_url"
                                        :src="item.foto_makanan_url"
                                        :alt="item.nama"
                                        class="h-full w-full object-cover"
                                    />
                                    <Utensils
                                        v-else
                                        class="h-5 w-5 text-gray-400 dark:text-[#a3a3a3]"
                                    />
                                </div>

                                <div class="min-w-0 pr-2">
                                    <button
                                        type="button"
                                        @click="openEditDrawer(item)"
                                        class="line-clamp-1 block cursor-pointer text-left text-sm font-bold text-[#191919] transition hover:text-[#0b8043] dark:text-[#f0efea] dark:hover:text-[#3fcb7a]"
                                        :title="item.nama"
                                    >
                                        {{ item.nama }}
                                    </button>
                                    <span
                                        class="mt-0.5 block text-xs font-semibold text-[#5e5e5e] dark:text-[#a3a3a3]"
                                    >
                                        {{ item.takaran_saji }}
                                    </span>
                                </div>
                            </div>
                        </td>

                        <!-- Col 2: Kategori -->
                        <td
                            class="px-6 py-3.5 text-sm font-medium whitespace-nowrap text-[#191919] dark:text-[#f0efea]"
                        >
                            {{ item.kategori }}
                        </td>

                        <!-- Col 3: Energi -->
                        <td
                            class="px-6 py-3.5 text-right text-sm font-bold whitespace-nowrap text-[#191919] dark:text-[#f0efea]"
                        >
                            {{ Math.round(item.energi) }} kkal
                        </td>

                        <!-- Col 4: Protein -->
                        <td
                            class="px-6 py-3.5 text-right text-sm font-bold whitespace-nowrap text-[#191919] dark:text-[#f0efea]"
                        >
                            {{ Math.round(item.protein) }} g
                        </td>

                        <!-- Col 5: Status Badge -->
                        <td class="px-6 py-3.5">
                            <FoodStatusBadge :status="item.status" />
                            <AiFoodChip
                                v-if="item.ai_status"
                                :status="item.ai_status"
                                class="mt-1.5"
                            />
                        </td>

                        <!-- Col 6: Pengaju -->
                        <td
                            class="px-6 py-3.5 text-sm font-medium whitespace-nowrap text-[#191919] dark:text-[#f0efea]"
                        >
                            {{ item.pengaju }}
                        </td>

                        <!-- Col 7: Actions (Ubah & Hapus) -->
                        <td class="px-6 py-3.5 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1.5">
                                <button
                                    type="button"
                                    @click="openEditDrawer(item)"
                                    class="cursor-pointer rounded-xl p-2 text-gray-400 transition hover:bg-emerald-50 hover:text-[#0b8043] dark:text-[#a3a3a3] dark:hover:bg-[#17321f] dark:hover:text-[#3fcb7a]"
                                    title="Ubah Makanan"
                                >
                                    <Pencil class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    @click="confirmDelete(item)"
                                    class="cursor-pointer rounded-xl p-2 text-gray-400 transition hover:bg-red-50 hover:text-red-600 dark:text-[#a3a3a3] dark:hover:bg-red-950/40 dark:hover:text-red-400"
                                    title="Hapus Makanan (Soft Delete)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </template>
                </DataTable>
            </div>
        </div>

        <!-- Side Sheet Drawer Tambah / Edit Makanan -->
        <FoodFormDrawer
            :show="isDrawerOpen"
            :food-to-edit="foodToEdit"
            :categories="categories"
            @close="isDrawerOpen = false"
            @success="isDrawerOpen = false"
        />

        <!-- Modal Konfirmasi Soft Delete -->
        <div
            v-if="isDeleteModalOpen"
            class="animate-in fade-in fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs duration-150"
        >
            <div
                class="w-full max-w-md rounded-3xl border border-gray-100 bg-white p-6 shadow-2xl dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
            >
                <div
                    class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-red-600 dark:bg-red-950/40 dark:text-red-400"
                >
                    <AlertCircle class="h-6 w-6" />
                </div>
                <h3
                    class="mb-1 text-lg font-bold text-gray-900 dark:text-[#f0efea]"
                >
                    Hapus Data Makanan?
                </h3>
                <p
                    class="mb-4 text-sm leading-relaxed text-gray-600 dark:text-[#a3a3a3]"
                >
                    Apakah Anda yakin ingin menghapus
                    <strong class="text-gray-900 dark:text-[#f0efea]"
                        >"{{ foodToDelete?.nama }}"</strong
                    >?
                </p>
                <div
                    class="mb-6 flex items-start gap-2 rounded-xl border border-amber-200/60 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300"
                >
                    <CheckCircle2
                        class="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400"
                    />
                    <span>
                        <strong>Soft Delete Aktif:</strong> Data ini akan
                        disembunyikan dari katalog, namun data historis
                        pencatatan makanan pengguna yang pernah mencatat makanan
                        ini akan tetap aman terjaga.
                    </span>
                </div>
                <div class="flex items-center justify-end gap-3">
                    <button
                        type="button"
                        @click="isDeleteModalOpen = false"
                        :disabled="isDeleting"
                        class="cursor-pointer rounded-full px-5 py-2.5 text-xs font-bold text-gray-600 transition hover:bg-gray-100 dark:text-[#a3a3a3] dark:hover:bg-[#262626]"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="executeSoftDelete"
                        :disabled="isDeleting"
                        class="cursor-pointer rounded-full bg-red-600 px-5 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-red-700 disabled:opacity-50"
                    >
                        {{ isDeleting ? 'Menghapus...' : 'Ya, Hapus Data' }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
