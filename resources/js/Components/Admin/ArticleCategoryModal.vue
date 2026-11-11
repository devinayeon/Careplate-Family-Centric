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
    CheckCircle2
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
            emit('updated', categories.value.map((c) => c.nama));
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
    }
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

        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

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
                newCategoryError.value = data.message || 'Gagal menambahkan kategori.';
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

        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

        const res = await fetch(route('admin.article-categories.update', cat.id), {
            method: 'PUT',
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
            `Kategori "${cat.nama}" tidak dapat dihapus karena masih digunakan oleh ${cat.artikels_count} artikel. Silakan ubah kategori artikel tersebut terlebih dahulu.`
        );
        return;
    }

    if (!confirm(`Apakah Anda yakin ingin menghapus kategori "${cat.nama}"?`)) {
        return;
    }

    try {
        isSubmitting.value = true;
        generalError.value = null;

        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

        const res = await fetch(route('admin.article-categories.destroy', cat.id), {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
        });

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
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-xs p-4 animate-in fade-in duration-150"
    >
        <div class="bg-white rounded-3xl max-w-lg w-full shadow-2xl border border-gray-100 overflow-hidden flex flex-col max-h-[90vh]">
            <!-- Header -->
            <div class="p-6 border-b border-gray-100 flex items-center justify-between bg-white shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-[#0b8043] flex items-center justify-center">
                        <Folder class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 tracking-tight">
                            Kelola Kategori Artikel
                        </h3>
                        <p class="text-xs text-gray-500">
                            Tambah, ubah nama, atau hapus kategori artikel CarePlate
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="emit('close')"
                    class="p-2 rounded-xl text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition cursor-pointer"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Body -->
            <div class="p-6 space-y-6 overflow-y-auto">
                <!-- Notifications -->
                <div
                    v-if="successMessage"
                    class="p-3.5 rounded-2xl bg-[#e6f6ec] text-[#0b7a46] text-xs font-semibold flex items-center gap-2 animate-in fade-in"
                >
                    <CheckCircle2 class="w-4 h-4 shrink-0" />
                    <span>{{ successMessage }}</span>
                </div>

                <div
                    v-if="generalError"
                    class="p-3.5 rounded-2xl bg-red-50 text-red-600 text-xs font-semibold flex items-center gap-2 animate-in fade-in"
                >
                    <AlertCircle class="w-4 h-4 shrink-0" />
                    <span>{{ generalError }}</span>
                </div>

                <!-- Section 1: Tambah Kategori Form -->
                <div class="p-4 rounded-2xl bg-[#f8f9fa] border border-gray-200/80 space-y-2">
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">
                        Tambah Kategori Baru
                    </label>
                    <div class="flex items-center gap-2">
                        <input
                            v-model="newCategoryName"
                            type="text"
                            placeholder="Contoh: Menu Diet Rendah Garam"
                            class="grow px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-xs font-medium focus:outline-none focus:border-[#0b8043] focus:ring-2 focus:ring-[#0b8043]/15 transition"
                            @keyup.enter="addCategory"
                        />
                        <button
                            type="button"
                            @click="addCategory"
                            :disabled="isSubmitting || !newCategoryName.trim()"
                            class="px-4 py-2.5 rounded-xl bg-[#0b8043] text-white text-xs font-semibold hover:bg-[#096836] shadow-xs transition flex items-center gap-1.5 shrink-0 disabled:opacity-40 cursor-pointer"
                        >
                            <Loader2 v-if="isSubmitting" class="w-3.5 h-3.5 animate-spin" />
                            <Plus v-else class="w-3.5 h-3.5" />
                            <span>Tambah</span>
                        </button>
                    </div>
                    <p v-if="newCategoryError" class="text-xs text-red-500 font-medium">
                        {{ newCategoryError }}
                    </p>
                </div>

                <!-- Section 2: Daftar Kategori -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-gray-700 uppercase tracking-wider">
                            Daftar Kategori Tersedia ({{ categories.length }})
                        </span>
                        <Loader2 v-if="isLoading" class="w-3.5 h-3.5 animate-spin text-gray-400" />
                    </div>

                    <div v-if="categories.length === 0 && !isLoading" class="p-8 text-center text-xs text-gray-400">
                        Belum ada kategori yang dibuat.
                    </div>

                    <div class="space-y-2 max-h-60 overflow-y-auto pr-1">
                        <div
                            v-for="cat in categories"
                            :key="cat.id"
                            class="flex items-center justify-between p-3 rounded-2xl border border-gray-100 bg-white hover:bg-gray-50/70 transition"
                        >
                            <!-- View Mode -->
                            <template v-if="editingId !== cat.id">
                                <div class="flex items-center gap-2.5 min-w-0 pr-2">
                                    <span class="font-semibold text-xs text-gray-800 truncate">
                                        {{ cat.nama }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-[#f0f4f1] text-[#2d4b38] shrink-0">
                                        {{ cat.artikels_count }} artikel
                                    </span>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <button
                                        type="button"
                                        @click="startEditing(cat)"
                                        class="p-1.5 rounded-lg text-gray-400 hover:text-[#0b8043] hover:bg-emerald-50 transition cursor-pointer"
                                        title="Ubah Nama"
                                    >
                                        <Pencil class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="deleteCategory(cat)"
                                        :class="[
                                            'p-1.5 rounded-lg transition cursor-pointer',
                                            cat.artikels_count > 0
                                                ? 'text-gray-300 hover:text-gray-400'
                                                : 'text-gray-400 hover:text-red-600 hover:bg-red-50'
                                        ]"
                                        :title="cat.artikels_count > 0 ? 'Tidak dapat dihapus karena digunakan artikel' : 'Hapus Kategori'"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </template>

                            <!-- Inline Edit Mode -->
                            <template v-else>
                                <div class="flex items-center gap-2 w-full">
                                    <div class="grow">
                                        <input
                                            v-model="editingName"
                                            type="text"
                                            class="w-full px-3 py-1.5 rounded-xl border border-emerald-500 text-xs font-medium focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                            @keyup.enter="saveEditing(cat)"
                                            @keyup.esc="cancelEditing"
                                            autoFocus
                                        />
                                        <p v-if="editingError" class="text-[10px] text-red-500 mt-0.5">
                                            {{ editingError }}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        @click="saveEditing(cat)"
                                        :disabled="isSubmitting"
                                        class="p-1.5 rounded-lg bg-[#0b8043] text-white hover:bg-[#096836] shadow-2xs transition cursor-pointer"
                                        title="Simpan"
                                    >
                                        <Check class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="cancelEditing"
                                        class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 transition cursor-pointer"
                                        title="Batal"
                                    >
                                        <X class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end shrink-0">
                <button
                    type="button"
                    @click="emit('close')"
                    class="px-5 py-2.5 rounded-full text-xs font-semibold text-gray-700 hover:bg-gray-200 transition cursor-pointer"
                >
                    Selesai
                </button>
            </div>
        </div>
    </div>
</template>
