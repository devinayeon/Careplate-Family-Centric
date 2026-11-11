<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import NutritionistLayout from '@/Layouts/NutritionistLayout.vue';
import MeetingCard from '@/Components/Consultation/MeetingCard.vue';
import ScheduleMeetingModal from '@/Components/Consultation/ScheduleMeetingModal.vue';
import RejectConsultationModal from '@/Components/Consultation/RejectConsultationModal.vue';
import CompleteConsultationModal from '@/Components/Consultation/CompleteConsultationModal.vue';
import {
    Video,
    Send,
    ShieldCheck,
    CheckCircle2,
    XCircle,
    Clock,
    AlertCircle,
    Check,
} from 'lucide-vue-next';

interface Message {
    id: number;
    sender: 'client' | 'nutritionist';
    is_me: boolean;
    sender_name: string;
    tipe_pesan: string;
    content: string;
    metadata?: any;
    time: string;
    date?: string;
}

const props = defineProps<{
    active_tab: 'menunggu' | 'berlangsung' | 'selesai' | 'ditolak';
    tabs: {
        menunggu: number;
        berlangsung: number;
        selesai: number;
        ditolak: number;
    };
    klien_list: Array<{
        id: number;
        inisial: string;
        nama: string;
        subjudul: string;
        waktu: string;
        unread: boolean;
        status: string;
        created_at: string;
    }>;
    active_client: {
        id: number;
        nomor_konsultasi: string;
        inisial: string;
        nama: string;
        subjudul: string;
        status: 'menunggu' | 'berlangsung' | 'selesai' | 'ditolak';
        alasan_penolakan?: string;
        klien_info: {
            untuk: string;
            topik: string;
            diajukan: string;
            keluhan: string;
            data_dibagikan: {
                bmi_usia?: string;
                energi_rata_rata?: string;
                camilan_manis?: string;
                sayur_tercatat?: string;
            };
        };
        messages: Array<Message>;
    } | null;
}>();

const currentTab = ref(props.active_tab || 'berlangsung');
const selectedClientId = ref<number | null>(props.active_client?.id ?? null);
const replyText = ref('');
const messagesList = ref<Array<Message>>([...(props.active_client?.messages ?? [])]);
const messageContainer = ref<HTMLDivElement | null>(null);

// Modal states
const showMeetingModal = ref(false);
const showRejectModal = ref(false);
const showCompleteModal = ref(false);
const actionLoading = ref(false);

const scrollToBottom = () => {
    nextTick(() => {
        if (messageContainer.value) {
            messageContainer.value.scrollTop = messageContainer.value.scrollHeight;
        }
    });
};

watch(
    () => props.active_client,
    (newVal) => {
        if (newVal) {
            selectedClientId.value = newVal.id;
            messagesList.value = [...(newVal.messages ?? [])];
            scrollToBottom();
        } else {
            messagesList.value = [];
        }
    },
    { immediate: true }
);

// Switch tab filter
const switchTab = (tab: 'menunggu' | 'berlangsung' | 'selesai' | 'ditolak') => {
    currentTab.value = tab;
    router.get(
        route('nutritionist.consultations'),
        { tab },
        { preserveState: true, preserveScroll: true }
    );
};

// Select client in sidebar
const selectClient = (klienId: number) => {
    selectedClientId.value = klienId;
    router.get(
        route('nutritionist.consultations'),
        { tab: currentTab.value, id: klienId },
        { preserveState: true, preserveScroll: true }
    );
};

const isSending = ref(false);

// Send text message
const sendMessage = async () => {
    if (
        !replyText.value.trim() ||
        !props.active_client ||
        props.active_client.status !== 'berlangsung' ||
        isSending.value
    )
        return;

    const textToSend = replyText.value.trim();
    replyText.value = '';
    isSending.value = true;

    // Optimistic UI push
    const now = new Date();
    const tempId = Date.now();
    messagesList.value.push({
        id: tempId,
        sender: 'nutritionist',
        is_me: true,
        sender_name: 'Saya',
        tipe_pesan: 'teks',
        content: textToSend,
        time: `${String(now.getHours()).padStart(2, '0')}.${String(now.getMinutes()).padStart(2, '0')}`,
    });
    scrollToBottom();

    const csrfToken =
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

    try {
        const response = await fetch(
            route('nutritionist.consultations.send_message', props.active_client.id),
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ pesan: textToSend }),
            }
        );

        if (response.ok) {
            const data = await response.json();
            if (data.success && data.message_data) {
                // Replace optimistic message in-place with real DB ID
                const idx = messagesList.value.findIndex((m) => m.id === tempId);
                if (idx !== -1) {
                    messagesList.value[idx] = data.message_data;
                } else if (!messagesList.value.some((m) => m.id === data.message_data.id)) {
                    messagesList.value.push(data.message_data);
                }
            }
        } else {
            messagesList.value = messagesList.value.filter((m) => m.id !== tempId);
        }
    } catch {
        messagesList.value = messagesList.value.filter((m) => m.id !== tempId);
    } finally {
        isSending.value = false;
        scrollToBottom();
    }
};

// Approve consultation
const approveConsultation = () => {
    if (!props.active_client) return;
    actionLoading.value = true;
    router.post(
        route('nutritionist.consultations.approve', props.active_client.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                actionLoading.value = false;
            },
        }
    );
};

// Reject consultation
const handleRejectSubmit = (alasan: string) => {
    if (!props.active_client) return;
    actionLoading.value = true;
    router.post(
        route('nutritionist.consultations.reject', props.active_client.id),
        { alasan_penolakan: alasan },
        {
            preserveScroll: true,
            onFinish: () => {
                actionLoading.value = false;
                showRejectModal.value = false;
            },
        }
    );
};

// Complete consultation
const handleCompleteConfirm = () => {
    if (!props.active_client) return;
    actionLoading.value = true;
    router.post(
        route('nutritionist.consultations.complete', props.active_client.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                actionLoading.value = false;
                showCompleteModal.value = false;
            },
        }
    );
};

// Submit meeting schedule
const handleMeetingSubmit = (meetingData: { tanggal: string; mulai: string; durasi: string; link: string }) => {
    if (!props.active_client) return;
    actionLoading.value = true;
    router.post(
        route('nutritionist.consultations.meeting', props.active_client.id),
        meetingData,
        {
            preserveScroll: true,
            onFinish: () => {
                actionLoading.value = false;
                showMeetingModal.value = false;
            },
        }
    );
};

// Real-Time Polling Sync
let pollTimer: any = null;

const pollNewMessages = async () => {
    if (!props.active_client || props.active_client.status !== 'berlangsung') return;

    // Ambil ID riil terakhir (bukan ID temporer Date.now())
    const realMessages = messagesList.value.filter((m) => m.id < 1000000000);
    const lastRealMsg = realMessages[realMessages.length - 1];
    const afterId = lastRealMsg ? lastRealMsg.id : 0;

    try {
        const response = await fetch(
            route('nutritionist.consultations.stream', {
                konsultasi: props.active_client.id,
                after_id: afterId,
            }),
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }
        );

        if (response.ok) {
            const data = await response.json();
            if (data.messages && data.messages.length > 0) {
                let hasNew = false;
                for (const newM of data.messages) {
                    // Cek apakah pesan sudah ada berdasarkan ID riil
                    if (messagesList.value.some((m) => m.id === newM.id)) {
                        continue;
                    }

                    // Cek apakah cocok dengan pesan optimistik lokal yang belum ter-resolve
                    const tempIdx = messagesList.value.findIndex(
                        (m) =>
                            m.id >= 1000000000 &&
                            m.is_me === newM.is_me &&
                            m.content === newM.content
                    );

                    if (tempIdx !== -1) {
                        messagesList.value[tempIdx] = newM;
                        hasNew = true;
                    } else {
                        messagesList.value.push(newM);
                        hasNew = true;
                    }
                }
                if (hasNew) {
                    scrollToBottom();
                }
            }
        }
    } catch {
        // silent catch on network glitch
    }
};

onMounted(() => {
    scrollToBottom();
    pollTimer = setInterval(pollNewMessages, 3000);
});

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
});
</script>

<template>
    <Head title="Workspace Konsultasi Ahli Gizi" />

    <NutritionistLayout :full-width="true">
        <!-- 3-Column Workspace Matching Figma 12.1 -->
        <div class="grid h-[calc(100vh-6rem)] grid-cols-1 gap-4 pb-2 lg:grid-cols-12">
            <!-- ============================================== -->
            <!-- COLUMN 1: Daftar Konsultasi & Tabs (3.5 cols) -->
            <!-- ============================================== -->
            <div class="flex flex-col rounded-3xl border border-gray-100 bg-white p-4 shadow-xs dark:border-[#262626] dark:bg-[#1a1a1a] lg:col-span-3">
                <div class="flex items-center justify-between px-2 py-1">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-[#f0efea]">
                        Konsultasi
                    </h2>
                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700 dark:bg-[#17321f] dark:text-[#3fcb7a]">
                        {{ tabs.menunggu + tabs.berlangsung }} Aktif
                    </span>
                </div>

                <!-- Tab Filters -->
                <div class="mt-2 mb-3 flex items-center gap-3 border-b border-gray-100 px-2 text-xs font-semibold overflow-x-auto custom-scrollbar dark:border-[#262626]">
                    <button
                        @click="switchTab('menunggu')"
                        class="relative pb-2.5 whitespace-nowrap transition-colors"
                        :class="
                            currentTab === 'menunggu'
                                ? 'border-b-2 border-amber-600 font-bold text-amber-600 dark:border-amber-400 dark:text-amber-400'
                                : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-200'
                        "
                    >
                        Menunggu ({{ tabs.menunggu }})
                    </button>
                    <button
                        @click="switchTab('berlangsung')"
                        class="relative pb-2.5 whitespace-nowrap transition-colors"
                        :class="
                            currentTab === 'berlangsung'
                                ? 'border-b-2 border-[#0f6b36] font-bold text-[#0f6b36] dark:border-[#3fcb7a] dark:text-[#3fcb7a]'
                                : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-200'
                        "
                    >
                        Berlangsung ({{ tabs.berlangsung }})
                    </button>
                    <button
                        @click="switchTab('selesai')"
                        class="relative pb-2.5 whitespace-nowrap transition-colors"
                        :class="
                            currentTab === 'selesai'
                                ? 'border-b-2 border-gray-900 font-bold text-gray-900 dark:border-gray-200 dark:text-gray-200'
                                : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-200'
                        "
                    >
                        Selesai
                    </button>
                    <button
                        @click="switchTab('ditolak')"
                        class="relative pb-2.5 whitespace-nowrap transition-colors"
                        :class="
                            currentTab === 'ditolak'
                                ? 'border-b-2 border-red-600 font-bold text-red-600 dark:border-red-400 dark:text-red-400'
                                : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-200'
                        "
                    >
                        Ditolak
                    </button>
                </div>

                <!-- Client List Items -->
                <div class="flex-1 space-y-1.5 overflow-y-auto pr-1">
                    <div
                        v-if="klien_list.length === 0"
                        class="flex flex-col items-center justify-center py-12 text-center text-xs text-gray-400 dark:text-gray-500"
                    >
                        <Clock class="mb-2 h-7 w-7 text-gray-300 dark:text-gray-600" />
                        <p>Tidak ada konsultasi pada tab {{ currentTab }}.</p>
                    </div>

                    <button
                        v-for="klien in klien_list"
                        :key="klien.id"
                        @click="selectClient(klien.id)"
                        class="flex w-full items-center justify-between rounded-2xl p-3 text-left transition-all"
                        :class="
                            selectedClientId === klien.id
                                ? 'bg-[#e7f5ec] text-gray-900 shadow-2xs dark:bg-[#17321f] dark:text-[#3fcb7a]'
                                : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-[#222]'
                        "
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-xs font-bold"
                                :class="
                                    selectedClientId === klien.id
                                        ? 'bg-emerald-200/80 text-[#0f6b36] dark:bg-[#204a2c] dark:text-[#3fcb7a]'
                                        : 'bg-gray-100 text-gray-600 dark:bg-[#262626] dark:text-gray-400'
                                "
                            >
                                {{ klien.inisial }}
                            </div>

                            <div class="min-w-0">
                                <div class="truncate text-xs font-bold text-gray-900 dark:text-[#f0efea]">
                                    {{ klien.nama }}
                                </div>
                                <div class="mt-0.5 truncate text-[11px] text-gray-400 dark:text-gray-400">
                                    {{ klien.subjudul }}
                                </div>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-col items-end gap-1.5 pl-2">
                            <span class="text-[10px] font-medium text-gray-400">
                                {{ klien.waktu }}
                            </span>
                            <span
                                v-if="klien.status === 'menunggu'"
                                class="rounded-full bg-amber-500 px-1.5 py-0.2 text-[9px] font-bold text-white"
                            >
                                Baru
                            </span>
                            <span
                                v-else-if="klien.unread"
                                class="h-2 w-2 rounded-full bg-orange-500"
                            ></span>
                        </div>
                    </button>
                </div>
            </div>

            <!-- ============================================== -->
            <!-- COLUMN 2: Chat Conversation Stream (5.5 cols) -->
            <!-- ============================================== -->
            <div class="flex flex-col overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-xs dark:border-[#262626] dark:bg-[#1a1a1a] lg:col-span-6">
                <!-- If No Client Selected -->
                <div
                    v-if="!active_client"
                    class="flex flex-1 flex-col items-center justify-center p-8 text-center"
                >
                    <div class="flex h-16 w-16 items-center justify-center rounded-3xl bg-gray-100 text-gray-400 dark:bg-[#262626]">
                        <Clock class="h-8 w-8" />
                    </div>
                    <h3 class="mt-4 text-sm font-bold text-gray-900 dark:text-[#f0efea]">
                        Pilih Konsultasi
                    </h3>
                    <p class="mt-1 max-w-xs text-xs text-gray-400">
                        Pilih salah satu klien dari daftar di sebelah kiri untuk melihat rincian dan riwayat percakapan.
                    </p>
                </div>

                <template v-else>
                    <!-- Top Chat Header -->
                    <div class="flex items-center justify-between gap-4 border-b border-gray-100 px-6 py-4 dark:border-[#262626]">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-xs font-bold text-gray-700 dark:bg-[#262626] dark:text-[#f0efea]">
                                {{ active_client.inisial }}
                            </div>
                            <div>
                                <div class="text-sm font-bold text-gray-900 dark:text-[#f0efea]">
                                    {{ active_client.nama }}
                                </div>
                                <div class="mt-0.5 text-xs text-gray-400">
                                    {{ active_client.subjudul }}
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons based on status -->
                        <div class="flex items-center gap-2">
                            <!-- When Waiting for Approval -->
                            <template v-if="active_client.status === 'menunggu'">
                                <button
                                    @click="showRejectModal = true"
                                    :disabled="actionLoading"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50/60 px-3.5 py-1.5 text-xs font-semibold text-red-600 transition-colors hover:bg-red-100 dark:border-red-900 dark:bg-red-950/40 dark:text-red-400"
                                >
                                    <XCircle class="h-3.5 w-3.5" />
                                    <span>Tolak</span>
                                </button>
                                <button
                                    @click="approveConsultation"
                                    :disabled="actionLoading"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-[#0f6b36] px-4 py-1.5 text-xs font-semibold text-white shadow-xs transition-colors hover:bg-[#0c592c]"
                                >
                                    <Check class="h-3.5 w-3.5" />
                                    <span>Setujui</span>
                                </button>
                            </template>

                            <!-- When Active/Berlangsung -->
                            <template v-else-if="active_client.status === 'berlangsung'">
                                <button
                                    @click="showMeetingModal = true"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 px-3.5 py-1.5 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-50 dark:border-[#333] dark:text-gray-300 dark:hover:bg-[#262626]"
                                >
                                    <Video class="h-3.5 w-3.5 text-gray-600 dark:text-gray-400" />
                                    <span>Meeting</span>
                                </button>
                                <button
                                    @click="showCompleteModal = true"
                                    class="rounded-full border border-gray-200 px-3.5 py-1.5 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-50 dark:border-[#333] dark:text-gray-300 dark:hover:bg-[#262626]"
                                >
                                    <span>Selesaikan</span>
                                </button>
                            </template>

                            <!-- Status Badge for Selesai / Ditolak -->
                            <template v-else>
                                <span
                                    v-if="active_client.status === 'selesai'"
                                    class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-400"
                                >
                                    Selesai
                                </span>
                                <span
                                    v-else-if="active_client.status === 'ditolak'"
                                    class="rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-800 dark:bg-red-950/50 dark:text-red-400"
                                >
                                    Ditolak
                                </span>
                            </template>
                        </div>
                    </div>

                    <!-- Status Banner if Menunggu / Ditolak / Selesai -->
                    <div
                        v-if="active_client.status === 'menunggu'"
                        class="flex items-center gap-3 border-b border-amber-200 bg-amber-50 px-6 py-2.5 text-xs text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300"
                    >
                        <AlertCircle class="h-4 w-4 shrink-0" />
                        <span>Permohonan ini butuh persetujuan Anda terlebih dahulu sebelum sesi obrolan dapat dimulai.</span>
                    </div>

                    <div
                        v-else-if="active_client.status === 'ditolak'"
                        class="flex items-center gap-3 border-b border-red-200 bg-red-50 px-6 py-2.5 text-xs text-red-800 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300"
                    >
                        <XCircle class="h-4 w-4 shrink-0" />
                        <span>Permohonan ditolak. Alasan: "{{ active_client.alasan_penolakan || 'Tidak ada alasan.' }}"</span>
                    </div>

                    <div
                        v-else-if="active_client.status === 'selesai'"
                        class="flex items-center gap-3 border-b border-gray-200 bg-gray-50 px-6 py-2 text-xs text-gray-600 dark:border-[#262626] dark:bg-[#121212] dark:text-gray-400"
                    >
                        <CheckCircle2 class="h-4 w-4 shrink-0 text-emerald-600" />
                        <span>Sesi konsultasi ini telah resmi diselesaikan. Percakapan ini dalam mode baca.</span>
                    </div>

                    <!-- Messages Bubble Stream -->
                    <div
                        ref="messageContainer"
                        class="flex-1 space-y-4 overflow-y-auto p-6"
                    >
                        <div
                            v-for="msg in messagesList"
                            :key="msg.id"
                            class="flex flex-col"
                            :class="msg.is_me ? 'items-end' : 'items-start'"
                        >
                            <span
                                v-if="!msg.is_me"
                                class="mb-1 pl-1 text-[11px] font-semibold text-gray-500 dark:text-gray-400"
                            >
                                {{ msg.sender_name }}
                            </span>

                            <!-- Meeting Card Message -->
                            <template v-if="msg.tipe_pesan === 'meeting'">
                                <MeetingCard :metadata="msg.metadata || {}" :is-me="msg.is_me" />
                            </template>

                            <!-- System Message -->
                            <template v-else-if="msg.tipe_pesan === 'sistem'">
                                <div class="my-2 max-w-sm rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2 text-center text-xs text-gray-500 dark:border-[#262626] dark:bg-[#121212] dark:text-gray-400">
                                    {{ msg.content }}
                                </div>
                            </template>

                            <!-- Standard Text Message -->
                            <template v-else>
                                <div
                                    class="max-w-md rounded-2xl p-4 text-xs leading-relaxed sm:text-sm"
                                    :class="
                                        msg.is_me
                                            ? 'rounded-tr-xs bg-[#0f6b36] text-white shadow-xs'
                                            : 'rounded-tl-xs border border-gray-200 bg-white text-gray-800 shadow-2xs dark:border-[#262626] dark:bg-[#222] dark:text-[#f0efea]'
                                    "
                                >
                                    {{ msg.content }}
                                </div>
                            </template>

                            <span class="mt-1 px-1 text-[10px] text-gray-400">
                                {{ msg.time }}
                            </span>
                        </div>
                    </div>

                    <!-- Bottom Input Bar -->
                    <div class="border-t border-gray-100 bg-white p-4 dark:border-[#262626] dark:bg-[#1a1a1a]">
                        <form
                            @submit.prevent="sendMessage"
                            class="flex items-center gap-2"
                        >
                            <div class="relative flex-1">
                                <input
                                    v-model="replyText"
                                    type="text"
                                    :disabled="active_client.status !== 'berlangsung'"
                                    :placeholder="
                                        active_client.status === 'berlangsung'
                                            ? `Balas ${active_client.nama.split(' ')[0]}...`
                                            : 'Percakapan ditutup untuk status ini'
                                    "
                                    class="w-full rounded-full border-none bg-[#f4f5f6] py-3 pr-4 pl-4 text-sm text-gray-800 placeholder-gray-400 transition-all outline-none hover:bg-[#eceef0] focus:bg-white focus:ring-2 focus:ring-[#0f6b36]/30 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-[#121212] dark:text-[#f0efea] dark:placeholder-gray-600 dark:focus:bg-[#161616]"
                                />
                            </div>

                            <button
                                type="submit"
                                :disabled="!replyText.trim() || active_client.status !== 'berlangsung'"
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#0f6b36] text-white shadow-xs transition-all hover:bg-[#0c592c] disabled:opacity-40"
                            >
                                <Send class="h-4 w-4" />
                            </button>
                        </form>
                    </div>
                </template>
            </div>

            <!-- ============================================== -->
            <!-- COLUMN 3: Konteks Klien Medical Panel (3 cols) -->
            <!-- ============================================== -->
            <div
                v-if="active_client"
                class="flex flex-col overflow-y-auto rounded-3xl border border-gray-100 bg-white p-5 shadow-xs dark:border-[#262626] dark:bg-[#1a1a1a] lg:col-span-3"
            >
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-bold text-gray-900 dark:text-[#f0efea]">
                        Konteks klien
                    </h2>
                    <span class="text-[10px] font-mono text-gray-400">
                        {{ active_client.nomor_konsultasi }}
                    </span>
                </div>

                <div class="space-y-3 border-b border-gray-100 pb-4 text-xs dark:border-[#262626]">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Untuk</span>
                        <span class="font-semibold text-gray-900 dark:text-[#f0efea]">{{
                            active_client.klien_info.untuk
                        }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Topik</span>
                        <span class="font-semibold text-gray-900 dark:text-[#f0efea]">{{
                            active_client.klien_info.topik
                        }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Diajukan</span>
                        <span class="font-semibold text-gray-900 dark:text-[#f0efea]">{{
                            active_client.klien_info.diajukan
                        }}</span>
                    </div>
                </div>

                <!-- Keluhan Box -->
                <div class="my-4 rounded-2xl border border-gray-100 bg-[#f9fafb] p-4 dark:border-[#262626] dark:bg-[#121212]">
                    <div class="mb-1.5 text-[11px] font-bold text-gray-700 dark:text-gray-300">
                        Keluhan
                    </div>
                    <p class="text-xs leading-relaxed text-gray-600 dark:text-gray-400">
                        {{ active_client.klien_info.keluhan }}
                    </p>
                </div>

                <!-- Data Dibagikan Klien -->
                <div class="mt-2 space-y-3">
                    <div class="mb-3 flex items-center gap-1.5 text-xs font-bold text-emerald-800 dark:text-emerald-400">
                        <ShieldCheck class="h-4 w-4 text-[#0f6b36] dark:text-emerald-400" />
                        <span>Data dibagikan klien</span>
                    </div>

                    <div class="flex items-center justify-between py-1 text-xs">
                        <span class="text-gray-500 dark:text-gray-400">BMI untuk usia</span>
                        <span class="font-bold text-gray-900 dark:text-[#f0efea]">{{
                            active_client.klien_info.data_dibagikan.bmi_usia || 'Normal'
                        }}</span>
                    </div>

                    <div class="flex items-center justify-between py-1 text-xs">
                        <span class="text-gray-500 dark:text-gray-400">Energi rata-rata</span>
                        <span class="font-bold text-gray-900 dark:text-[#f0efea]">{{
                            active_client.klien_info.data_dibagikan.energi_rata_rata || '1.420 kkal'
                        }}</span>
                    </div>

                    <div class="flex items-center justify-between py-1 text-xs">
                        <span class="text-gray-500 dark:text-gray-400">Camilan manis</span>
                        <span class="font-bold text-gray-900 dark:text-[#f0efea]">{{
                            active_client.klien_info.data_dibagikan.camilan_manis || '2 kali per hari'
                        }}</span>
                    </div>

                    <div class="flex items-center justify-between py-1 text-xs">
                        <span class="text-gray-500 dark:text-gray-400">Sayur tercatat</span>
                        <span class="font-bold text-gray-900 dark:text-[#f0efea]">{{
                            active_client.klien_info.data_dibagikan.sayur_tercatat || '3 dari 7 hari'
                        }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <ScheduleMeetingModal
            :show="showMeetingModal"
            :loading="actionLoading"
            @close="showMeetingModal = false"
            @submit="handleMeetingSubmit"
        />

        <RejectConsultationModal
            :show="showRejectModal"
            :loading="actionLoading"
            :client-name="active_client?.nama"
            @close="showRejectModal = false"
            @submit="handleRejectSubmit"
        />

        <CompleteConsultationModal
            :show="showCompleteModal"
            :loading="actionLoading"
            :client-name="active_client?.nama"
            @close="showCompleteModal = false"
            @confirm="handleCompleteConfirm"
        />
    </NutritionistLayout>
</template>
