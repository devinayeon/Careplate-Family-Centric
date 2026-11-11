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
    ShieldCheck
} from 'lucide-vue-next';

export interface FoodFormData {
    id?: number | null;
    nama: string;
    kategori_makanan_id: number | string;
    kuantitas: number | string;
    satuan: string;
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

const defaultSatuanOptions = ['g', 'ml', 'porsi', 'mangkuk', 'potong', 'butir', 'buah', 'sendok'];

const resetToCleanState = () => {
    form.nama = '';
    form.kategori_makanan_id = props.categories[0]?.id || '';
    form.kuantitas = '100';
    form.satuan = 'g';
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
    }
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
    <div v-if="show" class="fixed inset-0 z-50 overflow-hidden animate-in fade-in duration-200">
        <!-- Backdrop Scrim -->
        <div
            class="absolute inset-0 bg-[#1e2321]/50 backdrop-blur-xs transition-opacity"
            @click="emit('close')"
        />

        <div class="fixed inset-y-0 right-0 max-w-full flex pl-8">
            <div class="w-screen max-w-lg sm:max-w-xl bg-white shadow-2xl flex flex-col justify-between overflow-hidden">
                <!-- 1. Drawer Header -->
                <div class="px-6 py-5 border-b border-[#eceae5] flex items-center justify-between bg-white shrink-0">
                    <div>
                        <h2 class="text-2xl font-bold text-[#191919] tracking-tight">
                            {{ foodToEdit ? 'Ubah makanan' : 'Tambah makanan' }}
                        </h2>
                        <p class="text-xs text-[#5e5e5e] mt-0.5">
                            {{ foodToEdit ? 'Perbarui informasi kandungan nutrisi makanan' : 'Masukkan data gizi makanan baru ke database CarePlate' }}
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="emit('close')"
                        class="p-2 rounded-xl text-gray-400 hover:text-gray-600 hover:bg-[#f4f3f0] transition cursor-pointer"
                        title="Tutup"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- 2. Drawer Body (Scrollable Form) -->
                <form id="food-form" @submit.prevent="submit" class="p-6 overflow-y-auto space-y-5 grow">
                    <!-- Foto Makanan Section -->
                    <div>
                        <label class="block text-xs font-bold text-[#191919] uppercase tracking-wider mb-2">
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
                            class="relative rounded-2xl overflow-hidden border border-[#eceae5] bg-gray-50 aspect-video max-h-48 group shadow-xs"
                        >
                            <img
                                :src="imagePreviewUrl"
                                alt="Preview Foto Makanan"
                                class="w-full h-full object-cover"
                            />
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-3">
                                <button
                                    type="button"
                                    @click="fileInputRef?.click()"
                                    class="px-3.5 py-1.5 rounded-xl bg-white text-gray-800 text-xs font-semibold shadow-md hover:bg-gray-50 flex items-center gap-1.5 transition cursor-pointer"
                                >
                                    <RefreshCw class="w-3.5 h-3.5 text-[#0b8043]" />
                                    <span>Ganti</span>
                                </button>
                                <button
                                    type="button"
                                    @click="removeImage"
                                    class="px-3.5 py-1.5 rounded-xl bg-red-600 text-white text-xs font-semibold shadow-md hover:bg-red-700 flex items-center gap-1.5 transition cursor-pointer"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
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
                                'border-2 border-dashed rounded-2xl p-5 flex flex-col items-center justify-center text-center cursor-pointer transition-all duration-200',
                                isDragging
                                    ? 'border-[#0b8043] bg-emerald-50/50'
                                    : 'border-[#eceae5] hover:border-[#0b8043] hover:bg-emerald-50/20 bg-[#f8f9fa]'
                            ]"
                        >
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-[#0b8043] mb-2">
                                <UploadCloud class="w-5 h-5" />
                            </div>
                            <span class="text-xs font-semibold text-gray-800">
                                Unggah Foto Makanan
                            </span>
                            <span class="text-[11px] text-gray-400 mt-0.5">
                                Klik atau tarik file gambar (PNG, JPG, WebP maks 3MB)
                            </span>
                        </div>
                        <p v-if="form.errors.foto_makanan" class="text-xs text-red-500 mt-1 font-medium">
                            {{ form.errors.foto_makanan }}
                        </p>
                    </div>

                    <!-- Nama Makanan -->
                    <div>
                        <label class="block text-xs font-bold text-[#191919] uppercase tracking-wider mb-1.5">
                            Nama Makanan <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.nama"
                            type="text"
                            placeholder="Contoh: Pepes ikan kembung"
                            class="w-full px-4 py-2.5 rounded-xl border border-[#eceae5] bg-[#f4f3f0] focus:bg-white text-sm font-medium text-[#191919] focus:outline-none focus:border-[#0b8043] focus:ring-2 focus:ring-[#0b8043]/15 transition"
                            required
                        />
                        <p v-if="form.errors.nama" class="text-xs text-red-500 mt-1 font-medium">
                            {{ form.errors.nama }}
                        </p>
                    </div>

                    <!-- Kategori & Takaran Saji (Row 2 Cols) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Kategori -->
                        <div>
                            <label class="block text-xs font-bold text-[#191919] uppercase tracking-wider mb-1.5">
                                Kategori <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="form.kategori_makanan_id"
                                class="w-full px-3 py-2.5 rounded-xl border border-[#eceae5] bg-[#f4f3f0] focus:bg-white text-sm font-medium text-[#191919] focus:outline-none focus:border-[#0b8043] focus:ring-2 focus:ring-[#0b8043]/15 transition cursor-pointer"
                                required
                            >
                                <option value="" disabled>Pilih Kategori</option>
                                <option
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    :value="cat.id"
                                >
                                    {{ cat.kategori }}
                                </option>
                            </select>
                            <p v-if="form.errors.kategori_makanan_id" class="text-xs text-red-500 mt-1 font-medium">
                                {{ form.errors.kategori_makanan_id }}
                            </p>
                        </div>

                        <!-- Takaran Saji (Kuantitas & Satuan) -->
                        <div>
                            <label class="block text-xs font-bold text-[#191919] uppercase tracking-wider mb-1.5">
                                Takaran Saji <span class="text-red-500">*</span>
                            </label>
                            <div class="flex items-center gap-1.5">
                                <input
                                    v-model="form.kuantitas"
                                    type="number"
                                    step="any"
                                    min="0.1"
                                    placeholder="100"
                                    class="w-1/2 px-3 py-2.5 rounded-xl border border-[#eceae5] bg-[#f4f3f0] focus:bg-white text-sm font-medium text-[#191919] focus:outline-none focus:border-[#0b8043] focus:ring-2 focus:ring-[#0b8043]/15 transition"
                                    required
                                />
                                <select
                                    v-model="form.satuan"
                                    class="w-1/2 px-3 py-2.5 rounded-xl border border-[#eceae5] bg-[#f4f3f0] focus:bg-white text-sm font-medium text-[#191919] focus:outline-none focus:border-[#0b8043] focus:ring-2 focus:ring-[#0b8043]/15 transition cursor-pointer"
                                >
                                    <option v-for="s in defaultSatuanOptions" :key="s" :value="s">
                                        {{ s }}
                                    </option>
                                </select>
                            </div>
                            <p v-if="form.errors.kuantitas || form.errors.satuan" class="text-xs text-red-500 mt-1 font-medium">
                                {{ form.errors.kuantitas || form.errors.satuan }}
                            </p>
                        </div>
                    </div>

                    <!-- Makronutrisi: Energi & Protein -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-[#191919] uppercase tracking-wider mb-1.5">
                                Energi (Kalori) <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input
                                    v-model="form.energi"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    placeholder="172"
                                    class="w-full pl-3 pr-11 py-2.5 rounded-xl border border-[#eceae5] bg-[#f4f3f0] focus:bg-white text-sm font-medium text-[#191919] focus:outline-none focus:border-[#0b8043] focus:ring-2 focus:ring-[#0b8043]/15 transition"
                                    required
                                />
                                <span class="absolute right-3 top-2.5 text-xs font-bold text-[#5e5e5e] select-none">
                                    kkal
                                </span>
                            </div>
                            <p v-if="form.errors.energi" class="text-xs text-red-500 mt-1 font-medium">
                                {{ form.errors.energi }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-[#191919] uppercase tracking-wider mb-1.5">
                                Protein <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input
                                    v-model="form.protein"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    placeholder="22"
                                    class="w-full pl-3 pr-8 py-2.5 rounded-xl border border-[#eceae5] bg-[#f4f3f0] focus:bg-white text-sm font-medium text-[#191919] focus:outline-none focus:border-[#0b8043] focus:ring-2 focus:ring-[#0b8043]/15 transition"
                                    required
                                />
                                <span class="absolute right-3 top-2.5 text-xs font-bold text-[#5e5e5e] select-none">
                                    g
                                </span>
                            </div>
                            <p v-if="form.errors.protein" class="text-xs text-red-500 mt-1 font-medium">
                                {{ form.errors.protein }}
                            </p>
                        </div>
                    </div>

                    <!-- Makronutrisi: Karbohidrat & Lemak -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-[#191919] uppercase tracking-wider mb-1.5">
                                Karbohidrat <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input
                                    v-model="form.karbohidrat"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    placeholder="4"
                                    class="w-full pl-3 pr-8 py-2.5 rounded-xl border border-[#eceae5] bg-[#f4f3f0] focus:bg-white text-sm font-medium text-[#191919] focus:outline-none focus:border-[#0b8043] focus:ring-2 focus:ring-[#0b8043]/15 transition"
                                    required
                                />
                                <span class="absolute right-3 top-2.5 text-xs font-bold text-[#5e5e5e] select-none">
                                    g
                                </span>
                            </div>
                            <p v-if="form.errors.karbohidrat" class="text-xs text-red-500 mt-1 font-medium">
                                {{ form.errors.karbohidrat }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-[#191919] uppercase tracking-wider mb-1.5">
                                Lemak <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input
                                    v-model="form.lemak"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    placeholder="8"
                                    class="w-full pl-3 pr-8 py-2.5 rounded-xl border border-[#eceae5] bg-[#f4f3f0] focus:bg-white text-sm font-medium text-[#191919] focus:outline-none focus:border-[#0b8043] focus:ring-2 focus:ring-[#0b8043]/15 transition"
                                    required
                                />
                                <span class="absolute right-3 top-2.5 text-xs font-bold text-[#5e5e5e] select-none">
                                    g
                                </span>
                            </div>
                            <p v-if="form.errors.lemak" class="text-xs text-red-500 mt-1 font-medium">
                                {{ form.errors.lemak }}
                            </p>
                        </div>
                    </div>

                    <!-- Mikronutrisi: Gula & Natrium -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-[#191919] uppercase tracking-wider mb-1.5">
                                Gula
                            </label>
                            <div class="relative">
                                <input
                                    v-model="form.gula"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    placeholder="1"
                                    class="w-full pl-3 pr-8 py-2.5 rounded-xl border border-[#eceae5] bg-[#f4f3f0] focus:bg-white text-sm font-medium text-[#191919] focus:outline-none focus:border-[#0b8043] focus:ring-2 focus:ring-[#0b8043]/15 transition"
                                />
                                <span class="absolute right-3 top-2.5 text-xs font-bold text-[#5e5e5e] select-none">
                                    g
                                </span>
                            </div>
                            <p v-if="form.errors.gula" class="text-xs text-red-500 mt-1 font-medium">
                                {{ form.errors.gula }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-[#191919] uppercase tracking-wider mb-1.5">
                                Natrium
                            </label>
                            <div class="relative">
                                <input
                                    v-model="form.natrium"
                                    type="number"
                                    step="1"
                                    min="0"
                                    placeholder="380"
                                    class="w-full pl-3 pr-10 py-2.5 rounded-xl border border-[#eceae5] bg-[#f4f3f0] focus:bg-white text-sm font-medium text-[#191919] focus:outline-none focus:border-[#0b8043] focus:ring-2 focus:ring-[#0b8043]/15 transition"
                                />
                                <span class="absolute right-3 top-2.5 text-xs font-bold text-[#5e5e5e] select-none">
                                    mg
                                </span>
                            </div>
                            <p v-if="form.errors.natrium" class="text-xs text-red-500 mt-1 font-medium">
                                {{ form.errors.natrium }}
                            </p>
                        </div>
                    </div>

                    <!-- Sumber & Status Verifikasi -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-[#191919] uppercase tracking-wider mb-1.5">
                                Sumber Rujukan
                            </label>
                            <input
                                v-model="form.sumber"
                                type="text"
                                placeholder="Tabel Komposisi Pangan"
                                class="w-full px-4 py-2.5 rounded-xl border border-[#eceae5] bg-[#f4f3f0] focus:bg-white text-sm font-medium text-[#191919] focus:outline-none focus:border-[#0b8043] focus:ring-2 focus:ring-[#0b8043]/15 transition"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-[#191919] uppercase tracking-wider mb-1.5">
                                Status Verifikasi
                            </label>
                            <select
                                v-model="form.status"
                                class="w-full px-3 py-2.5 rounded-xl border border-[#eceae5] bg-[#f4f3f0] focus:bg-white text-sm font-medium text-[#191919] focus:outline-none focus:border-[#0b8043] focus:ring-2 focus:ring-[#0b8043]/15 transition cursor-pointer"
                            >
                                <option value="terverifikasi">Terverifikasi</option>
                                <option value="menunggu">Menunggu</option>
                                <option value="ditolak">Ditolak</option>
                            </select>
                        </div>
                    </div>

                    <!-- Info Banner -->
                    <div class="bg-[#e4f6ea] rounded-xl p-3.5 flex items-center gap-3 text-xs text-[#191919]">
                        <ShieldCheck class="w-5 h-5 text-[#0b8043] shrink-0" />
                        <p class="leading-relaxed">
                            Dibuat Admin langsung <strong>terverifikasi</strong> dan tersinkron ke modul kalkulator serta Chat Gizi.
                        </p>
                    </div>
                </form>

                <!-- 3. Drawer Footer Actions -->
                <div class="px-6 py-4 border-t border-[#eceae5] bg-white flex items-center justify-end gap-3 shrink-0">
                    <button
                        type="button"
                        @click="emit('close')"
                        :disabled="form.processing"
                        class="px-5 py-2.5 rounded-full border border-[#191919] text-xs font-bold text-[#191919] hover:bg-gray-100 transition cursor-pointer disabled:opacity-50"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        form="food-form"
                        :disabled="form.processing"
                        class="px-6 py-2.5 rounded-full bg-[#0b8043] text-white text-xs font-bold hover:bg-[#086637] shadow-sm transition flex items-center gap-2 cursor-pointer disabled:opacity-50"
                    >
                        <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
                        <span>Simpan Makanan</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
