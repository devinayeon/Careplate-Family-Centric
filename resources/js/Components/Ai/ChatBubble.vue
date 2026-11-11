<script setup lang="ts">
import { computed } from 'vue'
import { Database, Lightbulb, Loader2, RotateCcw, Stethoscope } from 'lucide-vue-next'
import type { ChatMessage } from '@/types/ai'

const props = defineProps<{ message: ChatMessage }>()
defineEmits<{ retry: [message: ChatMessage] }>()

const time = computed(() => new Date(props.message.at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace(':', '.'))

// What the person must know about where the answer came from (contract: show the notice and the source on every answer).
const source = computed(() => {
    const a = props.message.answer
    if (!a) return null
    if (a.mode === 'database') return { label: 'Dari Database CarePlate', icon: Database, tone: 'bg-[#e4f6ea] dark:bg-[#17321f] text-[#0b7a46] dark:text-[#3fcb7a]' }
    if (a.mode === 'general') return { label: 'Pengetahuan umum', icon: Lightbulb, tone: 'bg-[#e4f6ea] dark:bg-[#17321f] text-[#0b7a46] dark:text-[#3fcb7a]' }
    if (a.mode === 'referral') return { label: 'Sebaiknya tanya ahli gizi', icon: Stethoscope, tone: 'bg-[#fff4cc] dark:bg-amber-950/40 text-[#7a4b00] dark:text-amber-300' }

    return null
})
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex flex-col items-end gap-1">
            <p class="max-w-[85%] whitespace-pre-wrap break-words rounded-2xl bg-[#0b8043] px-4 py-3 text-base text-white">{{ message.question }}</p>
            <span class="text-xs text-[#5e5e5e] dark:text-[#a3a3a3]">{{ time }}</span>
        </div>

        <div class="flex flex-col items-start gap-2">
            <p v-if="message.state === 'sending'" class="flex items-center gap-2 rounded-2xl bg-[#f4f3f0] dark:bg-[#262626] px-4 py-3 text-sm text-[#5e5e5e] dark:text-[#a3a3a3]" role="status">
                <Loader2 class="h-4 w-4 animate-spin" aria-hidden="true" /> Sedang mencari jawabannya…
            </p>

            <template v-else-if="message.state === 'failed'">
                <p class="max-w-[85%] rounded-2xl bg-[#ffece5] dark:bg-[#3a1e14] px-4 py-3 text-sm text-[#a52f08] dark:text-red-300" role="alert">{{ message.error }}</p>
                <button v-if="message.retryable" type="button" class="inline-flex h-9 items-center gap-2 rounded-full border-2 border-[#191919] dark:border-[#f0efea] bg-white dark:bg-[#1c1c1c] px-4 text-sm font-semibold text-[#191919] dark:text-[#f0efea] hover:bg-gray-50 dark:hover:bg-[#262626] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919]" @click="$emit('retry', message)">
                    <RotateCcw class="h-3.5 w-3.5" aria-hidden="true" /> Kirim ulang
                </button>
            </template>

            <template v-else-if="message.answer">
                <p class="max-w-[85%] whitespace-pre-wrap break-words rounded-2xl bg-[#f4f3f0] dark:bg-[#262626] px-4 py-3 text-base text-[#191919] dark:text-[#f0efea]">{{ message.answer.answer }}</p>
                <span v-if="source" class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold" :class="source.tone"><component :is="source.icon" class="h-3.5 w-3.5" aria-hidden="true" />{{ source.label }}</span>
                <ul v-if="message.answer.citations.length" class="flex max-w-[85%] flex-col gap-1 text-xs text-[#5e5e5e] dark:text-[#a3a3a3]">
                    <li v-for="c in message.answer.citations" :key="c.id" :title="c.source_reference ?? undefined">
                        <span class="font-semibold">Sumber: {{ c.food_name }}</span>, per 100 g
                        <span v-if="c.source_reference" class="line-clamp-2 block">{{ c.source_reference }}</span>
                    </li>
                </ul>
                <p v-if="message.answer.general_knowledge_notice" class="max-w-[85%] text-xs text-[#5e5e5e] dark:text-[#a3a3a3]">{{ message.answer.general_knowledge_notice }}</p>
                <p v-for="w in message.answer.warnings" :key="w" class="max-w-[85%] text-xs text-[#7a4b00] dark:text-amber-300">{{ w }}</p>
            </template>
        </div>
    </div>
</template>
