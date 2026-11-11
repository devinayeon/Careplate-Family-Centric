<script setup lang="ts">
import { Video, Calendar, Clock, ExternalLink } from 'lucide-vue-next';

defineProps<{
    metadata: {
        tanggal?: string;
        mulai?: string;
        durasi?: string;
        link?: string;
    };
    isMe?: boolean;
}>();
</script>

<template>
    <div
        class="w-full max-w-md rounded-2xl border p-4 text-left transition-all"
        :class="
            isMe
                ? 'border-emerald-300 bg-emerald-50 text-gray-900 dark:border-emerald-700/60 dark:bg-emerald-950/40 dark:text-emerald-50'
                : 'border-emerald-200 bg-[#f0fbf4] text-gray-900 dark:border-emerald-800 dark:bg-[#13231a] dark:text-[#f0efea]'
        "
    >
        <div class="flex items-center gap-2 font-bold text-emerald-800 dark:text-emerald-400">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-600 text-white shadow-2xs">
                <Video class="h-4 w-4" />
            </div>
            <span class="text-sm">Jadwal Sesi Tatap Muka Online</span>
        </div>

        <div class="mt-3 space-y-1.5 text-xs">
            <div class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                <Calendar class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                <span class="font-medium">{{ metadata.tanggal || 'Sesuai Kesepakatan' }}</span>
            </div>
            <div class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                <Clock class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                <span>{{ metadata.mulai || '19.00' }} WIB (Durasi: {{ metadata.durasi || '30 mnt' }})</span>
            </div>
        </div>

        <div class="mt-4 pt-3 border-t border-emerald-200/80 dark:border-emerald-800/80">
            <a
                v-if="metadata.link"
                :href="metadata.link"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow-xs transition-colors hover:bg-emerald-700 focus:ring-2 focus:ring-emerald-500/30"
            >
                <Video class="h-3.5 w-3.5" />
                <span>Gabung Meeting</span>
                <ExternalLink class="h-3 w-3 opacity-80" />
            </a>
            <div v-else class="text-center text-xs italic text-gray-400">
                Tautan meeting belum disertakan.
            </div>
        </div>
    </div>
</template>
