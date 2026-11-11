<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ChipFilter from '@/Components/UI/ChipFilter.vue';
import SearchBar from '@/Components/UI/SearchBar.vue';
import PillButton from '@/Components/UI/PillButton.vue';
import DataTable, { type TableColumn } from '@/Components/UI/DataTable.vue';
import ArticleCategoryModal from '@/Components/Admin/ArticleCategoryModal.vue';
import {
    Plus,
    Pencil,
    Trash2,
    BookOpen,
    AlertCircle,
    Eye,
    Calendar,
    Sparkles,
    Folder,
} from 'lucide-vue-next';

export interface ArticleItem {
    id: number;
    judul: string;
    slug: string;
    ringkasan: string | null;
    kategori: string;
    foto_sampul_url: string | null;
    nama_penulis: string;
    status: 'terbit' | 'draf';
    views: number;
    diperbarui: string;
    published_at: string | null;
}

const props = defineProps<{
    artikels: {
        data: ArticleItem[];
        total: number;
        from: number | null;
        to: number | null;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: {
        search: string;
        status: string;
    };
    counts: {
        semua: number;
        terbit: number;
        draf: number;
    };
}>();

const activeStatusFilter = ref(props.filters.status || 'semua');
const searchQuery = ref(props.filters.search || '');

// Delete modal state
const isDeleteModalOpen = ref(false);
const articleToDelete = ref<ArticleItem | null>(null);
const isDeleting = ref(false);

// Category modal state
const isCategoryModalOpen = ref(false);

const tableColumns: TableColumn[] = [
    { key: 'judul', label: 'Judul', width: '460px' },
    { key: 'kategori', label: 'Kategori', width: '160px' },
    { key: 'status', label: 'Status', width: '130px' },
    { key: 'diperbarui', label: 'Diperbarui', width: '140px' },
    { key: 'actions', label: '', width: '96px', align: 'right' },
];

const applyStatusFilter = (statusKey: string) => {
    activeStatusFilter.value = statusKey;
    router.get(
        route('admin.articles'),
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
        route('admin.articles'),
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

const confirmDelete = (article: ArticleItem) => {
    articleToDelete.value = article;
    isDeleteModalOpen.value = true;
};

const executeDelete = () => {
    if (!articleToDelete.value) return;
    isDeleting.value = true;
    router.delete(route('admin.articles.destroy', articleToDelete.value.id), {
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            articleToDelete.value = null;
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};
</script>

<template>
    <AdminLayout title="Manajemen Artikel">
        <Head title="Manajemen Artikel - CarePlate Admin" />

        <div class="max-w-6xl space-y-6">
            <!-- Header Section -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1
                        class="text-3xl font-extrabold tracking-tight text-[#191919] sm:text-4xl dark:text-[#f0efea]"
                    >
                        Artikel
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-[#a3a3a3]">
                        Kelola artikel edukasi gizi, panduan konsumsi pangan,
                        dan konten keluarga.
                    </p>
                </div>
                <div class="flex items-center gap-2.5">
                    <button
                        type="button"
                        @click="isCategoryModalOpen = true"
                        class="flex cursor-pointer items-center gap-1.5 rounded-full border border-gray-200 bg-white px-4 py-2.5 text-xs font-semibold text-gray-700 shadow-2xs transition hover:border-gray-300 hover:bg-gray-50 dark:border-[#2e2e2e] dark:bg-[#1c1c1c] dark:text-[#f0efea] dark:hover:border-[#3d3d3d] dark:hover:bg-[#262626]"
                    >
                        <Folder
                            class="h-3.5 w-3.5 text-[#0b8043] dark:text-[#3fcb7a]"
                        />
                        <span>Kelola Kategori</span>
                    </button>
                    <Link :href="route('admin.articles.create')">
                        <PillButton variant="primary" class="shadow-sm">
                            <Plus class="mr-1.5 h-4 w-4" />
                            <span>Tulis Artikel</span>
                        </PillButton>
                    </Link>
                </div>
            </div>

            <!-- Filter Tabs & Search Bar -->
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <!-- Status Tabs -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1">
                    <ChipFilter
                        label="Semua"
                        :count="counts.semua"
                        :active="activeStatusFilter === 'semua'"
                        @click="applyStatusFilter('semua')"
                    />
                    <ChipFilter
                        label="Terbit"
                        :count="counts.terbit"
                        :active="activeStatusFilter === 'terbit'"
                        @click="applyStatusFilter('terbit')"
                    />
                    <ChipFilter
                        label="Draf"
                        :count="counts.draf"
                        :active="activeStatusFilter === 'draf'"
                        @click="applyStatusFilter('draf')"
                    />
                </div>

                <!-- Search Input -->
                <div class="w-full md:w-80">
                    <SearchBar
                        v-model="searchQuery"
                        placeholder="Cari judul artikel..."
                        @search="handleSearch"
                        @clear="handleSearch"
                    />
                </div>
            </div>

            <!-- Table Card -->
            <div
                class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-xs dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
            >
                <DataTable
                    :columns="tableColumns"
                    :items="artikels.data"
                    :total="artikels.total"
                    :from="artikels.from"
                    :to="artikels.to"
                    :prev-url="artikels.prev_page_url"
                    :next-url="artikels.next_page_url"
                    empty-title="Belum ada artikel"
                    empty-description="Belum ada artikel yang cocok dengan filter atau kata kunci saat ini."
                    @page-change="handlePageChange"
                >
                    <template #row="{ item }: { item: ArticleItem }">
                        <!-- Judul Column (Thumbnail 64x44 + Judul + Penulis) -->
                        <td class="px-6 py-3.5">
                            <div class="flex items-center gap-3.5">
                                <div
                                    class="h-11 w-16 shrink-0 overflow-hidden rounded-lg border border-gray-200 bg-gray-100 dark:border-[#2e2e2e] dark:bg-[#262626]"
                                >
                                    <img
                                        v-if="item.foto_sampul_url"
                                        :src="item.foto_sampul_url"
                                        :alt="item.judul"
                                        class="h-full w-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full w-full items-center justify-center bg-emerald-50 text-[#0b8043] dark:bg-[#17321f] dark:text-[#3fcb7a]"
                                    >
                                        <BookOpen class="h-5 w-5 opacity-60" />
                                    </div>
                                </div>
                                <div class="min-w-0 pr-2">
                                    <Link
                                        :href="
                                            route(
                                                'admin.articles.edit',
                                                item.id,
                                            )
                                        "
                                        class="line-clamp-1 block text-sm leading-snug font-semibold text-gray-900 transition hover:text-[#0b8043] dark:text-[#f0efea] dark:hover:text-[#3fcb7a]"
                                        :title="item.judul"
                                    >
                                        {{ item.judul }}
                                    </Link>
                                    <span
                                        class="mt-0.5 block text-xs text-gray-400 dark:text-[#a3a3a3]"
                                    >
                                        oleh {{ item.nama_penulis }}
                                    </span>
                                </div>
                            </div>
                        </td>

                        <!-- Kategori Column -->
                        <td class="px-6 py-3.5 whitespace-nowrap">
                            <span
                                class="inline-flex items-center rounded-full bg-[#f0f4f1] px-2.5 py-1 text-xs font-medium text-[#2d4b38] dark:bg-[#262626] dark:text-[#3fcb7a]"
                            >
                                {{ item.kategori }}
                            </span>
                        </td>

                        <!-- Status Column (Terbit / Draf badge with dot) -->
                        <td class="px-6 py-3.5 whitespace-nowrap">
                            <span
                                v-if="item.status === 'terbit'"
                                class="inline-flex items-center gap-1.5 rounded-full bg-[#e6f6ec] px-3 py-1 text-xs font-semibold text-[#0b7a46] dark:bg-[#17321f] dark:text-[#3fcb7a]"
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full bg-[#0b7a46] dark:bg-[#3fcb7a]"
                                ></span>
                                Terbit
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1.5 rounded-full bg-[#f4f3f0] px-3 py-1 text-xs font-semibold text-[#5e5e5e] dark:bg-[#262626] dark:text-[#a3a3a3]"
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full bg-[#8c8b88] dark:bg-[#737373]"
                                ></span>
                                Draf
                            </span>
                        </td>

                        <!-- Diperbarui Column -->
                        <td
                            class="px-6 py-3.5 text-xs font-normal whitespace-nowrap text-gray-500 dark:text-[#a3a3a3]"
                        >
                            {{ item.diperbarui }}
                        </td>

                        <!-- Actions Column -->
                        <td class="px-6 py-3.5 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1.5">
                                <Link
                                    :href="
                                        route('admin.articles.edit', item.id)
                                    "
                                    class="rounded-lg p-1.5 text-gray-400 transition hover:bg-emerald-50 hover:text-[#0b8043] dark:text-[#a3a3a3] dark:hover:bg-[#17321f] dark:hover:text-[#3fcb7a]"
                                    title="Edit Artikel"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Link>
                                <button
                                    type="button"
                                    @click="confirmDelete(item)"
                                    class="rounded-lg p-1.5 text-gray-400 transition hover:bg-red-50 hover:text-red-600 dark:text-[#a3a3a3] dark:hover:bg-red-950/40 dark:hover:text-red-400"
                                    title="Hapus Artikel"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </template>
                </DataTable>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
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
                    Hapus Artikel?
                </h3>
                <p
                    class="mb-6 text-sm leading-relaxed text-gray-600 dark:text-[#a3a3a3]"
                >
                    Apakah Anda yakin ingin menghapus artikel
                    <strong class="text-gray-900 dark:text-[#f0efea]"
                        >"{{ articleToDelete?.judul }}"</strong
                    >? Tindakan ini permanen dan tidak dapat dibatalkan.
                </p>
                <div class="flex items-center justify-end gap-3">
                    <button
                        type="button"
                        @click="isDeleteModalOpen = false"
                        :disabled="isDeleting"
                        class="rounded-full px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-100 dark:text-[#a3a3a3] dark:hover:bg-[#262626]"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="executeDelete"
                        :disabled="isDeleting"
                        class="rounded-full bg-red-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 disabled:opacity-50"
                    >
                        {{ isDeleting ? 'Menghapus...' : 'Ya, Hapus' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Kelola Kategori Artikel -->
        <ArticleCategoryModal
            :is-open="isCategoryModalOpen"
            @close="isCategoryModalOpen = false"
        />
    </AdminLayout>
</template>
