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
        getText: () =>
            'Satuan takaran saji tidak sesuai dengan standar porsi konsumsi umum (gunakan gram/ml/porsi).',
    },
    {
        key: 'sumber_tidak_jelas',
        label: 'Sumber tidak jelas',
        getText: () =>
            'Sumber rujukan informasi nilai gizi belum jelas atau foto label kemasan makanan buram/tidak terbaca.',
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
    },
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
        class="animate-in fade-in fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4 duration-150"
    >
        <!-- Backdrop Scrim -->
        <div
            class="fixed inset-0 bg-[#1e2321]/50 backdrop-blur-xs transition-opacity"
            @click="emit('close')"
        />

        <!-- Modal Dialog (Figma Node 36-10381: width 520px, rounded-3xl, p-8) -->
        <div
            class="relative z-10 my-auto w-full max-w-lg space-y-4 rounded-3xl border border-gray-100 bg-white p-6 text-left shadow-2xl sm:p-8 dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
        >
            <!-- 1. Icon Circle (Red Danger) -->
            <div
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#fde8e6] text-[#b42318] dark:bg-red-950/40 dark:text-red-400"
            >
                <XCircle class="h-6 w-6" />
            </div>

            <!-- 2. Header Title & Subtitle -->
            <div>
                <h3
                    class="text-xl font-bold tracking-tight text-[#191919] sm:text-2xl dark:text-[#f0efea]"
                >
                    Tolak {{ foodName }}?
                </h3>
                <p
                    class="mt-1 text-sm leading-relaxed text-[#5e5e5e] dark:text-[#a3a3a3]"
                >
                    Alasan dikirim ke
                    <strong class="text-[#191919] dark:text-[#f0efea]">{{
                        submitterName
                    }}</strong>
                    agar bisa memperbaiki dan mengajukan ulang.
                </p>
            </div>

            <!-- 3. Label & Textarea Alasan Penolakan -->
            <div class="space-y-1.5 pt-1">
                <label
                    class="block text-xs font-bold tracking-wider text-[#191919] uppercase dark:text-[#f0efea]"
                >
                    Alasan penolakan <span class="text-red-500">*</span>
                </label>
                <textarea
                    v-model="reason"
                    rows="4"
                    placeholder="Tuliskan alasan penolakan secara jelas dan solutif..."
                    class="w-full rounded-xl border border-gray-300 bg-white p-3.5 text-sm leading-relaxed text-[#191919] placeholder-gray-400 transition focus:border-[#b42318] focus:ring-2 focus:ring-[#b42318]/15 focus:outline-none dark:border-[#3d3d3d] dark:bg-[#262626] dark:text-[#f0efea] dark:placeholder-[#737373]"
                ></textarea>
                <p v-if="errorMessage" class="text-xs font-medium text-red-500">
                    {{ errorMessage }}
                </p>
            </div>

            <!-- 4. Quick Preset Chips (Duplikat, Satuan salah, Sumber tidak jelas) -->
            <div class="space-y-1.5">
                <span
                    class="block text-[11px] font-semibold tracking-wider text-[#5e5e5e] uppercase dark:text-[#a3a3a3]"
                >
                    Pilihan Cepat:
                </span>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="preset in presetOptions"
                        :key="preset.key"
                        type="button"
                        @click="selectPreset(preset.key)"
                        :class="[
                            'h-9 cursor-pointer rounded-full px-4 text-xs font-bold transition',
                            activePreset === preset.key
                                ? 'bg-[#191919] text-white shadow-xs dark:bg-[#f0efea] dark:text-[#121212]'
                                : 'bg-[#f4f3f0] text-[#191919] hover:bg-[#eceae5] dark:bg-[#262626] dark:text-[#f0efea] dark:hover:bg-[#3d3d3d]',
                        ]"
                    >
                        {{ preset.label }}
                    </button>
                </div>
            </div>

            <!-- 5. Actions Footer (Batal & Tolak Pengajuan) -->
            <div
                class="flex items-center justify-end gap-3 border-t border-[#eceae5] pt-3 dark:border-[#2e2e2e]"
            >
                <button
                    type="button"
                    @click="emit('close')"
                    :disabled="isSubmitting"
                    class="cursor-pointer rounded-full border-[1.5px] border-[#191919] px-5 py-2.5 text-xs font-bold text-[#191919] transition hover:bg-gray-100 disabled:opacity-50 dark:border-[#3d3d3d] dark:text-[#f0efea] dark:hover:bg-[#262626]"
                >
                    Batal
                </button>
                <button
                    type="button"
                    @click="handleConfirm"
                    :disabled="isSubmitting || !reason.trim()"
                    class="flex cursor-pointer items-center gap-1.5 rounded-full bg-[#b42318] px-6 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#991b1b] disabled:opacity-50"
                >
                    <Loader2 v-if="isSubmitting" class="h-4 w-4 animate-spin" />
                    <span>Tolak Pengajuan</span>
                </button>
            </div>
        </div>
    </div>
</template>
