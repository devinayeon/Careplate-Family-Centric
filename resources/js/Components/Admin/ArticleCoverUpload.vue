<script setup lang="ts">
import { ref, watch } from 'vue';
import { UploadCloud, Image as ImageIcon, Trash2, RefreshCw } from 'lucide-vue-next';

const props = defineProps<{
    initialPreviewUrl?: string | null;
    error?: string;
}>();

const emit = defineEmits<{
    (e: 'file-selected', file: File | null): void;
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(props.initialPreviewUrl || null);
const isDragging = ref(false);

watch(
    () => props.initialPreviewUrl,
    (newVal) => {
        previewUrl.value = newVal || null;
    }
);

const triggerSelect = () => {
    fileInput.value?.click();
};

const handleFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        processFile(file);
    }
};

const handleDrop = (e: DragEvent) => {
    isDragging.value = false;
    const file = e.dataTransfer?.files?.[0];
    if (file && file.type.startsWith('image/')) {
        processFile(file);
    }
};

const processFile = (file: File) => {
    previewUrl.value = URL.createObjectURL(file);
    emit('file-selected', file);
};

const removeImage = () => {
    previewUrl.value = null;
    if (fileInput.value) fileInput.value.value = '';
    emit('file-selected', null);
};
</script>

<template>
    <div class="space-y-2">
        <input
            ref="fileInput"
            type="file"
            accept="image/png,image/jpeg,image/webp"
            class="hidden"
            @change="handleFileChange"
        />

        <!-- Preview Box when image is set -->
        <div
            v-if="previewUrl"
            class="relative rounded-2xl overflow-hidden border border-gray-200 dark:border-[#2e2e2e] bg-gray-50 dark:bg-[#262626] aspect-[21/9] max-h-64 group shadow-xs"
        >
            <img
                :src="previewUrl"
                alt="Foto Sampul Artikel"
                class="w-full h-full object-cover transition duration-300 group-hover:scale-102"
            />
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-3">
                <button
                    type="button"
                    @click="triggerSelect"
                    class="px-4 py-2 rounded-xl bg-white dark:bg-[#1c1c1c] text-gray-800 dark:text-[#f0efea] text-xs font-semibold shadow-md hover:bg-gray-50 dark:hover:bg-[#262626] flex items-center gap-1.5 transition cursor-pointer"
                >
                    <RefreshCw class="w-3.5 h-3.5 text-[#0b8043] dark:text-[#3fcb7a]" />
                    <span>Ganti Foto</span>
                </button>
                <button
                    type="button"
                    @click="removeImage"
                    class="px-4 py-2 rounded-xl bg-red-600 text-white text-xs font-semibold shadow-md hover:bg-red-700 flex items-center gap-1.5 transition cursor-pointer"
                >
                    <Trash2 class="w-3.5 h-3.5" />
                    <span>Hapus</span>
                </button>
            </div>
        </div>

        <!-- Dropzone when no image -->
        <div
            v-else
            @click="triggerSelect"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="handleDrop"
            :class="[
                'border-2 border-dashed rounded-2xl p-6 sm:p-8 flex flex-col items-center justify-center text-center cursor-pointer transition-all duration-200',
                isDragging
                    ? 'border-[#0b8043] dark:border-[#3fcb7a] bg-emerald-50/50 dark:bg-[#17321f]/50 scale-[0.99]'
                    : 'border-gray-200 dark:border-[#2e2e2e] hover:border-[#0b8043] dark:hover:border-[#3fcb7a] hover:bg-emerald-50/20 dark:hover:bg-[#17321f]/20 bg-white dark:bg-[#1c1c1c]'
            ]"
        >
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-[#17321f] flex items-center justify-center text-[#0b8043] dark:text-[#3fcb7a] mb-3">
                <UploadCloud class="w-6 h-6" />
            </div>
            <h4 class="text-sm font-semibold text-gray-800 dark:text-[#f0efea] mb-1">
                Unggah Foto Sampul Artikel
            </h4>
            <p class="text-xs text-gray-500 dark:text-[#a3a3a3] max-w-sm mb-3">
                Tarik dan lepaskan file gambar ke sini, atau klik untuk memilih file dari komputer Anda.
            </p>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-gray-100 dark:bg-[#262626] text-[11px] font-medium text-gray-600 dark:text-[#a3a3a3]">
                <ImageIcon class="w-3 h-3 text-gray-400" />
                Format PNG, JPG, WebP (Rasio 16:9 disarankan, maks 3MB)
            </span>
        </div>

        <p v-if="error" class="text-xs text-red-500 mt-1 font-medium">
            {{ error }}
        </p>
    </div>
</template>
