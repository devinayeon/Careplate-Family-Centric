<script setup lang="ts">
import { ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import ChipFilter from "@/Components/UI/ChipFilter.vue";
import SearchBar from "@/Components/UI/SearchBar.vue";
import PillButton from "@/Components/UI/PillButton.vue";
import DataTable, { type TableColumn } from "@/Components/UI/DataTable.vue";
import ArticleCategoryModal from "@/Components/Admin/ArticleCategoryModal.vue";
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
} from "lucide-vue-next";

export interface ArticleItem {
    id: number;
    judul: string;
    slug: string;
    ringkasan: string | null;
    kategori: string;
    foto_sampul_url: string | null;
    nama_penulis: string;
    status: "terbit" | "draf";
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

const activeStatusFilter = ref(props.filters.status || "semua");
const searchQuery = ref(props.filters.search || "");

// Delete modal state
const isDeleteModalOpen = ref(false);
const articleToDelete = ref<ArticleItem | null>(null);
const isDeleting = ref(false);

// Category modal state
const isCategoryModalOpen = ref(false);

const tableColumns: TableColumn[] = [
    { key: "judul", label: "Judul", width: "460px" },
    { key: "kategori", label: "Kategori", width: "160px" },
    { key: "status", label: "Status", width: "130px" },
    { key: "diperbarui", label: "Diperbarui", width: "140px" },
    { key: "actions", label: "", width: "96px", align: "right" },
];

const applyStatusFilter = (statusKey: string) => {
    activeStatusFilter.value = statusKey;
    router.get(
        route("admin.articles"),
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
        route("admin.articles"),
        {
            status:
                activeStatusFilter.value !== "semua"
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
    router.delete(route("admin.articles.destroy", articleToDelete.value.id), {
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

        <div class="space-y-6 max-w-6xl">
            <!-- Header Section -->
            <div
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
            >
                <div>
                    <h1
                        class="text-3xl sm:text-4xl font-extrabold text-[#191919] dark:text-[#f0efea] tracking-tight"
                    >
                        Artikel
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-[#a3a3a3] mt-1">
                        Kelola artikel edukasi gizi, panduan konsumsi pangan,
                        dan konten keluarga.
                    </p>
                </div>
                <div class="flex items-center gap-2.5">
                    <button
                        type="button"
                        @click="isCategoryModalOpen = true"
                        class="px-4 py-2.5 rounded-full border border-gray-200 dark:border-[#2e2e2e] bg-white dark:bg-[#1c1c1c] text-xs font-semibold text-gray-700 dark:text-[#f0efea] hover:bg-gray-50 dark:hover:bg-[#262626] hover:border-gray-300 dark:hover:border-[#3d3d3d] transition flex items-center gap-1.5 shadow-2xs cursor-pointer"
                    >
                        <Folder class="w-3.5 h-3.5 text-[#0b8043] dark:text-[#3fcb7a]" />
                        <span>Kelola Kategori</span>
                    </button>
                    <Link :href="route('admin.articles.create')">
                        <PillButton variant="primary" class="shadow-sm">
                            <Plus class="w-4 h-4 mr-1.5" />
                            <span>Tulis Artikel</span>
                        </PillButton>
                    </Link>
                </div>
            </div>

            <!-- Filter Tabs & Search Bar -->
            <div
                class="flex flex-col md:flex-row md:items-center md:justify-between gap-4"
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
                class="bg-white dark:bg-[#1c1c1c] rounded-3xl border border-gray-100 dark:border-[#2e2e2e] shadow-xs overflow-hidden"
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
                                    class="w-16 h-11 shrink-0 rounded-lg overflow-hidden bg-gray-100 dark:bg-[#262626] border border-gray-200 dark:border-[#2e2e2e]"
                                >
                                    <img
                                        v-if="item.foto_sampul_url"
                                        :src="item.foto_sampul_url"
                                        :alt="item.judul"
                                        class="w-full h-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="w-full h-full flex items-center justify-center bg-emerald-50 dark:bg-[#17321f] text-[#0b8043] dark:text-[#3fcb7a]"
                                    >
                                        <BookOpen class="w-5 h-5 opacity-60" />
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
                                        class="font-semibold text-gray-900 dark:text-[#f0efea] hover:text-[#0b8043] dark:hover:text-[#3fcb7a] transition line-clamp-1 block text-sm leading-snug"
                                        :title="item.judul"
                                    >
                                        {{ item.judul }}
                                    </Link>
                                    <span
                                        class="text-xs text-gray-400 dark:text-[#a3a3a3] block mt-0.5"
                                    >
                                        oleh {{ item.nama_penulis }}
                                    </span>
                                </div>
                            </div>
                        </td>

                        <!-- Kategori Column -->
                        <td class="px-6 py-3.5 whitespace-nowrap">
                            <span
                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-[#f0f4f1] dark:bg-[#262626] text-[#2d4b38] dark:text-[#3fcb7a]"
                            >
                                {{ item.kategori }}
                            </span>
                        </td>

                        <!-- Status Column (Terbit / Draf badge with dot) -->
                        <td class="px-6 py-3.5 whitespace-nowrap">
                            <span
                                v-if="item.status === 'terbit'"
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#e6f6ec] dark:bg-[#17321f] text-[#0b7a46] dark:text-[#3fcb7a]"
                            >
                                <span
                                    class="w-1.5 h-1.5 rounded-full bg-[#0b7a46] dark:bg-[#3fcb7a]"
                                ></span>
                                Terbit
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#f4f3f0] dark:bg-[#262626] text-[#5e5e5e] dark:text-[#a3a3a3]"
                            >
                                <span
                                    class="w-1.5 h-1.5 rounded-full bg-[#8c8b88] dark:bg-[#737373]"
                                ></span>
                                Draf
                            </span>
                        </td>

                        <!-- Diperbarui Column -->
                        <td
                            class="px-6 py-3.5 whitespace-nowrap text-xs text-gray-500 dark:text-[#a3a3a3] font-normal"
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
                                    class="p-1.5 rounded-lg text-gray-400 dark:text-[#a3a3a3] hover:text-[#0b8043] dark:hover:text-[#3fcb7a] hover:bg-emerald-50 dark:hover:bg-[#17321f] transition"
                                    title="Edit Artikel"
                                >
                                    <Pencil class="w-4 h-4" />
                                </Link>
                                <button
                                    type="button"
                                    @click="confirmDelete(item)"
                                    class="p-1.5 rounded-lg text-gray-400 dark:text-[#a3a3a3] hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40 transition"
                                    title="Hapus Artikel"
                                >
                                    <Trash2 class="w-4 h-4" />
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
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4 animate-in fade-in duration-150"
        >
            <div
                class="bg-white dark:bg-[#1c1c1c] rounded-3xl max-w-md w-full p-6 shadow-2xl border border-gray-100 dark:border-[#2e2e2e]"
            >
                <div
                    class="w-12 h-12 rounded-2xl bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400 flex items-center justify-center mb-4"
                >
                    <AlertCircle class="w-6 h-6" />
                </div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-[#f0efea] mb-1">
                    Hapus Artikel?
                </h3>
                <p class="text-sm text-gray-600 dark:text-[#a3a3a3] mb-6 leading-relaxed">
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
                        class="px-5 py-2.5 rounded-full text-sm font-semibold text-gray-600 dark:text-[#a3a3a3] hover:bg-gray-100 dark:hover:bg-[#262626] transition"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="executeDelete"
                        :disabled="isDeleting"
                        class="px-5 py-2.5 rounded-full text-sm font-semibold text-white bg-red-600 hover:bg-red-700 shadow-sm transition disabled:opacity-50"
                    >
                        {{ isDeleting ? "Menghapus..." : "Ya, Hapus" }}
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
