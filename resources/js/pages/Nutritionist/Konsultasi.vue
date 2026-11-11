<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import NutritionistLayout from '@/Layouts/NutritionistLayout.vue';
import {
    Video,
    Send,
    Image as ImageIcon,
    ShieldCheck,
    CheckCircle2,
} from 'lucide-vue-next';

const props = defineProps<{
    tabs: {
        baru: number;
        aktif: number;
        selesai: number;
    };
    klien_list: Array<{
        id: number;
        inisial: string;
        nama: string;
        subjudul: string;
        waktu: string;
        unread: boolean;
        status: string;
    }>;
    active_client: {
        id: number;
        inisial: string;
        nama: string;
        subjudul: string;
        klien_info: {
            untuk: string;
            topik: string;
            diajukan: string;
            keluhan: string;
            data_dibagikan: {
                bmi_usia: string;
                energi_rata_rata: string;
                camilan_manis: string;
                sayur_tercatat: string;
            };
        };
        messages: Array<{
            id: number;
            sender: 'client' | 'nutritionist';
            sender_name: string;
            time: string;
            content: string;
        }>;
    };
}>();

const activeTab = ref('aktif');
const selectedClientId = ref(props.active_client?.id ?? 1);
const replyText = ref('');
const messagesList = ref([...(props.active_client?.messages ?? [])]);

const sendMessage = () => {
    if (!replyText.value.trim()) return;

    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');

    messagesList.value.push({
        id: Date.now(),
        sender: 'nutritionist',
        sender_name: 'dr. Sari Wulandari',
        time: `${hours}.${minutes}`,
        content: replyText.value.trim(),
    });

    replyText.value = '';
};
</script>

<template>
    <Head title="Konsultasi Ahli Gizi" />

    <NutritionistLayout :full-width="true">
        <!-- 3-Column Workspace Matching Figma 12.1 -->
        <div
            class="grid h-[calc(100vh-6rem)] grid-cols-1 gap-4 pb-2 lg:grid-cols-12"
        >
            <!-- ============================================== -->
            <!-- COLUMN 1: Daftar Konsultasi & Tabs (3.5 cols) -->
            <!-- ============================================== -->
            <div
                class="flex flex-col rounded-3xl border border-gray-100 bg-white p-4 shadow-xs lg:col-span-3"
            >
                <h2 class="px-2 py-1 text-lg font-bold text-gray-900">
                    Konsultasi
                </h2>

                <!-- Tab Filters -->
                <div
                    class="mt-2 mb-3 flex items-center gap-4 border-b border-gray-100 px-2 text-xs font-semibold"
                >
                    <button
                        @click="activeTab = 'baru'"
                        class="relative pb-2.5 transition-colors"
                        :class="
                            activeTab === 'baru'
                                ? 'border-b-2 border-gray-900 font-bold text-gray-900'
                                : 'text-gray-400 hover:text-gray-600'
                        "
                    >
                        Baru ({{ tabs.baru }})
                    </button>
                    <button
                        @click="activeTab = 'aktif'"
                        class="relative pb-2.5 transition-colors"
                        :class="
                            activeTab === 'aktif'
                                ? 'border-b-2 border-gray-900 font-bold text-gray-900'
                                : 'text-gray-400 hover:text-gray-600'
                        "
                    >
                        Aktif ({{ tabs.aktif }})
                    </button>
                    <button
                        @click="activeTab = 'selesai'"
                        class="relative pb-2.5 transition-colors"
                        :class="
                            activeTab === 'selesai'
                                ? 'border-b-2 border-gray-900 font-bold text-gray-900'
                                : 'text-gray-400 hover:text-gray-600'
                        "
                    >
                        Selesai
                    </button>
                </div>

                <!-- Client List Items -->
                <div class="flex-1 space-y-1.5 overflow-y-auto pr-1">
                    <button
                        v-for="klien in klien_list"
                        :key="klien.id"
                        @click="selectedClientId = klien.id"
                        class="flex w-full items-center justify-between rounded-2xl p-3 text-left transition-all"
                        :class="
                            selectedClientId === klien.id
                                ? 'bg-[#e7f5ec] text-gray-900 shadow-2xs'
                                : 'text-gray-700 hover:bg-gray-50'
                        "
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-xs font-bold"
                                :class="
                                    selectedClientId === klien.id
                                        ? 'bg-emerald-200/80 text-[#0f6b36]'
                                        : 'bg-gray-100 text-gray-600'
                                "
                            >
                                {{ klien.inisial }}
                            </div>

                            <div class="min-w-0">
                                <div
                                    class="truncate text-xs font-bold text-gray-900"
                                >
                                    {{ klien.nama }}
                                </div>
                                <div
                                    class="mt-0.5 truncate text-[11px] text-gray-400"
                                >
                                    {{ klien.subjudul }}
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex shrink-0 flex-col items-end gap-1.5 pl-2"
                        >
                            <span
                                class="text-[10px] font-medium text-gray-400"
                                >{{ klien.waktu }}</span
                            >
                            <span
                                v-if="klien.unread"
                                class="h-2 w-2 rounded-full bg-orange-500"
                            ></span>
                        </div>
                    </button>
                </div>
            </div>

            <!-- ============================================== -->
            <!-- COLUMN 2: Chat Conversation Stream (5.5 cols) -->
            <!-- ============================================== -->
            <div
                class="flex flex-col overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-xs lg:col-span-6"
            >
                <!-- Top Chat Header -->
                <div
                    class="flex items-center justify-between gap-4 border-b border-gray-100 px-6 py-4"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-xs font-bold text-gray-700"
                        >
                            {{ active_client.inisial }}
                        </div>
                        <div>
                            <div
                                class="text-sm leading-tight font-bold text-gray-900"
                            >
                                {{ active_client.nama }}
                            </div>
                            <div
                                class="mt-0.5 text-xs leading-tight text-gray-400"
                            >
                                {{ active_client.subjudul }}
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 px-3.5 py-1.5 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-50"
                        >
                            <Video class="h-3.5 w-3.5 text-gray-600" />
                            <span>Meeting</span>
                        </button>
                        <button
                            class="rounded-full border border-gray-200 px-3.5 py-1.5 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-50"
                        >
                            <span>Selesaikan</span>
                        </button>
                    </div>
                </div>

                <!-- Messages Bubble Stream -->
                <div class="flex-1 space-y-4 overflow-y-auto p-6">
                    <div
                        v-for="msg in messagesList"
                        :key="msg.id"
                        class="flex flex-col"
                        :class="
                            msg.sender === 'nutritionist'
                                ? 'items-end'
                                : 'items-start'
                        "
                    >
                        <!-- Sender name for client -->
                        <span
                            v-if="msg.sender === 'client'"
                            class="mb-1 pl-1 text-[11px] font-semibold text-gray-500"
                        >
                            {{ msg.sender_name }}
                        </span>

                        <div
                            class="max-w-md rounded-2xl p-4 text-xs leading-relaxed sm:text-sm"
                            :class="
                                msg.sender === 'nutritionist'
                                    ? 'rounded-tr-xs bg-[#0f6b36] text-white shadow-xs'
                                    : 'rounded-tl-xs border border-gray-200 bg-white text-gray-800 shadow-2xs'
                            "
                        >
                            {{ msg.content }}
                        </div>

                        <span class="mt-1 px-1 text-[10px] text-gray-400">
                            {{ msg.time }}
                        </span>
                    </div>
                </div>

                <!-- Bottom Input & Attachment Bar -->
                <div class="border-t border-gray-100 bg-white p-4">
                    <form
                        @submit.prevent="sendMessage"
                        class="flex items-center gap-2"
                    >
                        <div class="relative flex-1">
                            <input
                                v-model="replyText"
                                type="text"
                                :placeholder="`Balas ${active_client.nama.split(' ')[0]}`"
                                class="w-full rounded-full border-none bg-[#f4f5f6] py-3 pr-10 pl-4 text-sm text-gray-800 placeholder-gray-400 transition-all outline-none hover:bg-[#eceef0] focus:bg-white focus:ring-2 focus:ring-[#0f6b36]/30"
                            />
                            <button
                                type="button"
                                class="absolute top-1/2 right-3.5 -translate-y-1/2 text-gray-400 transition-colors hover:text-gray-600"
                            >
                                <ImageIcon class="h-4 w-4" />
                            </button>
                        </div>

                        <button
                            type="submit"
                            :disabled="!replyText.trim()"
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#0f6b36] text-white shadow-xs transition-all hover:bg-[#0c592c] disabled:opacity-40"
                        >
                            <Send class="h-4 w-4" />
                        </button>
                    </form>
                </div>
            </div>

            <!-- ============================================== -->
            <!-- COLUMN 3: Konteks Klien Medical Panel (3 cols) -->
            <!-- ============================================== -->
            <div
                class="flex flex-col overflow-y-auto rounded-3xl border border-gray-100 bg-white p-5 shadow-xs lg:col-span-3"
            >
                <h2 class="mb-4 text-base font-bold text-gray-900">
                    Konteks klien
                </h2>

                <div class="space-y-3 border-b border-gray-100 pb-4 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Untuk</span>
                        <span class="font-semibold text-gray-900">{{
                            active_client.klien_info.untuk
                        }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Topik</span>
                        <span class="font-semibold text-gray-900">{{
                            active_client.klien_info.topik
                        }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Diajukan</span>
                        <span class="font-semibold text-gray-900">{{
                            active_client.klien_info.diajukan
                        }}</span>
                    </div>
                </div>

                <!-- Keluhan Box -->
                <div
                    class="my-4 rounded-2xl border border-gray-100 bg-[#f9fafb] p-4"
                >
                    <div class="mb-1.5 text-[11px] font-bold text-gray-700">
                        Keluhan
                    </div>
                    <p class="text-xs leading-relaxed text-gray-600">
                        {{ active_client.klien_info.keluhan }}
                    </p>
                </div>

                <!-- Data Dibagikan Klien -->
                <div class="mt-2 space-y-3">
                    <div
                        class="mb-3 flex items-center gap-1.5 text-xs font-bold text-emerald-800"
                    >
                        <ShieldCheck class="h-4 w-4 text-[#0f6b36]" />
                        <span>Data dibagikan klien</span>
                    </div>

                    <div class="flex items-center justify-between py-1 text-xs">
                        <span class="text-gray-500">BMI untuk usia</span>
                        <span class="font-bold text-gray-900">{{
                            active_client.klien_info.data_dibagikan.bmi_usia
                        }}</span>
                    </div>

                    <div class="flex items-center justify-between py-1 text-xs">
                        <span class="text-gray-500"
                            >Energi rata-rata 7 hari</span
                        >
                        <span class="font-bold text-gray-900">{{
                            active_client.klien_info.data_dibagikan
                                .energi_rata_rata
                        }}</span>
                    </div>

                    <div class="flex items-center justify-between py-1 text-xs">
                        <span class="text-gray-500">Camilan manis</span>
                        <span class="font-bold text-gray-900">{{
                            active_client.klien_info.data_dibagikan
                                .camilan_manis
                        }}</span>
                    </div>

                    <div class="flex items-center justify-between py-1 text-xs">
                        <span class="text-gray-500">Sayur tercatat</span>
                        <span class="font-bold text-gray-900">{{
                            active_client.klien_info.data_dibagikan
                                .sayur_tercatat
                        }}</span>
                    </div>
                </div>
            </div>
        </div>
    </NutritionistLayout>
</template>
