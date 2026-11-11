<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import {
    X,
    UploadCloud,
    Image as ImageIcon,
    CheckCircle2,
    Loader2,
    RefreshCw,
    Trash2,
    Utensils,
    ShieldCheck,
} from 'lucide-vue-next';

export interface FoodFormData {
    id?: number | null;
    nama: string;
    kategori_makanan_id: number | string;
    kuantitas: number | string;
    satuan: string;
    berat_per_satuan_gram?: number | string | null;
    energi: number | string;
    protein: number | string;
    karbohidrat: number | string;
    lemak: number | string;
    gula: number | string;
    natrium: number | string;
    sumber: string;
    status: 'terverifikasi' | 'menunggu' | 'ditolak';
    foto_makanan_url?: string | null;
}

const props = defineProps<{
    show: boolean;
    foodToEdit?: FoodFormData | null;
    categories: Array<{ id: number; kategori: string }>;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'success'): void;
}>();

const fileInputRef = ref<HTMLInputElement | null>(null);
const imagePreviewUrl = ref<string | null>(null);
const isDragging = ref(false);

const form = useForm({
    nama: '',
    kategori_makanan_id: '' as number | string,
    kuantitas: '100',
    satuan: 'g',
    berat_per_satuan_gram: '' as number | string | null,
    energi: '',
    protein: '',
    karbohidrat: '',
    lemak: '',
    gula: '0',
    natrium: '0',
    sumber: 'Tabel Komposisi Pangan',
    status: 'terverifikasi' as 'terverifikasi' | 'menunggu' | 'ditolak',
    foto_makanan: null as File | null,
});

const defaultSatuanOptions = [
    'g',
    'ml',
    'porsi',
    'mangkuk',
    'potong',
    'butir',
    'buah',
    'sendok',
];

const resetToCleanState = () => {
    form.nama = '';
    form.kategori_makanan_id = props.categories[0]?.id || '';
    form.kuantitas = '100';
    form.satuan = 'g';
    form.berat_per_satuan_gram = '';
    form.energi = '';
    form.protein = '';
    form.karbohidrat = '';
    form.lemak = '';
    form.gula = '0';
    form.natrium = '0';
    form.sumber = 'Tabel Komposisi Pangan';
    form.status = 'terverifikasi';
    form.foto_makanan = null;
    form.clearErrors();
    imagePreviewUrl.value = null;
    if (fileInputRef.value) fileInputRef.value.value = '';
};

watch(
    () => props.show,
    (isOpen) => {
        if (!isOpen) {
            resetToCleanState();
        } else if (props.foodToEdit) {
            form.clearErrors();
            form.nama = props.foodToEdit.nama;
            form.kategori_makanan_id = props.foodToEdit.kategori_makanan_id;
            form.kuantitas = props.foodToEdit.kuantitas.toString();
            form.satuan = props.foodToEdit.satuan;
            form.berat_per_satuan_gram =
                props.foodToEdit.berat_per_satuan_gram?.toString() ?? '';
            form.energi = props.foodToEdit.energi.toString();
            form.protein = props.foodToEdit.protein.toString();
            form.karbohidrat = props.foodToEdit.karbohidrat.toString();
            form.lemak = props.foodToEdit.lemak.toString();
            form.gula = (props.foodToEdit.gula ?? 0).toString();
            form.natrium = (props.foodToEdit.natrium ?? 0).toString();
            form.sumber = props.foodToEdit.sumber || 'Tabel Komposisi Pangan';
            form.status = props.foodToEdit.status || 'terverifikasi';
            form.foto_makanan = null;
            imagePreviewUrl.value = props.foodToEdit.foto_makanan_url || null;
        } else {
            resetToCleanState();
        }
    },
);

// File change handler
const handleFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        form.foto_makanan = file;
        imagePreviewUrl.value = URL.createObjectURL(file);
    }
};

const handleDrop = (e: DragEvent) => {
    isDragging.value = false;
    const file = e.dataTransfer?.files?.[0];
    if (file && file.type.startsWith('image/')) {
        form.foto_makanan = file;
        imagePreviewUrl.value = URL.createObjectURL(file);
    }
};

const removeImage = () => {
    form.foto_makanan = null;
    imagePreviewUrl.value = null;
    if (fileInputRef.value) fileInputRef.value.value = '';
};

const submit = () => {
    if (props.foodToEdit?.id) {
        // Edit mode (POST with _method PUT for multipart)
        form.post(route('admin.foods.update', props.foodToEdit.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                emit('success');
                emit('close');
            },
        });
    } else {
        // Create mode
        form.post(route('admin.foods.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                emit('success');
                emit('close');
            },
        });
    }
};
</script>

<template>
    <!-- Drawer Container -->
    <div
        v-if="show"
        class="animate-in fade-in fixed inset-0 z-50 overflow-hidden duration-200"
    >
        <!-- Backdrop Scrim -->
        <div
            class="absolute inset-0 bg-[#1e2321]/50 backdrop-blur-xs transition-opacity"
            @click="emit('close')"
        />

        <div class="fixed inset-y-0 right-0 flex max-w-full pl-8">
            <div
                class="flex w-screen max-w-lg flex-col justify-between overflow-hidden border-l bg-white shadow-2xl sm:max-w-xl dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
            >
                <!-- 1. Drawer Header -->
                <div
                    class="flex shrink-0 items-center justify-between border-b border-[#eceae5] bg-white px-6 py-5 dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
                >
                    <div>
                        <h2
                            class="text-2xl font-bold tracking-tight text-[#191919] dark:text-[#f0efea]"
                        >
                            {{ foodToEdit ? 'Ubah makanan' : 'Tambah makanan' }}
                        </h2>
                        <p
                            class="mt-0.5 text-xs text-[#5e5e5e] dark:text-[#a3a3a3]"
                        >
                            {{
                                foodToEdit
                                    ? 'Perbarui informasi kandungan nutrisi makanan'
                                    : 'Masukkan data gizi makanan baru ke database CarePlate'
                            }}
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="emit('close')"
                        class="cursor-pointer rounded-xl p-2 text-gray-400 transition hover:bg-[#f4f3f0] hover:text-gray-600 dark:text-[#a3a3a3] dark:hover:bg-[#262626] dark:hover:text-[#f0efea]"
                        title="Tutup"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- 2. Drawer Body (Scrollable Form) -->
                <form
                    id="food-form"
                    @submit.prevent="submit"
                    class="grow space-y-5 overflow-y-auto p-6"
                >
                    <!-- Foto Makanan Section -->
                    <div>
                        <label
                            class="mb-2 block text-xs font-bold tracking-wider text-[#191919] uppercase dark:text-[#f0efea]"
                        >
                            Foto Makanan
                        </label>
                        <input
                            ref="fileInputRef"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            class="hidden"
                            @change="handleFileChange"
                        />

                        <!-- Preview jika sudah ada foto -->
                        <div
                            v-if="imagePreviewUrl"
                            class="group relative aspect-video max-h-48 overflow-hidden rounded-2xl border border-[#eceae5] bg-gray-50 shadow-xs dark:border-[#2e2e2e] dark:bg-[#262626]"
                        >
                            <img
                                :src="imagePreviewUrl"
                                alt="Preview Foto Makanan"
                                class="h-full w-full object-cover"
                            />
                            <div
                                class="absolute inset-0 flex items-center justify-center gap-3 bg-black/40 opacity-0 transition-opacity group-hover:opacity-100"
                            >
                                <button
                                    type="button"
                                    @click="fileInputRef?.click()"
                                    class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-white px-3.5 py-1.5 text-xs font-semibold text-gray-800 shadow-md transition hover:bg-gray-50 dark:bg-[#1c1c1c] dark:text-[#f0efea] dark:hover:bg-[#262626]"
                                >
                                    <RefreshCw
                                        class="h-3.5 w-3.5 text-[#0b8043] dark:text-[#3fcb7a]"
                                    />
                                    <span>Ganti</span>
                                </button>
                                <button
                                    type="button"
                                    @click="removeImage"
                                    class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-red-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-md transition hover:bg-red-700"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                    <span>Hapus</span>
                                </button>
                            </div>
                        </div>

                        <!-- Dropzone jika belum ada foto -->
                        <div
                            v-else
                            @click="fileInputRef?.click()"
                            @dragover.prevent="isDragging = true"
                            @dragleave.prevent="isDragging = false"
                            @drop.prevent="handleDrop"
                            :class="[
                                'flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed p-5 text-center transition-all duration-200',
                                isDragging
                                    ? 'border-[#0b8043] bg-emerald-50/50 dark:border-[#3fcb7a] dark:bg-[#17321f]/50'
                                    : 'border-[#eceae5] bg-[#f8f9fa] hover:border-[#0b8043] hover:bg-emerald-50/20 dark:border-[#2e2e2e] dark:bg-[#262626] dark:hover:border-[#3fcb7a] dark:hover:bg-[#17321f]/30',
                            ]"
                        >
                            <div
                                class="mb-2 flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-[#0b8043] dark:bg-[#17321f] dark:text-[#3fcb7a]"
                            >
                                <UploadCloud class="h-5 w-5" />
                            </div>
                            <span
                                class="text-xs font-semibold text-gray-800 dark:text-[#f0efea]"
                            >
                                Unggah Foto Makanan
                            </span>
                            <span
                                class="mt-0.5 text-[11px] text-gray-400 dark:text-[#a3a3a3]"
                            >
                                Klik atau tarik file gambar (PNG, JPG, WebP maks
                                3MB)
                            </span>
                        </div>
                        <p
                            v-if="form.errors.foto_makanan"
                            class="mt-1 text-xs font-medium text-red-500"
                        >
                            {{ form.errors.foto_makanan }}
                        </p>
                    </div>

                    <!-- Nama Makanan -->
                    <div>
                        <label
                            class="mb-1.5 block text-xs font-bold tracking-wider text-[#191919] uppercase dark:text-[#f0efea]"
                        >
                            Nama Makanan <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.nama"
                            type="text"
                            placeholder="Contoh: Pepes ikan kembung"
                            class="w-full rounded-xl border border-[#eceae5] bg-[#f4f3f0] px-4 py-2.5 text-sm font-medium text-[#191919] transition focus:border-[#0b8043] focus:bg-white focus:ring-2 focus:ring-[#0b8043]/15 focus:outline-none dark:border-[#2e2e2e] dark:bg-[#262626] dark:text-[#f0efea] dark:focus:border-[#3fcb7a] dark:focus:bg-[#1c1c1c]"
                            required
                        />
                        <p
                            v-if="form.errors.nama"
                            class="mt-1 text-xs font-medium text-red-500"
                        >
                            {{ form.errors.nama }}
                        </p>
                    </div>

                    <!-- Kategori & Takaran Saji (Row 2 Cols) -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <!-- Kategori -->
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-bold tracking-wider text-[#191919] uppercase dark:text-[#f0efea]"
                            >
                                Kategori <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="form.kategori_makanan_id"
                                class="w-full cursor-pointer rounded-xl border border-[#eceae5] bg-[#f4f3f0] px-3 py-2.5 text-sm font-medium text-[#191919] transition focus:border-[#0b8043] focus:bg-white focus:ring-2 focus:ring-[#0b8043]/15 focus:outline-none dark:border-[#2e2e2e] dark:bg-[#262626] dark:text-[#f0efea] dark:focus:border-[#3fcb7a] dark:focus:bg-[#1c1c1c]"
                                required
                            >
                                <option value="" disabled>
                                    Pilih Kategori
                                </option>
                                <option
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    :value="cat.id"
                                >
                                    {{ cat.kategori }}
                                </option>
                            </select>
                            <p
                                v-if="form.errors.kategori_makanan_id"
                                class="mt-1 text-xs font-medium text-red-500"
                            >
                                {{ form.errors.kategori_makanan_id }}
                            </p>
                        </div>

                        <!-- Takaran Saji (Kuantitas & Satuan) -->
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-bold tracking-wider text-[#191919] uppercase dark:text-[#f0efea]"
                            >
                                Takaran Saji <span class="text-red-500">*</span>
                            </label>
                            <div class="flex items-center gap-1.5">
                                <input
                                    v-model="form.kuantitas"
                                    type="number"
                                    step="any"
                                    min="0.1"
                                    placeholder="100"
                                    class="w-1/2 rounded-xl border border-[#eceae5] bg-[#f4f3f0] px-3 py-2.5 text-sm font-medium text-[#191919] transition focus:border-[#0b8043] focus:bg-white focus:ring-2 focus:ring-[#0b8043]/15 focus:outline-none dark:border-[#2e2e2e] dark:bg-[#262626] dark:text-[#f0efea] dark:focus:border-[#3fcb7a] dark:focus:bg-[#1c1c1c]"
                                    required
                                />
                                <select
                                    v-model="form.satuan"
                                    class="w-1/2 cursor-pointer rounded-xl border border-[#eceae5] bg-[#f4f3f0] px-3 py-2.5 text-sm font-medium text-[#191919] transition focus:border-[#0b8043] focus:bg-white focus:ring-2 focus:ring-[#0b8043]/15 focus:outline-none dark:border-[#2e2e2e] dark:bg-[#262626] dark:text-[#f0efea] dark:focus:border-[#3fcb7a] dark:focus:bg-[#1c1c1c]"
                                >
                                    <option
                                        v-for="s in defaultSatuanOptions"
                                        :key="s"
                                        :value="s"
                                    >
                                        {{ s }}
                                    </option>
                                </select>
                            </div>
                            <p
                                v-if="
                                    form.errors.kuantitas || form.errors.satuan
                                "
                                class="mt-1 text-xs font-medium text-red-500"
                            >
                                {{
                                    form.errors.kuantitas || form.errors.satuan
                                }}
                            </p>

                            <!-- Weight of ONE unit: lets Chat Gizi convert a non-gram serving to per 100 g -->
                            <div v-if="form.satuan !== 'g'" class="mt-3">
                                <label
                                    for="berat-per-satuan"
                                    class="mb-1.5 block text-xs font-bold tracking-wider text-[#191919] uppercase dark:text-[#f0efea]"
                                >
                                    Berat per {{ form.satuan }} (gram)
                                </label>
                                <input
                                    id="berat-per-satuan"
                                    v-model="form.berat_per_satuan_gram"
                                    type="number"
                                    step="any"
                                    min="0.01"
                                    placeholder="Contoh: 150"
                                    class="w-full rounded-xl border border-[#eceae5] bg-[#f4f3f0] px-3 py-2.5 text-sm font-medium text-[#191919] transition focus:border-[#0b8043] focus:bg-white focus:ring-2 focus:ring-[#0b8043]/15 focus:outline-none dark:border-[#2e2e2e] dark:bg-[#262626] dark:text-[#f0efea] dark:focus:bg-[#1c1c1c]"
                                />
                                <p
                                    class="mt-1 text-xs text-[#5e5e5e] dark:text-[#a3a3a3]"
                                >
                                    Opsional. Tanpa ini, Chat Gizi tidak memakai
                                    makanan ini.
                                </p>
                                <p
                                    v-if="form.errors.berat_per_satuan_gram"
                                    class="mt-1 text-xs font-medium text-red-500"
                                >
                                    {{ form.errors.berat_per_satuan_gram }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Makronutrisi: Energi & Protein -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-bold tracking-wider text-[#191919] uppercase dark:text-[#f0efea]"
                            >
                                Energi (Kalori)
                                <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input
                                    v-model="form.energi"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    placeholder="172"
                                    class="w-full rounded-xl border border-[#eceae5] bg-[#f4f3f0] py-2.5 pr-11 pl-3 text-sm font-medium text-[#191919] transition focus:border-[#0b8043] focus:bg-white focus:ring-2 focus:ring-[#0b8043]/15 focus:outline-none dark:border-[#2e2e2e] dark:bg-[#262626] dark:text-[#f0efea] dark:focus:border-[#3fcb7a] dark:focus:bg-[#1c1c1c]"
                                    required
                                />
                                <span
                                    class="absolute top-2.5 right-3 text-xs font-bold text-[#5e5e5e] select-none dark:text-[#a3a3a3]"
                                >
                                    kkal
                                </span>
                            </div>
                            <p
                                v-if="form.errors.energi"
                                class="mt-1 text-xs font-medium text-red-500"
                            >
                                {{ form.errors.energi }}
                            </p>
                        </div>

                        <div>
                            <label
                                class="mb-1.5 block text-xs font-bold tracking-wider text-[#191919] uppercase dark:text-[#f0efea]"
                            >
                                Protein <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input
                                    v-model="form.protein"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    placeholder="22"
                                    class="w-full rounded-xl border border-[#eceae5] bg-[#f4f3f0] py-2.5 pr-8 pl-3 text-sm font-medium text-[#191919] transition focus:border-[#0b8043] focus:bg-white focus:ring-2 focus:ring-[#0b8043]/15 focus:outline-none dark:border-[#2e2e2e] dark:bg-[#262626] dark:text-[#f0efea] dark:focus:border-[#3fcb7a] dark:focus:bg-[#1c1c1c]"
                                    required
                                />
                                <span
                                    class="absolute top-2.5 right-3 text-xs font-bold text-[#5e5e5e] select-none dark:text-[#a3a3a3]"
                                >
                                    g
                                </span>
                            </div>
                            <p
                                v-if="form.errors.protein"
                                class="mt-1 text-xs font-medium text-red-500"
                            >
                                {{ form.errors.protein }}
                            </p>
                        </div>
                    </div>

                    <!-- Makronutrisi: Karbohidrat & Lemak -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-bold tracking-wider text-[#191919] uppercase dark:text-[#f0efea]"
                            >
                                Karbohidrat <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input
                                    v-model="form.karbohidrat"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    placeholder="4"
                                    class="w-full rounded-xl border border-[#eceae5] bg-[#f4f3f0] py-2.5 pr-8 pl-3 text-sm font-medium text-[#191919] transition focus:border-[#0b8043] focus:bg-white focus:ring-2 focus:ring-[#0b8043]/15 focus:outline-none dark:border-[#2e2e2e] dark:bg-[#262626] dark:text-[#f0efea] dark:focus:border-[#3fcb7a] dark:focus:bg-[#1c1c1c]"
                                    required
                                />
                                <span
                                    class="absolute top-2.5 right-3 text-xs font-bold text-[#5e5e5e] select-none dark:text-[#a3a3a3]"
                                >
                                    g
                                </span>
                            </div>
                            <p
                                v-if="form.errors.karbohidrat"
                                class="mt-1 text-xs font-medium text-red-500"
                            >
                                {{ form.errors.karbohidrat }}
                            </p>
                        </div>

                        <div>
                            <label
                                class="mb-1.5 block text-xs font-bold tracking-wider text-[#191919] uppercase dark:text-[#f0efea]"
                            >
                                Lemak <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input
                                    v-model="form.lemak"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    placeholder="8"
                                    class="w-full rounded-xl border border-[#eceae5] bg-[#f4f3f0] py-2.5 pr-8 pl-3 text-sm font-medium text-[#191919] transition focus:border-[#0b8043] focus:bg-white focus:ring-2 focus:ring-[#0b8043]/15 focus:outline-none dark:border-[#2e2e2e] dark:bg-[#262626] dark:text-[#f0efea] dark:focus:border-[#3fcb7a] dark:focus:bg-[#1c1c1c]"
                                    required
                                />
                                <span
                                    class="absolute top-2.5 right-3 text-xs font-bold text-[#5e5e5e] select-none dark:text-[#a3a3a3]"
                                >
                                    g
                                </span>
                            </div>
                            <p
                                v-if="form.errors.lemak"
                                class="mt-1 text-xs font-medium text-red-500"
                            >
                                {{ form.errors.lemak }}
                            </p>
                        </div>
                    </div>

                    <!-- Mikronutrisi: Gula & Natrium -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-bold tracking-wider text-[#191919] uppercase dark:text-[#f0efea]"
                            >
                                Gula
                            </label>
                            <div class="relative">
                                <input
                                    v-model="form.gula"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    placeholder="1"
                                    class="w-full rounded-xl border border-[#eceae5] bg-[#f4f3f0] py-2.5 pr-8 pl-3 text-sm font-medium text-[#191919] transition focus:border-[#0b8043] focus:bg-white focus:ring-2 focus:ring-[#0b8043]/15 focus:outline-none dark:border-[#2e2e2e] dark:bg-[#262626] dark:text-[#f0efea] dark:focus:border-[#3fcb7a] dark:focus:bg-[#1c1c1c]"
                                />
                                <span
                                    class="absolute top-2.5 right-3 text-xs font-bold text-[#5e5e5e] select-none dark:text-[#a3a3a3]"
                                >
                                    g
                                </span>
                            </div>
                            <p
                                v-if="form.errors.gula"
                                class="mt-1 text-xs font-medium text-red-500"
                            >
                                {{ form.errors.gula }}
                            </p>
                        </div>

                        <div>
                            <label
                                class="mb-1.5 block text-xs font-bold tracking-wider text-[#191919] uppercase dark:text-[#f0efea]"
                            >
                                Natrium
                            </label>
                            <div class="relative">
                                <input
                                    v-model="form.natrium"
                                    type="number"
                                    step="1"
                                    min="0"
                                    placeholder="380"
                                    class="w-full rounded-xl border border-[#eceae5] bg-[#f4f3f0] py-2.5 pr-10 pl-3 text-sm font-medium text-[#191919] transition focus:border-[#0b8043] focus:bg-white focus:ring-2 focus:ring-[#0b8043]/15 focus:outline-none dark:border-[#2e2e2e] dark:bg-[#262626] dark:text-[#f0efea] dark:focus:border-[#3fcb7a] dark:focus:bg-[#1c1c1c]"
                                />
                                <span
                                    class="absolute top-2.5 right-3 text-xs font-bold text-[#5e5e5e] select-none dark:text-[#a3a3a3]"
                                >
                                    mg
                                </span>
                            </div>
                            <p
                                v-if="form.errors.natrium"
                                class="mt-1 text-xs font-medium text-red-500"
                            >
                                {{ form.errors.natrium }}
                            </p>
                        </div>
                    </div>

                    <!-- Sumber & Status Verifikasi -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-bold tracking-wider text-[#191919] uppercase dark:text-[#f0efea]"
                            >
                                Sumber Rujukan
                            </label>
                            <input
                                v-model="form.sumber"
                                type="text"
                                placeholder="Tabel Komposisi Pangan"
                                class="w-full rounded-xl border border-[#eceae5] bg-[#f4f3f0] px-4 py-2.5 text-sm font-medium text-[#191919] transition focus:border-[#0b8043] focus:bg-white focus:ring-2 focus:ring-[#0b8043]/15 focus:outline-none dark:border-[#2e2e2e] dark:bg-[#262626] dark:text-[#f0efea] dark:focus:border-[#3fcb7a] dark:focus:bg-[#1c1c1c]"
                            />
                        </div>

                        <div>
                            <label
                                class="mb-1.5 block text-xs font-bold tracking-wider text-[#191919] uppercase dark:text-[#f0efea]"
                            >
                                Status Verifikasi
                            </label>
                            <select
                                v-model="form.status"
                                class="w-full cursor-pointer rounded-xl border border-[#eceae5] bg-[#f4f3f0] px-3 py-2.5 text-sm font-medium text-[#191919] transition focus:border-[#0b8043] focus:bg-white focus:ring-2 focus:ring-[#0b8043]/15 focus:outline-none dark:border-[#2e2e2e] dark:bg-[#262626] dark:text-[#f0efea] dark:focus:border-[#3fcb7a] dark:focus:bg-[#1c1c1c]"
                            >
                                <option value="terverifikasi">
                                    Terverifikasi
                                </option>
                                <option value="menunggu">Menunggu</option>
                                <option value="ditolak">Ditolak</option>
                            </select>
                        </div>
                    </div>

                    <!-- Info Banner -->
                    <div
                        class="flex items-center gap-3 rounded-xl border border-[#0b8043]/20 bg-[#e4f6ea] p-3.5 text-xs text-[#191919] dark:border-[#3fcb7a]/30 dark:bg-[#17321f] dark:text-[#f0efea]"
                    >
                        <ShieldCheck
                            class="h-5 w-5 shrink-0 text-[#0b8043] dark:text-[#3fcb7a]"
                        />
                        <p class="leading-relaxed">
                            Dibuat Admin langsung
                            <strong>terverifikasi</strong> dan tersinkron ke
                            modul kalkulator serta Chat Gizi.
                        </p>
                    </div>
                </form>

                <!-- 3. Drawer Footer Actions -->
                <div
                    class="flex shrink-0 items-center justify-end gap-3 border-t border-[#eceae5] bg-white px-6 py-4 dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
                >
                    <button
                        type="button"
                        @click="emit('close')"
                        :disabled="form.processing"
                        class="cursor-pointer rounded-full border border-[#191919] px-5 py-2.5 text-xs font-bold text-[#191919] transition hover:bg-gray-100 disabled:opacity-50 dark:border-[#3d3d3d] dark:text-[#f0efea] dark:hover:bg-[#262626]"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        form="food-form"
                        :disabled="form.processing"
                        class="flex cursor-pointer items-center gap-2 rounded-full bg-[#0b8043] px-6 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#086637] disabled:opacity-50 dark:bg-[#3fcb7a] dark:text-[#121212] dark:hover:bg-[#34aa66]"
                    >
                        <Loader2
                            v-if="form.processing"
                            class="h-4 w-4 animate-spin"
                        />
                        <span>Simpan Makanan</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
