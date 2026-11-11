<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PillButton from '@/Components/UI/PillButton.vue';
import TiptapEditor from '@/Components/UI/TiptapEditor.vue';
import ArticleCoverUpload from '@/Components/Admin/ArticleCoverUpload.vue';
import ArticleCategoryModal from '@/Components/Admin/ArticleCategoryModal.vue';
import {
    ArrowLeft,
    Save,
    Send,
    Eye,
    Globe,
    FileText,
    Folder,
    User as UserIcon,
    Sparkles,
    CheckCircle2,
    Calendar,
    AlertCircle,
    Loader2,
    Settings2
} from 'lucide-vue-next';

interface ArtikelData {
    id: number;
    judul: string;
    slug: string;
    ringkasan: string | null;
    konten: string;
    kategori: string;
    foto_sampul_url: string | null;
    nama_penulis: string;
    status: 'terbit' | 'draf';
    published_at: string | null;
}

const props = defineProps<{
    mode: 'create' | 'edit';
    artikel?: ArtikelData | null;
    kategoriOptions: string[];
}>();

// Form State
const judul = ref(props.artikel?.judul || '');
const slug = ref(props.artikel?.slug || '');
const ringkasan = ref(props.artikel?.ringkasan || '');
const konten = ref(props.artikel?.konten || '');
const categoriesList = ref<string[]>([...props.kategoriOptions]);
const kategori = ref(props.artikel?.kategori || props.kategoriOptions[0] || 'Gizi Keluarga');
const isCategoryModalOpen = ref(false);
const namaPenulis = ref(props.artikel?.nama_penulis || 'Tim Redaksi');
const status = ref<'draf' | 'terbit'>(props.artikel?.status || 'draf');
const coverFile = ref<File | null>(null);
const initialCoverUrl = ref<string | null>(props.artikel?.foto_sampul_url || null);

// UI States
const isSubmitting = ref(false);
const errors = ref<Record<string, string>>({});
const isSlugManuallyEdited = ref(props.mode === 'edit');

// Auto slug generation from title
const generateSlug = (text: string) => {
    return text
        .toLowerCase()
        .replace(/[^\w\s-]/g, '')
        .trim()
        .replace(/\s+/g, '-');
};

watch(judul, (newTitle) => {
    if (!isSlugManuallyEdited.value) {
        slug.value = generateSlug(newTitle);
    }
});

const onSlugInput = () => {
    isSlugManuallyEdited.value = true;
    slug.value = generateSlug(slug.value);
};

// Handle category select
const selectKategori = (val: string) => {
    kategori.value = val;
};

const onCategoriesUpdated = (newCategories: string[]) => {
    categoriesList.value = newCategories;
    if (newCategories.length > 0 && !newCategories.includes(kategori.value)) {
        kategori.value = newCategories[0];
    }
};

const handleCoverSelected = (file: File | null) => {
    coverFile.value = file;
};

// Save handler (either draft or publish)
const submitForm = (targetStatus: 'draf' | 'terbit') => {
    status.value = targetStatus;
    errors.value = {};

    // Basic frontend validation
    if (!judul.value.trim()) {
        errors.value.judul = 'Judul artikel wajib diisi.';
    }
    if (!konten.value.trim() || konten.value === '<p></p>') {
        errors.value.konten = 'Konten artikel wajib diisi.';
    }

    if (!kategori.value) {
        errors.value.kategori = 'Kategori artikel wajib dipilih.';
    }

    if (Object.keys(errors.value).length > 0) {
        return;
    }

    isSubmitting.value = true;

    const formData = new FormData();
    formData.append('judul', judul.value.trim());
    formData.append('slug', slug.value.trim() || generateSlug(judul.value));
    formData.append('ringkasan', ringkasan.value.trim());
    formData.append('konten', konten.value);
    formData.append('kategori', kategori.value);
    formData.append('nama_penulis', namaPenulis.value.trim() || 'Tim Redaksi');
    formData.append('status', targetStatus);

    if (coverFile.value) {
        formData.append('foto_sampul', coverFile.value);
    }

    if (props.mode === 'create') {
        router.post(route('admin.articles.store'), formData, {
            forceFormData: true,
            onError: (errs) => {
                errors.value = errs;
                isSubmitting.value = false;
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        });
    } else if (props.artikel) {
        // Laravel PUT method spoofing via POST for multipart/form-data
        formData.append('_method', 'PUT');
        router.post(route('admin.articles.update', props.artikel.id), formData, {
            forceFormData: true,
            onError: (errs) => {
                errors.value = errs;
                isSubmitting.value = false;
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        });
    }
};
</script>

<template>
    <AdminLayout :title="mode === 'create' ? 'Tulis Artikel' : 'Edit Artikel'">
        <Head :title="(mode === 'create' ? 'Tulis Artikel' : 'Edit Artikel') + ' - CarePlate Admin'" />

        <div class="space-y-6 max-w-7xl mx-auto pb-16">
            <!-- Navigation & Actions Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-[#1c1c1c] p-4 sm:p-5 rounded-2xl border border-gray-100 dark:border-[#2e2e2e] shadow-xs">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('admin.articles')"
                        class="p-2 rounded-xl text-gray-500 dark:text-[#a3a3a3] hover:text-gray-800 dark:hover:text-[#f0efea] hover:bg-gray-100 dark:hover:bg-[#262626] transition"
                        title="Kembali ke Daftar Artikel"
                    >
                        <ArrowLeft class="w-5 h-5" />
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-[#737373]">
                                {{ mode === 'create' ? 'Artikel Baru' : 'Perbarui Artikel' }}
                            </span>
                            <span
                                :class="[
                                    'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold',
                                    status === 'terbit' ? 'bg-[#e6f6ec] dark:bg-[#17321f] text-[#0b7a46] dark:text-[#3fcb7a]' : 'bg-gray-100 dark:bg-[#262626] text-gray-600 dark:text-[#a3a3a3]'
                                ]"
                            >
                                {{ status === 'terbit' ? 'Siap Terbit' : 'Draf' }}
                            </span>
                        </div>
                        <h1 class="text-xl sm:text-2xl font-bold text-[#191919] dark:text-[#f0efea] tracking-tight line-clamp-1">
                            {{ judul || (mode === 'create' ? 'Tulis Artikel Baru' : 'Edit Artikel') }}
                        </h1>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center gap-2.5 shrink-0">
                    <button
                        type="button"
                        @click="submitForm('draf')"
                        :disabled="isSubmitting"
                        class="px-4 py-2.5 rounded-full border border-gray-200 dark:border-[#3d3d3d] text-sm font-semibold text-gray-700 dark:text-[#f0efea] hover:bg-gray-50 dark:hover:bg-[#262626] transition flex items-center gap-1.5 disabled:opacity-50 cursor-pointer"
                    >
                        <Save class="w-4 h-4 text-gray-500 dark:text-[#a3a3a3]" />
                        <span>Simpan Draf</span>
                    </button>
                    <button
                        type="button"
                        @click="submitForm('terbit')"
                        :disabled="isSubmitting"
                        class="px-5 py-2.5 rounded-full bg-[#0b8043] dark:bg-[#3fcb7a] text-white dark:text-[#121212] text-sm font-semibold hover:bg-[#096836] dark:hover:bg-[#34aa66] shadow-sm transition flex items-center gap-1.5 disabled:opacity-50 cursor-pointer"
                    >
                        <Loader2 v-if="isSubmitting" class="w-4 h-4 animate-spin" />
                        <Send v-else class="w-4 h-4" />
                        <span>Terbitkan Artikel</span>
                    </button>
                </div>
            </div>

            <!-- Form Body: Grid 2 Columns -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- Main Content Column (Col 8) -->
                <div class="lg:col-span-8 space-y-6">
                    <!-- Title & Slug Card -->
                    <div class="bg-white dark:bg-[#1c1c1c] p-6 rounded-3xl border border-gray-100 dark:border-[#2e2e2e] shadow-xs space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-[#f0efea] uppercase tracking-wider mb-2">
                                Judul Artikel <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="judul"
                                type="text"
                                placeholder="Contoh: Satu Meja, Satu Piring: Panduan Makan Sehat Keluarga"
                                class="w-full px-4 py-3 text-lg font-medium rounded-2xl border border-gray-200 dark:border-[#3d3d3d] bg-white dark:bg-[#262626] text-gray-900 dark:text-[#f0efea] placeholder-gray-400 dark:placeholder-[#737373] focus:outline-none focus:border-[#0b8043] dark:focus:border-[#3fcb7a] focus:ring-2 focus:ring-[#0b8043]/15 transition"
                            />
                            <p v-if="errors.judul" class="text-xs text-red-500 mt-1 font-medium">
                                {{ errors.judul }}
                            </p>
                        </div>

                        <!-- Slug URL helper -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 dark:text-[#a3a3a3] uppercase tracking-wider mb-1.5 flex items-center gap-1">
                                <Globe class="w-3.5 h-3.5 text-gray-400" />
                                <span>Slug URL (Permalink)</span>
                            </label>
                            <div class="flex items-center rounded-xl border border-gray-200 dark:border-[#3d3d3d] bg-gray-50/70 dark:bg-[#262626] overflow-hidden px-3 py-2 text-xs text-gray-600 dark:text-[#a3a3a3] focus-within:border-[#0b8043] dark:focus-within:border-[#3fcb7a] focus-within:bg-white dark:focus-within:bg-[#1c1c1c] transition">
                                <span class="text-gray-400 dark:text-[#737373] select-none mr-1">/artikel/</span>
                                <input
                                    v-model="slug"
                                    type="text"
                                    placeholder="slug-artikel"
                                    @input="onSlugInput"
                                    class="w-full bg-transparent border-0 p-0 text-xs font-medium text-gray-800 dark:text-[#f0efea] focus:outline-none focus:ring-0"
                                />
                            </div>
                            <p v-if="errors.slug" class="text-xs text-red-500 mt-1 font-medium">
                                {{ errors.slug }}
                            </p>
                        </div>

                        <!-- Excerpt / Ringkasan -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-[#f0efea] uppercase tracking-wider mb-1.5">
                                Ringkasan Singkat (Lead / Excerpt)
                            </label>
                            <textarea
                                v-model="ringkasan"
                                rows="2"
                                placeholder="Tuliskan 1-2 kalimat ringkasan tentang isi artikel untuk ditampilkan di kartu cuplikan..."
                                class="w-full px-4 py-2.5 text-sm rounded-xl border border-gray-200 dark:border-[#3d3d3d] bg-white dark:bg-[#262626] text-gray-900 dark:text-[#f0efea] placeholder-gray-400 dark:placeholder-[#737373] focus:outline-none focus:border-[#0b8043] dark:focus:border-[#3fcb7a] focus:ring-2 focus:ring-[#0b8043]/15 transition"
                            ></textarea>
                            <p v-if="errors.ringkasan" class="text-xs text-red-500 mt-1 font-medium">
                                {{ errors.ringkasan }}
                            </p>
                        </div>
                    </div>

                    <!-- TipTap Rich Text Editor Card -->
                    <div class="bg-white dark:bg-[#1c1c1c] p-6 rounded-3xl border border-gray-100 dark:border-[#2e2e2e] shadow-xs space-y-3">
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-semibold text-gray-700 dark:text-[#f0efea] uppercase tracking-wider">
                                Konten Artikel Lengkap <span class="text-red-500">*</span>
                            </label>
                            <span class="text-[11px] text-gray-400 dark:text-[#a3a3a3]">
                                Mendukung heading, gambar inline, & video YouTube
                            </span>
                        </div>

                        <TiptapEditor
                            v-model="konten"
                            placeholder="Mulai tulis artikel lengkap di sini. Gunakan toolbar untuk styling, insert gambar atau sematkan video YouTube..."
                        />
                        <p v-if="errors.konten" class="text-xs text-red-500 mt-1 font-medium">
                            {{ errors.konten }}
                        </p>
                    </div>
                </div>

                <!-- Sidebar Settings Column (Col 4) -->
                <div class="lg:col-span-4 space-y-6">
                    <!-- Card 1: Cover Foto Sampul -->
                    <div class="bg-white dark:bg-[#1c1c1c] p-6 rounded-3xl border border-gray-100 dark:border-[#2e2e2e] shadow-xs space-y-3">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-[#f0efea] flex items-center gap-2">
                            <FileText class="w-4 h-4 text-[#0b8043] dark:text-[#3fcb7a]" />
                            <span>Foto Sampul (Cover Banner)</span>
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-[#a3a3a3]">
                            Foto ini akan dijadikan thumbnail di daftar artikel dan banner di halaman baca artikel.
                        </p>
                        <ArticleCoverUpload
                            :initial-preview-url="initialCoverUrl"
                            :error="errors.foto_sampul"
                            @file-selected="handleCoverSelected"
                        />
                    </div>

                    <!-- Card 2: Pengaturan Publikasi & Status -->
                    <div class="bg-white dark:bg-[#1c1c1c] p-6 rounded-3xl border border-gray-100 dark:border-[#2e2e2e] shadow-xs space-y-4">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-[#f0efea] flex items-center gap-2">
                            <CheckCircle2 class="w-4 h-4 text-[#0b8043] dark:text-[#3fcb7a]" />
                            <span>Status Publikasi</span>
                        </h3>

                        <div class="grid grid-cols-2 gap-2 p-1 bg-gray-100 dark:bg-[#262626] rounded-2xl">
                            <button
                                type="button"
                                @click="status = 'draf'"
                                :class="[
                                    'py-2 px-3 rounded-xl text-xs font-semibold transition cursor-pointer',
                                    status === 'draf' ? 'bg-white dark:bg-[#1c1c1c] text-gray-900 dark:text-[#f0efea] shadow-xs' : 'text-gray-500 dark:text-[#a3a3a3] hover:text-gray-800 dark:hover:text-[#f0efea]'
                                ]"
                            >
                                Draf
                            </button>
                            <button
                                type="button"
                                @click="status = 'terbit'"
                                :class="[
                                    'py-2 px-3 rounded-xl text-xs font-semibold transition cursor-pointer',
                                    status === 'terbit' ? 'bg-[#0b8043] dark:bg-[#3fcb7a] text-white dark:text-[#121212] shadow-xs' : 'text-gray-500 dark:text-[#a3a3a3] hover:text-gray-800 dark:hover:text-[#f0efea]'
                                ]"
                            >
                                Terbit
                            </button>
                        </div>

                        <div v-if="props.artikel?.published_at" class="text-xs text-gray-500 dark:text-[#a3a3a3] flex items-center gap-1.5 pt-1 border-t border-gray-100 dark:border-[#2e2e2e]">
                            <Calendar class="w-3.5 h-3.5 text-gray-400" />
                            <span>Pertama kali terbit: {{ props.artikel.published_at }}</span>
                        </div>
                    </div>

                    <!-- Card 3: Kategori & Penulis -->
                    <div class="bg-white dark:bg-[#1c1c1c] p-6 rounded-3xl border border-gray-100 dark:border-[#2e2e2e] shadow-xs space-y-4">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-[#f0efea] flex items-center gap-2">
                            <Folder class="w-4 h-4 text-[#0b8043] dark:text-[#3fcb7a]" />
                            <span>Kategori & Penulis</span>
                        </h3>

                        <!-- Kategori Selection -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-xs font-semibold text-gray-700 dark:text-[#f0efea] uppercase tracking-wider">
                                    Kategori
                                </label>
                                <button
                                    type="button"
                                    @click="isCategoryModalOpen = true"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#0b8043] dark:text-[#3fcb7a] hover:text-[#086637] transition cursor-pointer"
                                    title="Kelola Daftar Kategori"
                                >
                                    <Settings2 class="w-3.5 h-3.5" />
                                    <span>Kelola Kategori</span>
                                </button>
                            </div>

                            <div class="flex flex-wrap gap-1.5 mb-2">
                                <button
                                    v-for="opt in categoriesList"
                                    :key="opt"
                                    type="button"
                                    @click="selectKategori(opt)"
                                    :class="[
                                        'px-3 py-1.5 rounded-full text-xs font-medium transition cursor-pointer',
                                        kategori === opt
                                            ? 'bg-[#0b8043] dark:bg-[#3fcb7a] text-white dark:text-[#121212] shadow-xs font-semibold'
                                            : 'bg-gray-100 dark:bg-[#262626] text-gray-600 dark:text-[#a3a3a3] hover:bg-gray-200 dark:hover:bg-[#3d3d3d]'
                                    ]"
                                >
                                    {{ opt }}
                                </button>
                            </div>
                            <p v-if="errors.kategori" class="text-xs text-red-500 mt-1 font-medium">
                                {{ errors.kategori }}
                            </p>
                        </div>

                        <!-- Penulis Field -->
                        <div class="pt-2 border-t border-gray-100 dark:border-[#2e2e2e]">
                            <label class="block text-xs font-semibold text-gray-700 dark:text-[#f0efea] uppercase tracking-wider mb-1.5 flex items-center gap-1">
                                <UserIcon class="w-3.5 h-3.5 text-gray-400" />
                                <span>Nama Penulis</span>
                            </label>
                            <input
                                v-model="namaPenulis"
                                type="text"
                                placeholder="Contoh: Tim Redaksi CarePlate"
                                class="w-full px-3 py-2 text-xs rounded-xl border border-gray-200 dark:border-[#3d3d3d] bg-white dark:bg-[#262626] text-gray-900 dark:text-[#f0efea] placeholder-gray-400 dark:placeholder-[#737373] focus:outline-none focus:border-[#0b8043] dark:focus:border-[#3fcb7a] focus:ring-1 focus:ring-[#0b8043]"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Kelola Kategori Artikel -->
        <ArticleCategoryModal
            :is-open="isCategoryModalOpen"
            @close="isCategoryModalOpen = false"
            @updated="onCategoriesUpdated"
        />
    </AdminLayout>
</template>
