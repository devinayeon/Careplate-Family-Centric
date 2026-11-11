<script setup lang="ts">
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import NutritionistLayout from '@/Layouts/NutritionistLayout.vue'
import {
  Video,
  Send,
  Image as ImageIcon,
  ShieldCheck,
  CheckCircle2,
} from 'lucide-vue-next'

const props = defineProps<{
  tabs: {
    baru: number
    aktif: number
    selesai: number
  }
  klien_list: Array<{
    id: number
    inisial: string
    nama: string
    subjudul: string
    waktu: string
    unread: boolean
    status: string
  }>
  active_client: {
    id: number
    inisial: string
    nama: string
    subjudul: string
    klien_info: {
      untuk: string
      topik: string
      diajukan: string
      keluhan: string
      data_dibagikan: {
        bmi_usia: string
        energi_rata_rata: string
        camilan_manis: string
        sayur_tercatat: string
      }
    }
    messages: Array<{
      id: number
      sender: 'client' | 'nutritionist'
      sender_name: string
      time: string
      content: string
    }>
  }
}>()

const activeTab = ref('aktif')
const selectedClientId = ref(props.active_client?.id ?? 1)
const replyText = ref('')
const messagesList = ref([...(props.active_client?.messages ?? [])])

const sendMessage = () => {
  if (!replyText.value.trim()) return

  const now = new Date()
  const hours = String(now.getHours()).padStart(2, '0')
  const minutes = String(now.getMinutes()).padStart(2, '0')

  messagesList.value.push({
    id: Date.now(),
    sender: 'nutritionist',
    sender_name: 'dr. Sari Wulandari',
    time: `${hours}.${minutes}`,
    content: replyText.value.trim(),
  })

  replyText.value = ''
}
</script>

<template>
  <Head title="Konsultasi Ahli Gizi" />

  <NutritionistLayout :full-width="true">
    <!-- 3-Column Workspace Matching Figma 12.1 -->
    <div class="h-[calc(100vh-6rem)] grid grid-cols-1 lg:grid-cols-12 gap-4 pb-2">
      <!-- ============================================== -->
      <!-- COLUMN 1: Daftar Konsultasi & Tabs (3.5 cols) -->
      <!-- ============================================== -->
      <div class="lg:col-span-3 bg-white rounded-3xl border border-gray-100 p-4 flex flex-col shadow-xs">
        <h2 class="text-lg font-bold text-gray-900 px-2 py-1">Konsultasi</h2>

        <!-- Tab Filters -->
        <div class="flex items-center gap-4 border-b border-gray-100 px-2 mt-2 mb-3 text-xs font-semibold">
          <button
            @click="activeTab = 'baru'"
            class="pb-2.5 transition-colors relative"
            :class="activeTab === 'baru' ? 'text-gray-900 font-bold border-b-2 border-gray-900' : 'text-gray-400 hover:text-gray-600'"
          >
            Baru ({{ tabs.baru }})
          </button>
          <button
            @click="activeTab = 'aktif'"
            class="pb-2.5 transition-colors relative"
            :class="activeTab === 'aktif' ? 'text-gray-900 font-bold border-b-2 border-gray-900' : 'text-gray-400 hover:text-gray-600'"
          >
            Aktif ({{ tabs.aktif }})
          </button>
          <button
            @click="activeTab = 'selesai'"
            class="pb-2.5 transition-colors relative"
            :class="activeTab === 'selesai' ? 'text-gray-900 font-bold border-b-2 border-gray-900' : 'text-gray-400 hover:text-gray-600'"
          >
            Selesai
          </button>
        </div>

        <!-- Client List Items -->
        <div class="space-y-1.5 overflow-y-auto flex-1 pr-1">
          <button
            v-for="klien in klien_list"
            :key="klien.id"
            @click="selectedClientId = klien.id"
            class="w-full flex items-center justify-between p-3 rounded-2xl text-left transition-all"
            :class="
              selectedClientId === klien.id
                ? 'bg-[#e7f5ec] text-gray-900 shadow-2xs'
                : 'hover:bg-gray-50 text-gray-700'
            "
          >
            <div class="flex items-center gap-3 min-w-0">
              <div
                class="w-10 h-10 rounded-xl font-bold text-xs flex items-center justify-center shrink-0"
                :class="
                  selectedClientId === klien.id
                    ? 'bg-emerald-200/80 text-[#0f6b36]'
                    : 'bg-gray-100 text-gray-600'
                "
              >
                {{ klien.inisial }}
              </div>

              <div class="min-w-0">
                <div class="text-xs font-bold truncate text-gray-900">{{ klien.nama }}</div>
                <div class="text-[11px] text-gray-400 truncate mt-0.5">{{ klien.subjudul }}</div>
              </div>
            </div>

            <div class="flex flex-col items-end gap-1.5 shrink-0 pl-2">
              <span class="text-[10px] text-gray-400 font-medium">{{ klien.waktu }}</span>
              <span v-if="klien.unread" class="w-2 h-2 rounded-full bg-orange-500"></span>
            </div>
          </button>
        </div>
      </div>

      <!-- ============================================== -->
      <!-- COLUMN 2: Chat Conversation Stream (5.5 cols) -->
      <!-- ============================================== -->
      <div class="lg:col-span-6 bg-white rounded-3xl border border-gray-100 flex flex-col shadow-xs overflow-hidden">
        <!-- Top Chat Header -->
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gray-100 text-gray-700 font-bold text-xs flex items-center justify-center shrink-0">
              {{ active_client.inisial }}
            </div>
            <div>
              <div class="text-sm font-bold text-gray-900 leading-tight">{{ active_client.nama }}</div>
              <div class="text-xs text-gray-400 leading-tight mt-0.5">{{ active_client.subjudul }}</div>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <button
              class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
            >
              <Video class="w-3.5 h-3.5 text-gray-600" />
              <span>Meeting</span>
            </button>
            <button
              class="px-3.5 py-1.5 rounded-full border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
            >
              <span>Selesaikan</span>
            </button>
          </div>
        </div>

        <!-- Messages Bubble Stream -->
        <div class="flex-1 p-6 overflow-y-auto space-y-4">
          <div
            v-for="msg in messagesList"
            :key="msg.id"
            class="flex flex-col"
            :class="msg.sender === 'nutritionist' ? 'items-end' : 'items-start'"
          >
            <!-- Sender name for client -->
            <span v-if="msg.sender === 'client'" class="text-[11px] font-semibold text-gray-500 mb-1 pl-1">
              {{ msg.sender_name }}
            </span>

            <div
              class="max-w-md rounded-2xl p-4 text-xs sm:text-sm leading-relaxed"
              :class="
                msg.sender === 'nutritionist'
                  ? 'bg-[#0f6b36] text-white rounded-tr-xs shadow-xs'
                  : 'bg-white border border-gray-200 text-gray-800 rounded-tl-xs shadow-2xs'
              "
            >
              {{ msg.content }}
            </div>

            <span class="text-[10px] text-gray-400 mt-1 px-1">
              {{ msg.time }}
            </span>
          </div>
        </div>

        <!-- Bottom Input & Attachment Bar -->
        <div class="p-4 border-t border-gray-100 bg-white">
          <form @submit.prevent="sendMessage" class="flex items-center gap-2">
            <div class="relative flex-1">
              <input
                v-model="replyText"
                type="text"
                :placeholder="`Balas ${active_client.nama.split(' ')[0]}`"
                class="w-full bg-[#f4f5f6] hover:bg-[#eceef0] focus:bg-white text-sm text-gray-800 placeholder-gray-400 pl-4 pr-10 py-3 rounded-full border-none focus:ring-2 focus:ring-[#0f6b36]/30 transition-all outline-none"
              />
              <button
                type="button"
                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
              >
                <ImageIcon class="w-4 h-4" />
              </button>
            </div>

            <button
              type="submit"
              :disabled="!replyText.trim()"
              class="w-11 h-11 rounded-full bg-[#0f6b36] hover:bg-[#0c592c] disabled:opacity-40 text-white flex items-center justify-center transition-all shrink-0 shadow-xs"
            >
              <Send class="w-4 h-4" />
            </button>
          </form>
        </div>
      </div>

      <!-- ============================================== -->
      <!-- COLUMN 3: Konteks Klien Medical Panel (3 cols) -->
      <!-- ============================================== -->
      <div class="lg:col-span-3 bg-white rounded-3xl border border-gray-100 p-5 flex flex-col shadow-xs overflow-y-auto">
        <h2 class="text-base font-bold text-gray-900 mb-4">Konteks klien</h2>

        <div class="space-y-3 text-xs border-b border-gray-100 pb-4">
          <div class="flex items-center justify-between">
            <span class="text-gray-400">Untuk</span>
            <span class="font-semibold text-gray-900">{{ active_client.klien_info.untuk }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-gray-400">Topik</span>
            <span class="font-semibold text-gray-900">{{ active_client.klien_info.topik }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-gray-400">Diajukan</span>
            <span class="font-semibold text-gray-900">{{ active_client.klien_info.diajukan }}</span>
          </div>
        </div>

        <!-- Keluhan Box -->
        <div class="bg-[#f9fafb] border border-gray-100 rounded-2xl p-4 my-4">
          <div class="text-[11px] font-bold text-gray-700 mb-1.5">Keluhan</div>
          <p class="text-xs text-gray-600 leading-relaxed">
            {{ active_client.klien_info.keluhan }}
          </p>
        </div>

        <!-- Data Dibagikan Klien -->
        <div class="mt-2 space-y-3">
          <div class="flex items-center gap-1.5 text-xs font-bold text-emerald-800 mb-3">
            <ShieldCheck class="w-4 h-4 text-[#0f6b36]" />
            <span>Data dibagikan klien</span>
          </div>

          <div class="flex items-center justify-between text-xs py-1">
            <span class="text-gray-500">BMI untuk usia</span>
            <span class="font-bold text-gray-900">{{ active_client.klien_info.data_dibagikan.bmi_usia }}</span>
          </div>

          <div class="flex items-center justify-between text-xs py-1">
            <span class="text-gray-500">Energi rata-rata 7 hari</span>
            <span class="font-bold text-gray-900">{{ active_client.klien_info.data_dibagikan.energi_rata_rata }}</span>
          </div>

          <div class="flex items-center justify-between text-xs py-1">
            <span class="text-gray-500">Camilan manis</span>
            <span class="font-bold text-gray-900">{{ active_client.klien_info.data_dibagikan.camilan_manis }}</span>
          </div>

          <div class="flex items-center justify-between text-xs py-1">
            <span class="text-gray-500">Sayur tercatat</span>
            <span class="font-bold text-gray-900">{{ active_client.klien_info.data_dibagikan.sayur_tercatat }}</span>
          </div>
        </div>
      </div>
    </div>
  </NutritionistLayout>
</template>
