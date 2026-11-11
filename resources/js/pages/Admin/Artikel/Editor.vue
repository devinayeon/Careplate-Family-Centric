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
    Settings2,
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
const kategori = ref(
    props.artikel?.kategori || props.kategoriOptions[0] || 'Gizi Keluarga',
);
const isCategoryModalOpen = ref(false);
const namaPenulis = ref(props.artikel?.nama_penulis || 'Tim Redaksi');
const status = ref<'draf' | 'terbit'>(props.artikel?.status || 'draf');
const coverFile = ref<File | null>(null);
const initialCoverUrl = ref<string | null>(
    props.artikel?.foto_sampul_url || null,
);

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
        router.post(
            route('admin.articles.update', props.artikel.id),
            formData,
            {
                forceFormData: true,
                onError: (errs) => {
                    errors.value = errs;
                    isSubmitting.value = false;
                },
                onFinish: () => {
                    isSubmitting.value = false;
                },
            },
        );
    }
};
</script>

<template>
    <AdminLayout :title="mode === 'create' ? 'Tulis Artikel' : 'Edit Artikel'">
        <Head
            :title="
                (mode === 'create' ? 'Tulis Artikel' : 'Edit Artikel') +
                ' - CarePlate Admin'
            "
        />

        <div class="mx-auto max-w-7xl space-y-6 pb-16">
            <!-- Navigation & Actions Bar -->
            <div
                class="flex flex-col gap-4 rounded-2xl border border-gray-100 bg-white p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between sm:p-5 dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
            >
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('admin.articles')"
                        class="rounded-xl p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-800 dark:text-[#a3a3a3] dark:hover:bg-[#262626] dark:hover:text-[#f0efea]"
                        title="Kembali ke Daftar Artikel"
                    >
                        <ArrowLeft class="h-5 w-5" />
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <span
                                class="text-xs font-semibold tracking-wider text-gray-400 uppercase dark:text-[#737373]"
                            >
                                {{
                                    mode === 'create'
                                        ? 'Artikel Baru'
                                        : 'Perbarui Artikel'
                                }}
                            </span>
                            <span
                                :class="[
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                    status === 'terbit'
                                        ? 'bg-[#e6f6ec] text-[#0b7a46] dark:bg-[#17321f] dark:text-[#3fcb7a]'
                                        : 'bg-gray-100 text-gray-600 dark:bg-[#262626] dark:text-[#a3a3a3]',
                                ]"
                            >
                                {{
                                    status === 'terbit' ? 'Siap Terbit' : 'Draf'
                                }}
                            </span>
                        </div>
                        <h1
                            class="line-clamp-1 text-xl font-bold tracking-tight text-[#191919] sm:text-2xl dark:text-[#f0efea]"
                        >
                            {{
                                judul ||
                                (mode === 'create'
                                    ? 'Tulis Artikel Baru'
                                    : 'Edit Artikel')
                            }}
                        </h1>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex shrink-0 items-center gap-2.5">
                    <button
                        type="button"
                        @click="submitForm('draf')"
                        :disabled="isSubmitting"
                        class="flex cursor-pointer items-center gap-1.5 rounded-full border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:opacity-50 dark:border-[#3d3d3d] dark:text-[#f0efea] dark:hover:bg-[#262626]"
                    >
                        <Save
                            class="h-4 w-4 text-gray-500 dark:text-[#a3a3a3]"
                        />
                        <span>Simpan Draf</span>
                    </button>
                    <button
                        type="button"
                        @click="submitForm('terbit')"
                        :disabled="isSubmitting"
                        class="flex cursor-pointer items-center gap-1.5 rounded-full bg-[#0b8043] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#096836] disabled:opacity-50 dark:bg-[#3fcb7a] dark:text-[#121212] dark:hover:bg-[#34aa66]"
                    >
                        <Loader2
                            v-if="isSubmitting"
                            class="h-4 w-4 animate-spin"
                        />
                        <Send v-else class="h-4 w-4" />
                        <span>Terbitkan Artikel</span>
                    </button>
                </div>
            </div>

            <!-- Form Body: Grid 2 Columns -->
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
                <!-- Main Content Column (Col 8) -->
                <div class="space-y-6 lg:col-span-8">
                    <!-- Title & Slug Card -->
                    <div
                        class="space-y-4 rounded-3xl border border-gray-100 bg-white p-6 shadow-xs dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
                    >
                        <div>
                            <label
                                class="mb-2 block text-xs font-semibold tracking-wider text-gray-700 uppercase dark:text-[#f0efea]"
                            >
                                Judul Artikel
                                <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="judul"
                                type="text"
                                placeholder="Contoh: Satu Meja, Satu Piring: Panduan Makan Sehat Keluarga"
                                class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-lg font-medium text-gray-900 placeholder-gray-400 transition focus:border-[#0b8043] focus:ring-2 focus:ring-[#0b8043]/15 focus:outline-none dark:border-[#3d3d3d] dark:bg-[#262626] dark:text-[#f0efea] dark:placeholder-[#737373] dark:focus:border-[#3fcb7a]"
                            />
                            <p
                                v-if="errors.judul"
                                class="mt-1 text-xs font-medium text-red-500"
                            >
                                {{ errors.judul }}
                            </p>
                        </div>

                        <!-- Slug URL helper -->
                        <div>
                            <label
                                class="mb-1.5 block flex items-center gap-1 text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-[#a3a3a3]"
                            >
                                <Globe class="h-3.5 w-3.5 text-gray-400" />
                                <span>Slug URL (Permalink)</span>
                            </label>
                            <div
                                class="flex items-center overflow-hidden rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2 text-xs text-gray-600 transition focus-within:border-[#0b8043] focus-within:bg-white dark:border-[#3d3d3d] dark:bg-[#262626] dark:text-[#a3a3a3] dark:focus-within:border-[#3fcb7a] dark:focus-within:bg-[#1c1c1c]"
                            >
                                <span
                                    class="mr-1 text-gray-400 select-none dark:text-[#737373]"
                                    >/artikel/</span
                                >
                                <input
                                    v-model="slug"
                                    type="text"
                                    placeholder="slug-artikel"
                                    @input="onSlugInput"
                                    class="w-full border-0 bg-transparent p-0 text-xs font-medium text-gray-800 focus:ring-0 focus:outline-none dark:text-[#f0efea]"
                                />
                            </div>
                            <p
                                v-if="errors.slug"
                                class="mt-1 text-xs font-medium text-red-500"
                            >
                                {{ errors.slug }}
                            </p>
                        </div>

                        <!-- Excerpt / Ringkasan -->
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-semibold tracking-wider text-gray-700 uppercase dark:text-[#f0efea]"
                            >
                                Ringkasan Singkat (Lead / Excerpt)
                            </label>
                            <textarea
                                v-model="ringkasan"
                                rows="2"
                                placeholder="Tuliskan 1-2 kalimat ringkasan tentang isi artikel untuk ditampilkan di kartu cuplikan..."
                                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 transition focus:border-[#0b8043] focus:ring-2 focus:ring-[#0b8043]/15 focus:outline-none dark:border-[#3d3d3d] dark:bg-[#262626] dark:text-[#f0efea] dark:placeholder-[#737373] dark:focus:border-[#3fcb7a]"
                            ></textarea>
                            <p
                                v-if="errors.ringkasan"
                                class="mt-1 text-xs font-medium text-red-500"
                            >
                                {{ errors.ringkasan }}
                            </p>
                        </div>
                    </div>

                    <!-- TipTap Rich Text Editor Card -->
                    <div
                        class="space-y-3 rounded-3xl border border-gray-100 bg-white p-6 shadow-xs dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
                    >
                        <div class="mb-1 flex items-center justify-between">
                            <label
                                class="block text-xs font-semibold tracking-wider text-gray-700 uppercase dark:text-[#f0efea]"
                            >
                                Konten Artikel Lengkap
                                <span class="text-red-500">*</span>
                            </label>
                            <span
                                class="text-[11px] text-gray-400 dark:text-[#a3a3a3]"
                            >
                                Mendukung heading, gambar inline, & video
                                YouTube
                            </span>
                        </div>

                        <TiptapEditor
                            v-model="konten"
                            placeholder="Mulai tulis artikel lengkap di sini. Gunakan toolbar untuk styling, insert gambar atau sematkan video YouTube..."
                        />
                        <p
                            v-if="errors.konten"
                            class="mt-1 text-xs font-medium text-red-500"
                        >
                            {{ errors.konten }}
                        </p>
                    </div>
                </div>

                <!-- Sidebar Settings Column (Col 4) -->
                <div class="space-y-6 lg:col-span-4">
                    <!-- Card 1: Cover Foto Sampul -->
                    <div
                        class="space-y-3 rounded-3xl border border-gray-100 bg-white p-6 shadow-xs dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
                    >
                        <h3
                            class="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-[#f0efea]"
                        >
                            <FileText
                                class="h-4 w-4 text-[#0b8043] dark:text-[#3fcb7a]"
                            />
                            <span>Foto Sampul (Cover Banner)</span>
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-[#a3a3a3]">
                            Foto ini akan dijadikan thumbnail di daftar artikel
                            dan banner di halaman baca artikel.
                        </p>
                        <ArticleCoverUpload
                            :initial-preview-url="initialCoverUrl"
                            :error="errors.foto_sampul"
                            @file-selected="handleCoverSelected"
                        />
                    </div>

                    <!-- Card 2: Pengaturan Publikasi & Status -->
                    <div
                        class="space-y-4 rounded-3xl border border-gray-100 bg-white p-6 shadow-xs dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
                    >
                        <h3
                            class="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-[#f0efea]"
                        >
                            <CheckCircle2
                                class="h-4 w-4 text-[#0b8043] dark:text-[#3fcb7a]"
                            />
                            <span>Status Publikasi</span>
                        </h3>

                        <div
                            class="grid grid-cols-2 gap-2 rounded-2xl bg-gray-100 p-1 dark:bg-[#262626]"
                        >
                            <button
                                type="button"
                                @click="status = 'draf'"
                                :class="[
                                    'cursor-pointer rounded-xl px-3 py-2 text-xs font-semibold transition',
                                    status === 'draf'
                                        ? 'bg-white text-gray-900 shadow-xs dark:bg-[#1c1c1c] dark:text-[#f0efea]'
                                        : 'text-gray-500 hover:text-gray-800 dark:text-[#a3a3a3] dark:hover:text-[#f0efea]',
                                ]"
                            >
                                Draf
                            </button>
                            <button
                                type="button"
                                @click="status = 'terbit'"
                                :class="[
                                    'cursor-pointer rounded-xl px-3 py-2 text-xs font-semibold transition',
                                    status === 'terbit'
                                        ? 'bg-[#0b8043] text-white shadow-xs dark:bg-[#3fcb7a] dark:text-[#121212]'
                                        : 'text-gray-500 hover:text-gray-800 dark:text-[#a3a3a3] dark:hover:text-[#f0efea]',
                                ]"
                            >
                                Terbit
                            </button>
                        </div>

                        <div
                            v-if="props.artikel?.published_at"
                            class="flex items-center gap-1.5 border-t border-gray-100 pt-1 text-xs text-gray-500 dark:border-[#2e2e2e] dark:text-[#a3a3a3]"
                        >
                            <Calendar class="h-3.5 w-3.5 text-gray-400" />
                            <span
                                >Pertama kali terbit:
                                {{ props.artikel.published_at }}</span
                            >
                        </div>
                    </div>

                    <!-- Card 3: Kategori & Penulis -->
                    <div
                        class="space-y-4 rounded-3xl border border-gray-100 bg-white p-6 shadow-xs dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
                    >
                        <h3
                            class="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-[#f0efea]"
                        >
                            <Folder
                                class="h-4 w-4 text-[#0b8043] dark:text-[#3fcb7a]"
                            />
                            <span>Kategori & Penulis</span>
                        </h3>

                        <!-- Kategori Selection -->
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <label
                                    class="block text-xs font-semibold tracking-wider text-gray-700 uppercase dark:text-[#f0efea]"
                                >
                                    Kategori
                                </label>
                                <button
                                    type="button"
                                    @click="isCategoryModalOpen = true"
                                    class="inline-flex cursor-pointer items-center gap-1 text-[11px] font-semibold text-[#0b8043] transition hover:text-[#086637] dark:text-[#3fcb7a]"
                                    title="Kelola Daftar Kategori"
                                >
                                    <Settings2 class="h-3.5 w-3.5" />
                                    <span>Kelola Kategori</span>
                                </button>
                            </div>

                            <div class="mb-2 flex flex-wrap gap-1.5">
                                <button
                                    v-for="opt in categoriesList"
                                    :key="opt"
                                    type="button"
                                    @click="selectKategori(opt)"
                                    :class="[
                                        'cursor-pointer rounded-full px-3 py-1.5 text-xs font-medium transition',
                                        kategori === opt
                                            ? 'bg-[#0b8043] font-semibold text-white shadow-xs dark:bg-[#3fcb7a] dark:text-[#121212]'
                                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-[#262626] dark:text-[#a3a3a3] dark:hover:bg-[#3d3d3d]',
                                    ]"
                                >
                                    {{ opt }}
                                </button>
                            </div>
                            <p
                                v-if="errors.kategori"
                                class="mt-1 text-xs font-medium text-red-500"
                            >
                                {{ errors.kategori }}
                            </p>
                        </div>

                        <!-- Penulis Field -->
                        <div
                            class="border-t border-gray-100 pt-2 dark:border-[#2e2e2e]"
                        >
                            <label
                                class="mb-1.5 block flex items-center gap-1 text-xs font-semibold tracking-wider text-gray-700 uppercase dark:text-[#f0efea]"
                            >
                                <UserIcon class="h-3.5 w-3.5 text-gray-400" />
                                <span>Nama Penulis</span>
                            </label>
                            <input
                                v-model="namaPenulis"
                                type="text"
                                placeholder="Contoh: Tim Redaksi CarePlate"
                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs text-gray-900 placeholder-gray-400 focus:border-[#0b8043] focus:ring-1 focus:ring-[#0b8043] focus:outline-none dark:border-[#3d3d3d] dark:bg-[#262626] dark:text-[#f0efea] dark:placeholder-[#737373] dark:focus:border-[#3fcb7a]"
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
