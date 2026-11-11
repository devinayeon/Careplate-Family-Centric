<script setup lang="ts">
import { ref, watch } from 'vue';
import { XCircle, Loader2 } from 'lucide-vue-next';

const props = defineProps<{
    show: boolean;
    foodName: string;
    submitterName: string;
    similarFoodName?: string;
    isSubmitting?: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'confirm', reason: string): void;
}>();

const reason = ref('');
const activePreset = ref<string | null>(null);
const errorMessage = ref<string | null>(null);

const presetOptions = [
    {
        key: 'duplikat',
        label: 'Duplikat',
        getText: (similarName?: string) =>
            similarName
                ? `Sudah ada sebagai "${similarName}" dengan nilai hampir sama. Gunakan item itu saat mencatat.`
                : 'Sudah ada makanan dengan nilai gizi yang sama di database. Gunakan item yang sudah ada saat mencatat.',
    },
    {
        key: 'satuan_salah',
        label: 'Satuan salah',
        getText: () => 'Satuan takaran saji tidak sesuai dengan standar porsi konsumsi umum (gunakan gram/ml/porsi).',
    },
    {
        key: 'sumber_tidak_jelas',
        label: 'Sumber tidak jelas',
        getText: () => 'Sumber rujukan informasi nilai gizi belum jelas atau foto label kemasan makanan buram/tidak terbaca.',
    },
];

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            reason.value = '';
            activePreset.value = null;
            errorMessage.value = null;
        }
    }
);

const selectPreset = (presetKey: string) => {
    activePreset.value = presetKey;
    const found = presetOptions.find((p) => p.key === presetKey);
    if (found) {
        reason.value = found.getText(props.similarFoodName);
        errorMessage.value = null;
    }
};

const handleConfirm = () => {
    if (!reason.value.trim()) {
        errorMessage.value = 'Alasan penolakan wajib diisi.';
        return;
    }
    emit('confirm', reason.value.trim());
};
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 animate-in fade-in duration-150 overflow-y-auto"
    >
        <!-- Backdrop Scrim -->
        <div
            class="fixed inset-0 bg-[#1e2321]/50 backdrop-blur-xs transition-opacity"
            @click="emit('close')"
        />

        <!-- Modal Dialog (Figma Node 36-10381: width 520px, rounded-3xl, p-8) -->
        <div
            class="relative bg-white dark:bg-[#1c1c1c] rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-gray-100 dark:border-[#2e2e2e] space-y-4 z-10 my-auto text-left"
        >
            <!-- 1. Icon Circle (Red Danger) -->
            <div
                class="w-12 h-12 rounded-full bg-[#fde8e6] dark:bg-red-950/40 text-[#b42318] dark:text-red-400 flex items-center justify-center shrink-0"
            >
                <XCircle class="w-6 h-6" />
            </div>

            <!-- 2. Header Title & Subtitle -->
            <div>
                <h3 class="text-xl sm:text-2xl font-bold text-[#191919] dark:text-[#f0efea] tracking-tight">
                    Tolak {{ foodName }}?
                </h3>
                <p class="text-sm text-[#5e5e5e] dark:text-[#a3a3a3] mt-1 leading-relaxed">
                    Alasan dikirim ke <strong class="text-[#191919] dark:text-[#f0efea]">{{ submitterName }}</strong> agar bisa memperbaiki dan mengajukan ulang.
                </p>
            </div>

            <!-- 3. Label & Textarea Alasan Penolakan -->
            <div class="space-y-1.5 pt-1">
                <label class="block text-xs font-bold text-[#191919] dark:text-[#f0efea] uppercase tracking-wider">
                    Alasan penolakan <span class="text-red-500">*</span>
                </label>
                <textarea
                    v-model="reason"
                    rows="4"
                    placeholder="Tuliskan alasan penolakan secara jelas dan solutif..."
                    class="w-full p-3.5 rounded-xl border border-gray-300 dark:border-[#3d3d3d] bg-white dark:bg-[#262626] text-sm text-[#191919] dark:text-[#f0efea] placeholder-gray-400 dark:placeholder-[#737373] focus:border-[#b42318] focus:ring-2 focus:ring-[#b42318]/15 focus:outline-none transition leading-relaxed"
                ></textarea>
                <p v-if="errorMessage" class="text-xs text-red-500 font-medium">
                    {{ errorMessage }}
                </p>
            </div>

            <!-- 4. Quick Preset Chips (Duplikat, Satuan salah, Sumber tidak jelas) -->
            <div class="space-y-1.5">
                <span class="block text-[11px] font-semibold text-[#5e5e5e] dark:text-[#a3a3a3] uppercase tracking-wider">
                    Pilihan Cepat:
                </span>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="preset in presetOptions"
                        :key="preset.key"
                        type="button"
                        @click="selectPreset(preset.key)"
                        :class="[
                            'h-9 px-4 rounded-full text-xs font-bold transition cursor-pointer',
                            activePreset === preset.key
                                ? 'bg-[#191919] dark:bg-[#f0efea] text-white dark:text-[#121212] shadow-xs'
                                : 'bg-[#f4f3f0] dark:bg-[#262626] text-[#191919] dark:text-[#f0efea] hover:bg-[#eceae5] dark:hover:bg-[#3d3d3d]'
                        ]"
                    >
                        {{ preset.label }}
                    </button>
                </div>
            </div>

            <!-- 5. Actions Footer (Batal & Tolak Pengajuan) -->
            <div class="pt-3 flex items-center justify-end gap-3 border-t border-[#eceae5] dark:border-[#2e2e2e]">
                <button
                    type="button"
                    @click="emit('close')"
                    :disabled="isSubmitting"
                    class="px-5 py-2.5 rounded-full border-[1.5px] border-[#191919] dark:border-[#3d3d3d] text-xs font-bold text-[#191919] dark:text-[#f0efea] hover:bg-gray-100 dark:hover:bg-[#262626] transition cursor-pointer disabled:opacity-50"
                >
                    Batal
                </button>
                <button
                    type="button"
                    @click="handleConfirm"
                    :disabled="isSubmitting || !reason.trim()"
                    class="px-6 py-2.5 rounded-full bg-[#b42318] hover:bg-[#991b1b] text-white text-xs font-bold shadow-sm transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                >
                    <Loader2 v-if="isSubmitting" class="w-4 h-4 animate-spin" />
                    <span>Tolak Pengajuan</span>
                </button>
            </div>
        </div>
    </div>
</template>
