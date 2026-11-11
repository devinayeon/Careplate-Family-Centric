<script setup lang="ts">
import { ref, watch } from 'vue';
import {
    Folder,
    Plus,
    Pencil,
    Trash2,
    Check,
    X,
    Loader2,
    AlertCircle,
    CheckCircle2,
} from 'lucide-vue-next';

export interface CategoryItem {
    id: number;
    nama: string;
    slug: string;
    deskripsi?: string | null;
    artikels_count: number;
}

const props = defineProps<{
    isOpen: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'updated', categoryNames: string[]): void;
}>();

const categories = ref<CategoryItem[]>([]);
const isLoading = ref(false);
const isSubmitting = ref(false);
const generalError = ref<string | null>(null);
const successMessage = ref<string | null>(null);

// New Category Input
const newCategoryName = ref('');
const newCategoryError = ref<string | null>(null);

// Editing Category State
const editingId = ref<number | null>(null);
const editingName = ref('');
const editingError = ref<string | null>(null);

const fetchCategories = async () => {
    try {
        isLoading.value = true;
        generalError.value = null;
        const res = await fetch(route('admin.article-categories.index'), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const data = await res.json();
        if (data?.categories) {
            categories.value = data.categories;
            emit(
                'updated',
                categories.value.map((c) => c.nama),
            );
        }
    } catch (err) {
        console.error('Failed to fetch categories:', err);
        generalError.value = 'Gagal memuat daftar kategori.';
    } finally {
        isLoading.value = false;
    }
};

watch(
    () => props.isOpen,
    (open) => {
        if (open) {
            generalError.value = null;
            successMessage.value = null;
            newCategoryName.value = '';
            newCategoryError.value = null;
            editingId.value = null;
            fetchCategories();
        }
    },
);

const addCategory = async () => {
    const trimmed = newCategoryName.value.trim();
    if (!trimmed) {
        newCategoryError.value = 'Nama kategori wajib diisi.';
        return;
    }

    try {
        isSubmitting.value = true;
        newCategoryError.value = null;
        generalError.value = null;

        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content || '';

        const res = await fetch(route('admin.article-categories.store'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ nama: trimmed }),
        });

        const data = await res.json();

        if (!res.ok) {
            if (data.errors?.nama) {
                newCategoryError.value = data.errors.nama[0];
            } else {
                newCategoryError.value =
                    data.message || 'Gagal menambahkan kategori.';
            }
            return;
        }

        newCategoryName.value = '';
        successMessage.value = `Kategori "${trimmed}" berhasil ditambahkan!`;
        setTimeout(() => (successMessage.value = null), 3000);
        await fetchCategories();
    } catch (err) {
        console.error(err);
        newCategoryError.value = 'Terjadi kesalahan sistem.';
    } finally {
        isSubmitting.value = false;
    }
};

const startEditing = (cat: CategoryItem) => {
    editingId.value = cat.id;
    editingName.value = cat.nama;
    editingError.value = null;
};

const cancelEditing = () => {
    editingId.value = null;
    editingName.value = '';
    editingError.value = null;
};

const saveEditing = async (cat: CategoryItem) => {
    const trimmed = editingName.value.trim();
    if (!trimmed) {
        editingError.value = 'Nama kategori tidak boleh kosong.';
        return;
    }

    if (trimmed === cat.nama) {
        cancelEditing();
        return;
    }

    try {
        isSubmitting.value = true;
        editingError.value = null;

        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content || '';

        const res = await fetch(
            route('admin.article-categories.update', cat.id),
            {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ nama: trimmed }),
            },
        );

        const data = await res.json();

        if (!res.ok) {
            if (data.errors?.nama) {
                editingError.value = data.errors.nama[0];
            } else {
                editingError.value = data.message || 'Gagal mengubah kategori.';
            }
            return;
        }

        cancelEditing();
        successMessage.value = `Kategori berhasil diubah menjadi "${trimmed}"!`;
        setTimeout(() => (successMessage.value = null), 3000);
        await fetchCategories();
    } catch (err) {
        console.error(err);
        editingError.value = 'Terjadi kesalahan sistem.';
    } finally {
        isSubmitting.value = false;
    }
};

const deleteCategory = async (cat: CategoryItem) => {
    if (cat.artikels_count > 0) {
        alert(
            `Kategori "${cat.nama}" tidak dapat dihapus karena masih digunakan oleh ${cat.artikels_count} artikel. Silakan ubah kategori artikel tersebut terlebih dahulu.`,
        );
        return;
    }

    if (!confirm(`Apakah Anda yakin ingin menghapus kategori "${cat.nama}"?`)) {
        return;
    }

    try {
        isSubmitting.value = true;
        generalError.value = null;

        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content || '';

        const res = await fetch(
            route('admin.article-categories.destroy', cat.id),
            {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
            },
        );

        const data = await res.json();

        if (!res.ok) {
            generalError.value = data.message || 'Gagal menghapus kategori.';
            return;
        }

        successMessage.value = `Kategori "${cat.nama}" berhasil dihapus!`;
        setTimeout(() => (successMessage.value = null), 3000);
        await fetchCategories();
    } catch (err) {
        console.error(err);
        generalError.value = 'Terjadi kesalahan saat menghapus.';
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <div
        v-if="isOpen"
        class="animate-in fade-in fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs duration-150"
    >
        <div
            class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-2xl dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
        >
            <!-- Header -->
            <div
                class="flex shrink-0 items-center justify-between border-b border-gray-100 bg-white p-6 dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-50 text-[#0b8043] dark:bg-[#17321f] dark:text-[#3fcb7a]"
                    >
                        <Folder class="h-5 w-5" />
                    </div>
                    <div>
                        <h3
                            class="text-lg font-bold tracking-tight text-gray-900 dark:text-[#f0efea]"
                        >
                            Kelola Kategori Artikel
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-[#a3a3a3]">
                            Tambah, ubah nama, atau hapus kategori artikel
                            CarePlate
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="emit('close')"
                    class="cursor-pointer rounded-xl p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:text-[#a3a3a3] dark:hover:bg-[#262626] dark:hover:text-[#f0efea]"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Body -->
            <div class="space-y-6 overflow-y-auto p-6">
                <!-- Notifications -->
                <div
                    v-if="successMessage"
                    class="animate-in fade-in flex items-center gap-2 rounded-2xl bg-[#e6f6ec] p-3.5 text-xs font-semibold text-[#0b7a46] dark:bg-[#17321f] dark:text-[#3fcb7a]"
                >
                    <CheckCircle2 class="h-4 w-4 shrink-0" />
                    <span>{{ successMessage }}</span>
                </div>

                <div
                    v-if="generalError"
                    class="animate-in fade-in flex items-center gap-2 rounded-2xl bg-red-50 p-3.5 text-xs font-semibold text-red-600 dark:bg-red-950/40 dark:text-red-400"
                >
                    <AlertCircle class="h-4 w-4 shrink-0" />
                    <span>{{ generalError }}</span>
                </div>

                <!-- Section 1: Tambah Kategori Form -->
                <div
                    class="space-y-2 rounded-2xl border border-gray-200/80 bg-[#f8f9fa] p-4 dark:border-[#2e2e2e] dark:bg-[#262626]"
                >
                    <label
                        class="block text-xs font-semibold tracking-wider text-gray-700 uppercase dark:text-[#f0efea]"
                    >
                        Tambah Kategori Baru
                    </label>
                    <div class="flex items-center gap-2">
                        <input
                            v-model="newCategoryName"
                            type="text"
                            placeholder="Contoh: Menu Diet Rendah Garam"
                            class="grow rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-xs font-medium text-gray-900 placeholder-gray-400 transition focus:border-[#0b8043] focus:ring-2 focus:ring-[#0b8043]/15 focus:outline-none dark:border-[#3d3d3d] dark:bg-[#1c1c1c] dark:text-[#f0efea] dark:placeholder-[#737373] dark:focus:border-[#3fcb7a]"
                            @keyup.enter="addCategory"
                        />
                        <button
                            type="button"
                            @click="addCategory"
                            :disabled="isSubmitting || !newCategoryName.trim()"
                            class="flex shrink-0 cursor-pointer items-center gap-1.5 rounded-xl bg-[#0b8043] px-4 py-2.5 text-xs font-semibold text-white shadow-xs transition hover:bg-[#096836] disabled:opacity-40 dark:bg-[#3fcb7a] dark:text-[#121212] dark:hover:bg-[#34aa66]"
                        >
                            <Loader2
                                v-if="isSubmitting"
                                class="h-3.5 w-3.5 animate-spin"
                            />
                            <Plus v-else class="h-3.5 w-3.5" />
                            <span>Tambah</span>
                        </button>
                    </div>
                    <p
                        v-if="newCategoryError"
                        class="text-xs font-medium text-red-500"
                    >
                        {{ newCategoryError }}
                    </p>
                </div>

                <!-- Section 2: Daftar Kategori -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span
                            class="text-xs font-semibold tracking-wider text-gray-700 uppercase dark:text-[#a3a3a3]"
                        >
                            Daftar Kategori Tersedia ({{ categories.length }})
                        </span>
                        <Loader2
                            v-if="isLoading"
                            class="h-3.5 w-3.5 animate-spin text-gray-400"
                        />
                    </div>

                    <div
                        v-if="categories.length === 0 && !isLoading"
                        class="p-8 text-center text-xs text-gray-400 dark:text-[#737373]"
                    >
                        Belum ada kategori yang dibuat.
                    </div>

                    <div class="max-h-60 space-y-2 overflow-y-auto pr-1">
                        <div
                            v-for="cat in categories"
                            :key="cat.id"
                            class="flex items-center justify-between rounded-2xl border border-gray-100 bg-white p-3 transition hover:bg-gray-50/70 dark:border-[#2e2e2e] dark:bg-[#1c1c1c] dark:hover:bg-[#262626]"
                        >
                            <!-- View Mode -->
                            <template v-if="editingId !== cat.id">
                                <div
                                    class="flex min-w-0 items-center gap-2.5 pr-2"
                                >
                                    <span
                                        class="truncate text-xs font-semibold text-gray-800 dark:text-[#f0efea]"
                                    >
                                        {{ cat.nama }}
                                    </span>
                                    <span
                                        class="shrink-0 rounded-full bg-[#f0f4f1] px-2 py-0.5 text-[10px] font-medium text-[#2d4b38] dark:bg-[#262626] dark:text-[#3fcb7a]"
                                    >
                                        {{ cat.artikels_count }} artikel
                                    </span>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <button
                                        type="button"
                                        @click="startEditing(cat)"
                                        class="cursor-pointer rounded-lg p-1.5 text-gray-400 transition hover:bg-emerald-50 hover:text-[#0b8043] dark:text-[#a3a3a3] dark:hover:bg-[#17321f] dark:hover:text-[#3fcb7a]"
                                        title="Ubah Nama"
                                    >
                                        <Pencil class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="deleteCategory(cat)"
                                        :class="[
                                            'cursor-pointer rounded-lg p-1.5 transition',
                                            cat.artikels_count > 0
                                                ? 'text-gray-300 hover:text-gray-400 dark:text-gray-600'
                                                : 'text-gray-400 hover:bg-red-50 hover:text-red-600 dark:text-[#a3a3a3] dark:hover:bg-red-950/40 dark:hover:text-red-400',
                                        ]"
                                        :title="
                                            cat.artikels_count > 0
                                                ? 'Tidak dapat dihapus karena digunakan artikel'
                                                : 'Hapus Kategori'
                                        "
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </template>

                            <!-- Inline Edit Mode -->
                            <template v-else>
                                <div class="flex w-full items-center gap-2">
                                    <div class="grow">
                                        <input
                                            v-model="editingName"
                                            type="text"
                                            class="w-full rounded-xl border border-emerald-500 bg-white px-3 py-1.5 text-xs font-medium text-gray-900 focus:ring-1 focus:ring-emerald-500 focus:outline-none dark:border-[#3fcb7a] dark:bg-[#262626] dark:text-[#f0efea]"
                                            @keyup.enter="saveEditing(cat)"
                                            @keyup.esc="cancelEditing"
                                            autoFocus
                                        />
                                        <p
                                            v-if="editingError"
                                            class="mt-0.5 text-[10px] text-red-500"
                                        >
                                            {{ editingError }}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        @click="saveEditing(cat)"
                                        :disabled="isSubmitting"
                                        class="cursor-pointer rounded-lg bg-[#0b8043] p-1.5 text-white shadow-2xs transition hover:bg-[#096836] dark:bg-[#3fcb7a] dark:text-[#121212] dark:hover:bg-[#34aa66]"
                                        title="Simpan"
                                    >
                                        <Check class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="cancelEditing"
                                        class="cursor-pointer rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 dark:text-[#a3a3a3] dark:hover:bg-[#262626]"
                                        title="Batal"
                                    >
                                        <X class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div
                class="flex shrink-0 items-center justify-end border-t border-gray-100 bg-gray-50 p-4 dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
            >
                <button
                    type="button"
                    @click="emit('close')"
                    class="cursor-pointer rounded-full px-5 py-2.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-200 dark:text-[#f0efea] dark:hover:bg-[#262626]"
                >
                    Selesai
                </button>
            </div>
        </div>
    </div>
</template>
