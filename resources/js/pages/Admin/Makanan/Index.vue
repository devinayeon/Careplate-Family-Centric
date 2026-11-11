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
import FoodFormDrawer, { type FoodFormData } from '@/Components/Admin/FoodFormDrawer.vue';
import {
    Plus,
    Pencil,
    Trash2,
    Utensils,
    AlertCircle,
    CheckCircle2
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
        }
    );
};

const handleSearch = () => {
    router.get(
        route('admin.foods'),
        {
            status: activeStatusFilter.value !== 'semua' ? activeStatusFilter.value : undefined,
            search: searchQuery.value || undefined,
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

        <div class="space-y-6 max-w-6xl">
            <!-- 1. Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold text-[#191919] tracking-tight">
                        Data Makanan
                    </h1>
                    <p class="text-sm text-[#5e5e5e] mt-1">
                        {{ counts.terverifikasi }} makanan terverifikasi. Perubahan otomatis tersinkron ke Chat Gizi.
                    </p>
                </div>

                <PillButton
                    variant="primary"
                    @click="openCreateDrawer"
                    class="self-start sm:self-center shadow-xs cursor-pointer"
                >
                    <Plus class="w-4 h-4 mr-1.5" />
                    <span>Tambah Makanan</span>
                </PillButton>
            </div>

            <!-- 2. Filter Bar: Status Tabs & Search Bar -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <!-- Status Filter Chips -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
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
            <div class="bg-white rounded-2xl border border-[#eceae5] shadow-xs overflow-hidden">
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
                                <div class="w-12 h-12 shrink-0 rounded-xl overflow-hidden bg-[#f4f3f0] border border-[#eceae5] flex items-center justify-center">
                                    <img
                                        v-if="item.foto_makanan_url"
                                        :src="item.foto_makanan_url"
                                        :alt="item.nama"
                                        class="w-full h-full object-cover"
                                    />
                                    <Utensils v-else class="w-5 h-5 text-gray-400" />
                                </div>

                                <div class="min-w-0 pr-2">
                                    <button
                                        type="button"
                                        @click="openEditDrawer(item)"
                                        class="font-bold text-[#191919] hover:text-[#0b8043] transition line-clamp-1 block text-sm text-left cursor-pointer"
                                        :title="item.nama"
                                    >
                                        {{ item.nama }}
                                    </button>
                                    <span class="text-xs font-semibold text-[#5e5e5e] block mt-0.5">
                                        {{ item.takaran_saji }}
                                    </span>
                                </div>
                            </div>
                        </td>

                        <!-- Col 2: Kategori -->
                        <td class="px-6 py-3.5 whitespace-nowrap text-sm text-[#191919] font-medium">
                            {{ item.kategori }}
                        </td>

                        <!-- Col 3: Energi -->
                        <td class="px-6 py-3.5 whitespace-nowrap text-right font-bold text-sm text-[#191919]">
                            {{ Math.round(item.energi) }} kkal
                        </td>

                        <!-- Col 4: Protein -->
                        <td class="px-6 py-3.5 whitespace-nowrap text-right font-bold text-sm text-[#191919]">
                            {{ Math.round(item.protein) }} g
                        </td>

                        <!-- Col 5: Status Badge -->
                        <td class="px-6 py-3.5">
                            <FoodStatusBadge :status="item.status" />
                            <AiFoodChip v-if="item.ai_status" :status="item.ai_status" class="mt-1.5" />
                        </td>

                        <!-- Col 6: Pengaju -->
                        <td class="px-6 py-3.5 whitespace-nowrap text-sm text-[#191919] font-medium">
                            {{ item.pengaju }}
                        </td>

                        <!-- Col 7: Actions (Ubah & Hapus) -->
                        <td class="px-6 py-3.5 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1.5">
                                <button
                                    type="button"
                                    @click="openEditDrawer(item)"
                                    class="p-2 rounded-xl text-gray-400 hover:text-[#0b8043] hover:bg-emerald-50 transition cursor-pointer"
                                    title="Ubah Makanan"
                                >
                                    <Pencil class="w-4 h-4" />
                                </button>
                                <button
                                    type="button"
                                    @click="confirmDelete(item)"
                                    class="p-2 rounded-xl text-gray-400 hover:text-red-600 hover:bg-red-50 transition cursor-pointer"
                                    title="Hapus Makanan (Soft Delete)"
                                >
                                    <Trash2 class="w-4 h-4" />
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
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-xs p-4 animate-in fade-in duration-150"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-gray-100">
                <div class="w-12 h-12 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center mb-4">
                    <AlertCircle class="w-6 h-6" />
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-1">
                    Hapus Data Makanan?
                </h3>
                <p class="text-sm text-gray-600 mb-4 leading-relaxed">
                    Apakah Anda yakin ingin menghapus
                    <strong class="text-gray-900">"{{ foodToDelete?.nama }}"</strong>?
                </p>
                <div class="p-3 bg-amber-50 rounded-xl border border-amber-200/60 mb-6 text-xs text-amber-800 flex items-start gap-2">
                    <CheckCircle2 class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                    <span>
                        <strong>Soft Delete Aktif:</strong> Data ini akan disembunyikan dari katalog, namun data historis pencatatan makanan pengguna yang pernah mencatat makanan ini akan tetap aman terjaga.
                    </span>
                </div>
                <div class="flex items-center justify-end gap-3">
                    <button
                        type="button"
                        @click="isDeleteModalOpen = false"
                        :disabled="isDeleting"
                        class="px-5 py-2.5 rounded-full text-xs font-bold text-gray-600 hover:bg-gray-100 transition cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="executeSoftDelete"
                        :disabled="isDeleting"
                        class="px-5 py-2.5 rounded-full text-xs font-bold text-white bg-red-600 hover:bg-red-700 shadow-sm transition disabled:opacity-50 cursor-pointer"
                    >
                        {{ isDeleting ? 'Menghapus...' : 'Ya, Hapus Data' }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
