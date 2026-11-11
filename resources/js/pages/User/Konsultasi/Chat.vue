<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import UserLayout from '@/Layouts/UserLayout.vue';
import MeetingCard from '@/Components/Consultation/MeetingCard.vue';
import {
    ArrowLeft,
    Send,
    Clock,
    CheckCircle2,
    XCircle,
    AlertCircle,
    ShieldCheck,
    Video,
    User,
} from 'lucide-vue-next';

interface Message {
    id: number;
    sender_id: number;
    is_me: boolean;
    sender_name: string;
    tipe_pesan: string;
    pesan: string;
    metadata?: any;
    time: string;
    date: string;
}

const props = defineProps<{
    konsultasi: {
        id: number;
        nomor_konsultasi: string;
        target_tipe: string;
        nama_pasien: string;
        usia_pasien: number;
        topik: string;
        keluhan: string;
        data_kesehatan?: any;
        status: 'menunggu' | 'berlangsung' | 'selesai' | 'ditolak';
        alasan_penolakan?: string;
        disetujui_pada?: string;
        selesai_pada?: string;
        created_at: string;
        ahli_gizi: {
            id: number;
            user_id: number;
            nama: string;
            spesialisasi: string;
            nomor_str: string;
            jam_layanan: string;
        };
        messages: Message[];
    };
}>();

const replyText = ref('');
const isSending = ref(false);
const messagesList = ref<Message[]>([...(props.konsultasi.messages || [])]);
const messageContainer = ref<HTMLDivElement | null>(null);
const currentStatus = ref(props.konsultasi.status);
const rejectionReason = ref(props.konsultasi.alasan_penolakan || '');

const scrollToBottom = () => {
    nextTick(() => {
        if (messageContainer.value) {
            messageContainer.value.scrollTop = messageContainer.value.scrollHeight;
        }
    });
};

const sendMessage = async () => {
    if (!replyText.value.trim() || currentStatus.value !== 'berlangsung' || isSending.value) return;

    const textToSend = replyText.value.trim();
    replyText.value = '';
    isSending.value = true;

    const now = new Date();
    const tempId = Date.now();

    // Optimistic UI update
    messagesList.value.push({
        id: tempId,
        sender_id: 0,
        is_me: true,
        sender_name: 'Saya',
        tipe_pesan: 'teks',
        pesan: textToSend,
        time: `${String(now.getHours()).padStart(2, '0')}.${String(now.getMinutes()).padStart(2, '0')}`,
        date: 'Hari ini',
    });
    scrollToBottom();

    const csrfToken =
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

    try {
        const response = await fetch(
            route('user.consultation.send_message', props.konsultasi.id),
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
                // Ganti pesan optimistik secara in-place dengan ID database riil
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

// Real-Time Polling Sync
let pollTimer: any = null;

const pollNewMessages = async () => {
    // Ambil ID riil terakhir (bukan ID temporer Date.now())
    const realMessages = messagesList.value.filter((m) => m.id < 1000000000);
    const lastRealMsg = realMessages[realMessages.length - 1];
    const afterId = lastRealMsg ? lastRealMsg.id : 0;

    try {
        const response = await fetch(
            route('user.consultation.stream', {
                konsultasi: props.konsultasi.id,
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
            if (data.status) {
                currentStatus.value = data.status;
            }
            if (data.alasan_penolakan) {
                rejectionReason.value = data.alasan_penolakan;
            }

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
                            m.pesan === newM.pesan
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
        // silent catch
    }
};

onMounted(() => {
    scrollToBottom();
    // Poll every 3 seconds for fast real-time chat sync
    pollTimer = setInterval(pollNewMessages, 3000);
});

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
});

const getInitials = (name: string) => {
    const parts = name.split(' ');
    let init = '';
    for (const p of parts) {
        if (p) init += p[0].toUpperCase();
        if (init.length >= 2) break;
    }
    return init || 'DR';
};
</script>

<template>
    <Head :title="`Konsultasi - ${konsultasi.ahli_gizi.nama}`" />

    <UserLayout :full-width="true">
        <div class="grid h-[calc(100vh-6rem)] grid-cols-1 gap-4 pb-2 lg:grid-cols-12">
            <!-- ============================================== -->
            <!-- MAIN CHAT STREAM (8 cols)                      -->
            <!-- ============================================== -->
            <div class="flex flex-col overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-xs dark:border-[#262626] dark:bg-[#1a1a1a] lg:col-span-8">
                <!-- Top Bar -->
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-[#262626]">
                    <div class="flex items-center gap-3">
                        <Link
                            :href="route('user.consultation', { tab: 'saya' })"
                            class="mr-1 rounded-full p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-[#262626] dark:hover:text-[#f0efea]"
                        >
                            <ArrowLeft class="h-5 w-5" />
                        </Link>
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-xs font-bold text-[#0f6b36] dark:bg-emerald-950 dark:text-emerald-300">
                            {{ getInitials(konsultasi.ahli_gizi.nama) }}
                        </div>
                        <div>
                            <div class="text-sm font-bold text-gray-900 dark:text-[#f0efea]">
                                {{ konsultasi.ahli_gizi.nama }}
                            </div>
                            <div class="text-xs text-emerald-700 dark:text-emerald-400 font-medium">
                                {{ konsultasi.ahli_gizi.spesialisasi }} • STR: {{ konsultasi.ahli_gizi.nomor_str }}
                            </div>
                        </div>
                    </div>

                    <!-- Status Pill -->
                    <div>
                        <span
                            v-if="currentStatus === 'menunggu'"
                            class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800 dark:bg-amber-950/60 dark:text-amber-400"
                        >
                            <Clock class="h-3.5 w-3.5" />
                            <span>Menunggu Konfirmasi</span>
                        </span>
                        <span
                            v-else-if="currentStatus === 'berlangsung'"
                            class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400"
                        >
                            <CheckCircle2 class="h-3.5 w-3.5" />
                            <span>Sesi Aktif</span>
                        </span>
                        <span
                            v-else-if="currentStatus === 'selesai'"
                            class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-xs font-bold text-gray-700 dark:bg-[#262626] dark:text-gray-300"
                        >
                            <span>Selesai</span>
                        </span>
                        <span
                            v-else-if="currentStatus === 'ditolak'"
                            class="inline-flex items-center gap-1 rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-800 dark:bg-red-950/60 dark:text-red-400"
                        >
                            <XCircle class="h-3.5 w-3.5" />
                            <span>Ditolak</span>
                        </span>
                    </div>
                </div>

                <!-- Status Banner Alerts -->
                <div
                    v-if="currentStatus === 'menunggu'"
                    class="flex items-center gap-3 border-b border-amber-200 bg-amber-50 px-6 py-3 text-xs text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300"
                >
                    <Clock class="h-4 w-4 shrink-0" />
                    <span>Permohonan konsultasi Anda sedang menunggu konfirmasi dari ahli gizi. Anda akan dapat mulai berdiskusi setelah dokter menyetujui.</span>
                </div>

                <div
                    v-else-if="currentStatus === 'ditolak'"
                    class="flex items-center gap-3 border-b border-red-200 bg-red-50 px-6 py-3 text-xs text-red-800 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300"
                >
                    <XCircle class="h-4 w-4 shrink-0" />
                    <span>Permohonan tidak disetujui oleh ahli gizi. Alasan: "{{ rejectionReason || 'Jadwal penuh atau topik di luar spesialisasi.' }}"</span>
                </div>

                <div
                    v-else-if="currentStatus === 'selesai'"
                    class="flex items-center gap-3 border-b border-gray-200 bg-gray-50 px-6 py-2.5 text-xs text-gray-600 dark:border-[#262626] dark:bg-[#121212] dark:text-gray-400"
                >
                    <CheckCircle2 class="h-4 w-4 shrink-0 text-emerald-600" />
                    <span>Sesi konsultasi ini telah resmi diselesaikan. Percakapan ini dalam mode arsip.</span>
                </div>

                <!-- Messages Stream Container -->
                <div
                    ref="messageContainer"
                    class="flex-1 space-y-4 overflow-y-auto p-6"
                >
                    <!-- Initial Submission Recap Bubble -->
                    <div class="mx-auto max-w-lg rounded-2xl border border-emerald-100 bg-[#f7fcf9] p-4 text-xs dark:border-emerald-900/40 dark:bg-emerald-950/20">
                        <div class="flex items-center justify-between font-bold text-emerald-800 dark:text-emerald-400 mb-1.5">
                            <span>Tiket Permohonan: {{ konsultasi.nomor_konsultasi }}</span>
                            <span class="text-[10px] text-gray-400">{{ konsultasi.created_at }}</span>
                        </div>
                        <p class="text-gray-600 dark:text-gray-300">
                            <strong>Keluhan:</strong> {{ konsultasi.keluhan }}
                        </p>
                    </div>

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
                                {{ msg.pesan }}
                            </div>
                        </template>

                        <!-- Standard Text Message -->
                        <template v-else>
                            <div
                                class="max-w-md rounded-2xl p-4 text-xs leading-relaxed sm:text-sm"
                                :class="
                                    msg.is_me
                                        ? 'rounded-tr-xs bg-[#0b8043] text-white shadow-xs'
                                        : 'rounded-tl-xs border border-gray-200 bg-white text-gray-800 shadow-2xs dark:border-[#262626] dark:bg-[#222] dark:text-[#f0efea]'
                                "
                            >
                                {{ msg.pesan }}
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
                                :disabled="currentStatus !== 'berlangsung'"
                                :placeholder="
                                    currentStatus === 'berlangsung'
                                        ? `Tulis pesan balasan untuk ${konsultasi.ahli_gizi.nama.split(' ')[0]}...`
                                        : currentStatus === 'menunggu'
                                            ? 'Menunggu persetujuan ahli gizi...'
                                            : 'Sesi konsultasi telah ditutup'
                                "
                                class="w-full rounded-full border-none bg-[#f4f5f6] py-3 pr-4 pl-4 text-sm text-gray-800 placeholder-gray-400 transition-all outline-none hover:bg-[#eceef0] focus:bg-white focus:ring-2 focus:ring-[#0b8043]/30 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-[#121212] dark:text-[#f0efea] dark:placeholder-gray-600 dark:focus:bg-[#161616]"
                            />
                        </div>

                        <button
                            type="submit"
                            :disabled="!replyText.trim() || currentStatus !== 'berlangsung'"
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#0b8043] text-white shadow-xs transition-all hover:bg-[#096a37] disabled:opacity-40"
                        >
                            <Send class="h-4 w-4" />
                        </button>
                    </form>
                </div>
            </div>

            <!-- ============================================== -->
            <!-- RIGHT DETAIL PANEL (4 cols)                    -->
            <!-- ============================================== -->
            <div class="flex flex-col overflow-y-auto rounded-3xl border border-gray-100 bg-white p-5 shadow-xs dark:border-[#262626] dark:bg-[#1a1a1a] lg:col-span-4">
                <h2 class="text-base font-bold text-gray-900 dark:text-[#f0efea] mb-4">
                    Rincian Konsultasi
                </h2>

                <div class="space-y-3 border-b border-gray-100 pb-4 text-xs dark:border-[#262626]">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Nomor Tiket</span>
                        <span class="font-mono font-semibold text-gray-900 dark:text-[#f0efea]">{{ konsultasi.nomor_konsultasi }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Untuk Pasien</span>
                        <span class="font-semibold text-gray-900 dark:text-[#f0efea]">{{ konsultasi.nama_pasien }}, {{ konsultasi.usia_pasien }} th</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Topik Gizi</span>
                        <span class="font-semibold text-gray-900 dark:text-[#f0efea]">{{ konsultasi.topik }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Diajukan Pada</span>
                        <span class="font-semibold text-gray-900 dark:text-[#f0efea]">{{ konsultasi.created_at }}</span>
                    </div>
                </div>

                <!-- Keluhan Detail Box -->
                <div class="my-4 rounded-2xl border border-gray-100 bg-[#f9fafb] p-4 dark:border-[#262626] dark:bg-[#121212]">
                    <div class="mb-1.5 text-[11px] font-bold text-gray-700 dark:text-gray-300">
                        Keluhan yang Diajukan
                    </div>
                    <p class="text-xs leading-relaxed text-gray-600 dark:text-gray-400">
                        {{ konsultasi.keluhan }}
                    </p>
                </div>

                <!-- Data Kesehatan Snapshot (if present) -->
                <div v-if="konsultasi.data_kesehatan" class="space-y-3">
                    <div class="flex items-center gap-1.5 text-xs font-bold text-emerald-800 dark:text-emerald-400">
                        <ShieldCheck class="h-4 w-4 text-[#0b8043] dark:text-emerald-400" />
                        <span>Data Kesehatan Terbagi</span>
                    </div>

                    <div class="flex items-center justify-between py-1 text-xs">
                        <span class="text-gray-500 dark:text-gray-400">BMI</span>
                        <span class="font-bold text-gray-900 dark:text-[#f0efea]">
                            {{ konsultasi.data_kesehatan.bmi_usia || 'Normal' }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between py-1 text-xs">
                        <span class="text-gray-500 dark:text-gray-400">Energi Rata-rata</span>
                        <span class="font-bold text-gray-900 dark:text-[#f0efea]">
                            {{ konsultasi.data_kesehatan.energi_rata_rata || '-' }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between py-1 text-xs">
                        <span class="text-gray-500 dark:text-gray-400">Camilan Manis</span>
                        <span class="font-bold text-gray-900 dark:text-[#f0efea]">
                            {{ konsultasi.data_kesehatan.camilan_manis || '-' }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between py-1 text-xs">
                        <span class="text-gray-500 dark:text-gray-400">Sayur Tercatat</span>
                        <span class="font-bold text-gray-900 dark:text-[#f0efea]">
                            {{ konsultasi.data_kesehatan.sayur_tercatat || '-' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
