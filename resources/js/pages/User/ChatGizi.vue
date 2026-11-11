<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Info, MessageCircle, Plus, Send, Trash2 } from 'lucide-vue-next';
import UserLayout from '@/Layouts/UserLayout.vue';
import ChatBubble from '@/Components/Ai/ChatBubble.vue';
import {
    AiApiError,
    createSession,
    deleteSession,
    listSessions,
    listTurns,
    sendTurn,
} from '@/lib/aiApi';
import type { ChatMessage, ChatSessionItem } from '@/types/ai';

defineProps<{ aiEnabled: boolean }>();

const MAX_CHARS = 2000;
const suggestions = [
    'Berapa protein 100 gram tempe?',
    'Apa fungsi protein untuk tubuh?',
    'Bandingkan kalori nasi dan kentang',
];

const sessions = ref<ChatSessionItem[]>([]);
const activeId = ref<string | null>(null);
const messages = ref<ChatMessage[]>([]);
const draft = ref('');
const loading = ref(true);
const loadError = ref('');
const sending = ref(false);
const scroller = ref<HTMLElement | null>(null);

const scrollToEnd = () =>
    nextTick(() =>
        scroller.value?.scrollTo({ top: scroller.value.scrollHeight }),
    );
const newKey = () => crypto.randomUUID();

function errorText(e: unknown): string {
    return e instanceof AiApiError
        ? e.helpText
        : 'Terjadi kesalahan. Coba lagi.';
}

async function refreshSessions() {
    sessions.value = (await listSessions()).data;
}

async function openSession(id: string) {
    activeId.value = id;
    messages.value = [];
    loadError.value = '';
    try {
        const turns = (await listTurns(id)).data;
        messages.value = [...turns]
            .sort((a, b) => a.sequence - b.sequence)
            .map((t) => ({
                key: t.id,
                clientTurnId: t.client_turn_id,
                question: t.user_content,
                state:
                    t.state === 'completed'
                        ? 'done'
                        : t.state === 'pending'
                          ? 'sending'
                          : 'failed',
                answer: t.response,
                error:
                    t.state === 'failed'
                        ? 'Pertanyaan ini gagal dijawab. Kirim ulang pertanyaannya.'
                        : null,
                retryable: t.state === 'failed',
                at: t.created_at,
            }));
        await scrollToEnd();
    } catch (e) {
        loadError.value = errorText(e);
    }
}

function newChat() {
    activeId.value = null;
    messages.value = [];
    loadError.value = '';
    draft.value = '';
}

async function ask(question: string, replace?: ChatMessage) {
    const text = question.trim();
    if (!text || text.length > MAX_CHARS || sending.value) return;
    sending.value = true;

    const entry: ChatMessage = replace ?? {
        key: newKey(),
        clientTurnId: newKey(),
        question: text,
        state: 'sending',
        answer: null,
        error: null,
        retryable: false,
        at: new Date().toISOString(),
    };
    if (replace) {
        // A deliberate retry is a new turn: reusing the id would only replay the stored failure.
        Object.assign(entry, {
            clientTurnId: newKey(),
            state: 'sending',
            error: null,
            retryable: false,
        });
    } else {
        messages.value.push(entry);
        draft.value = '';
    }
    const view = messages.value.find((m) => m.key === entry.key) ?? entry;
    await scrollToEnd();

    try {
        let id = activeId.value;
        if (!id) {
            const created = await createSession();
            id = created.id;
            activeId.value = id;
        }
        view.answer = await sendTurn(id, view.clientTurnId, text);
        view.state = 'done';
        void refreshSessions().catch(() => undefined);
    } catch (e) {
        view.state = 'failed';
        view.error = errorText(e);
        view.retryable =
            e instanceof AiApiError
                ? e.retryable || e.code === 'NETWORK'
                : true;
    } finally {
        sending.value = false;
        await scrollToEnd();
    }
}

async function remove(session: ChatSessionItem) {
    if (
        !window.confirm(
            'Hapus percakapan ini? Isinya langsung dihapus dan tidak bisa dikembalikan.',
        )
    )
        return;
    try {
        await deleteSession(session.id);
        if (activeId.value === session.id) newChat();
        await refreshSessions();
    } catch (e) {
        loadError.value = errorText(e);
    }
}

onMounted(async () => {
    try {
        await refreshSessions();
        if (sessions.value[0]) await openSession(sessions.value[0].id);
    } catch (e) {
        loadError.value = errorText(e);
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <Head title="Chat Gizi" />
    <UserLayout>
        <div
            class="grid min-h-[36rem] overflow-hidden rounded-3xl border border-[#eceae5] bg-white lg:h-[calc(100vh-9rem)] lg:grid-cols-[18rem_1fr] dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
        >
            <aside
                class="flex max-h-64 flex-col gap-3 overflow-y-auto bg-[#e4f6ea] p-4 lg:max-h-none dark:bg-[#17321f]"
                aria-label="Riwayat chat"
            >
                <button
                    type="button"
                    class="inline-flex h-11 items-center justify-center gap-2 rounded-full bg-[#0b8043] text-sm font-semibold text-white hover:bg-[#086637] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919]"
                    @click="newChat"
                >
                    <Plus class="h-4 w-4" aria-hidden="true" /> Chat Baru
                </button>
                <p class="px-1 text-xs text-[#5e5e5e] dark:text-[#a3a3a3]">
                    Riwayat chat · tersimpan 7 hari, hanya kamu yang bisa
                    melihat
                </p>
                <ul class="flex flex-col gap-1">
                    <li
                        v-for="s in sessions"
                        :key="s.id"
                        class="group flex items-center gap-1 rounded-xl"
                        :class="
                            s.id === activeId
                                ? 'bg-white dark:bg-[#1c1c1c]'
                                : 'hover:bg-white/60'
                        "
                    >
                        <button
                            type="button"
                            class="min-w-0 flex-1 px-3 py-2 text-left focus-visible:outline-2 focus-visible:outline-[#191919]"
                            :aria-current="
                                s.id === activeId ? 'true' : undefined
                            "
                            @click="openSession(s.id)"
                        >
                            <span
                                class="block truncate text-sm font-semibold text-[#191919] dark:text-[#f0efea]"
                                >{{ s.title || 'Percakapan baru' }}</span
                            >
                            <span
                                class="block text-xs text-[#5e5e5e] dark:text-[#a3a3a3]"
                                >{{
                                    new Date(s.updated_at).toLocaleDateString(
                                        'id-ID',
                                        { day: 'numeric', month: 'short' },
                                    )
                                }}</span
                            >
                        </button>
                        <button
                            type="button"
                            class="mr-1 rounded-full p-2.5 text-[#5e5e5e] hover:bg-[#ffece5] hover:text-[#a52f08] focus-visible:outline-2 focus-visible:outline-[#191919] dark:text-[#a3a3a3]"
                            :aria-label="`Hapus percakapan ${s.title || 'baru'}`"
                            @click="remove(s)"
                        >
                            <Trash2 class="h-4 w-4" aria-hidden="true" />
                        </button>
                    </li>
                </ul>
                <p
                    v-if="!loading && !sessions.length"
                    class="px-1 text-sm text-[#5e5e5e] dark:text-[#a3a3a3]"
                >
                    Belum ada percakapan.
                </p>
            </aside>

            <section class="flex min-h-0 flex-col" aria-labelledby="chat-title">
                <header
                    class="flex items-center gap-3 border-b border-[#eceae5] px-6 py-4 dark:border-[#2e2e2e]"
                >
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-[#f4f3f0] dark:bg-[#262626]"
                        ><MessageCircle class="h-5 w-5" aria-hidden="true"
                    /></span>
                    <div>
                        <h1
                            id="chat-title"
                            class="font-display text-lg font-bold text-[#191919] dark:text-[#f0efea]"
                        >
                            Chat Gizi
                        </h1>
                        <p class="text-xs text-[#5e5e5e] dark:text-[#a3a3a3]">
                            Asisten otomatis, bukan ahli gizi
                        </p>
                    </div>
                </header>

                <div
                    ref="scroller"
                    class="flex-1 overflow-y-auto px-6 py-5"
                    role="log"
                    aria-live="polite"
                    aria-label="Percakapan"
                >
                    <p
                        v-if="loading"
                        class="text-sm text-[#5e5e5e] dark:text-[#a3a3a3]"
                        role="status"
                    >
                        Memuat percakapan…
                    </p>
                    <p
                        v-else-if="loadError"
                        class="rounded-xl bg-[#ffece5] px-4 py-3 text-sm text-[#a52f08] dark:bg-[#3a1e14] dark:text-red-300"
                        role="alert"
                    >
                        {{ loadError }}
                    </p>

                    <div
                        v-else-if="!messages.length"
                        class="mx-auto flex max-w-md flex-col items-center gap-4 py-10 text-center"
                    >
                        <h2
                            class="font-display text-xl font-bold text-[#191919] dark:text-[#f0efea]"
                        >
                            Tanya apa saja soal gizi makanan
                        </h2>
                        <p class="text-sm text-[#5e5e5e] dark:text-[#a3a3a3]">
                            Angka gizi hanya diambil dari Database CarePlate
                            yang sudah diperiksa admin. Kalau tidak ada di sana,
                            jawabannya ditandai sebagai pengetahuan umum.
                        </p>
                        <div class="flex flex-wrap justify-center gap-2">
                            <button
                                v-for="s in suggestions"
                                :key="s"
                                type="button"
                                :disabled="!aiEnabled"
                                class="rounded-full border border-[#d6d3cc] px-4 py-2 text-sm text-[#191919] hover:bg-[#f4f3f0] focus-visible:outline-2 focus-visible:outline-[#191919] disabled:opacity-50 dark:border-[#3d3d3d] dark:text-[#f0efea] dark:hover:bg-[#262626]"
                                @click="ask(s)"
                            >
                                {{ s }}
                            </button>
                        </div>
                    </div>

                    <div v-else class="flex flex-col gap-6">
                        <ChatBubble
                            v-for="m in messages"
                            :key="m.key"
                            :message="m"
                            @retry="ask(m.question, $event)"
                        />
                    </div>
                </div>

                <form
                    class="border-t border-[#eceae5] px-6 py-4 dark:border-[#2e2e2e]"
                    @submit.prevent="ask(draft)"
                >
                    <p
                        v-if="!aiEnabled"
                        class="mb-3 rounded-xl bg-[#fff4cc] px-4 py-2 text-sm text-[#7a4b00] dark:bg-amber-950/40 dark:text-amber-300"
                        role="status"
                    >
                        Chat Gizi belum diaktifkan di server ini.
                    </p>
                    <div
                        class="flex items-end gap-3 rounded-3xl bg-[#f4f3f0] py-2 pr-2 pl-5 dark:bg-[#262626]"
                    >
                        <label for="chat-input" class="sr-only"
                            >Pertanyaan tentang gizi makanan</label
                        >
                        <textarea
                            id="chat-input"
                            v-model="draft"
                            rows="1"
                            :maxlength="MAX_CHARS"
                            :disabled="!aiEnabled"
                            placeholder="Tanya soal gizi makanan"
                            class="max-h-32 min-h-10 flex-1 resize-none border-0 bg-transparent py-2 text-base text-[#191919] placeholder:text-[#5e5e5e] focus:ring-0 focus-visible:outline-none dark:text-[#f0efea]"
                            @keydown.enter.exact.prevent="ask(draft)"
                        />
                        <button
                            type="submit"
                            :disabled="!aiEnabled || sending || !draft.trim()"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#0b8043] text-white hover:bg-[#086637] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919] disabled:cursor-not-allowed disabled:opacity-50"
                            aria-label="Kirim pertanyaan"
                        >
                            <Send class="h-4 w-4" aria-hidden="true" />
                        </button>
                    </div>
                    <p
                        class="mt-2 flex items-start gap-2 text-xs text-[#5e5e5e] dark:text-[#a3a3a3]"
                    >
                        <Info
                            class="mt-0.5 h-3.5 w-3.5 shrink-0"
                            aria-hidden="true"
                        />Jawaban otomatis bisa keliru. Untuk kondisi medis,
                        ajukan Konsultasi dengan Ahli Gizi. Pertanyaanmu
                        diproses penyedia AI.
                    </p>
                </form>
            </section>
        </div>
    </UserLayout>
</template>
