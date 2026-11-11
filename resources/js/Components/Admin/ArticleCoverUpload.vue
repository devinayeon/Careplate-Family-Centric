<script setup lang="ts">
import { ref, watch } from 'vue';
import {
    UploadCloud,
    Image as ImageIcon,
    Trash2,
    RefreshCw,
} from 'lucide-vue-next';

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
    },
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
            class="group relative aspect-[21/9] max-h-64 overflow-hidden rounded-2xl border border-gray-200 bg-gray-50 shadow-xs dark:border-[#2e2e2e] dark:bg-[#262626]"
        >
            <img
                :src="previewUrl"
                alt="Foto Sampul Artikel"
                class="h-full w-full object-cover transition duration-300 group-hover:scale-102"
            />
            <div
                class="absolute inset-0 flex items-center justify-center gap-3 bg-black/40 opacity-0 transition-opacity group-hover:opacity-100"
            >
                <button
                    type="button"
                    @click="triggerSelect"
                    class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-white px-4 py-2 text-xs font-semibold text-gray-800 shadow-md transition hover:bg-gray-50 dark:bg-[#1c1c1c] dark:text-[#f0efea] dark:hover:bg-[#262626]"
                >
                    <RefreshCw
                        class="h-3.5 w-3.5 text-[#0b8043] dark:text-[#3fcb7a]"
                    />
                    <span>Ganti Foto</span>
                </button>
                <button
                    type="button"
                    @click="removeImage"
                    class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-red-600 px-4 py-2 text-xs font-semibold text-white shadow-md transition hover:bg-red-700"
                >
                    <Trash2 class="h-3.5 w-3.5" />
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
                'flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed p-6 text-center transition-all duration-200 sm:p-8',
                isDragging
                    ? 'scale-[0.99] border-[#0b8043] bg-emerald-50/50 dark:border-[#3fcb7a] dark:bg-[#17321f]/50'
                    : 'border-gray-200 bg-white hover:border-[#0b8043] hover:bg-emerald-50/20 dark:border-[#2e2e2e] dark:bg-[#1c1c1c] dark:hover:border-[#3fcb7a] dark:hover:bg-[#17321f]/20',
            ]"
        >
            <div
                class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-[#0b8043] dark:bg-[#17321f] dark:text-[#3fcb7a]"
            >
                <UploadCloud class="h-6 w-6" />
            </div>
            <h4
                class="mb-1 text-sm font-semibold text-gray-800 dark:text-[#f0efea]"
            >
                Unggah Foto Sampul Artikel
            </h4>
            <p class="mb-3 max-w-sm text-xs text-gray-500 dark:text-[#a3a3a3]">
                Tarik dan lepaskan file gambar ke sini, atau klik untuk memilih
                file dari komputer Anda.
            </p>
            <span
                class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-[11px] font-medium text-gray-600 dark:bg-[#262626] dark:text-[#a3a3a3]"
            >
                <ImageIcon class="h-3 w-3 text-gray-400" />
                Format PNG, JPG, WebP (Rasio 16:9 disarankan, maks 3MB)
            </span>
        </div>

        <p v-if="error" class="mt-1 text-xs font-medium text-red-500">
            {{ error }}
        </p>
    </div>
</template>
