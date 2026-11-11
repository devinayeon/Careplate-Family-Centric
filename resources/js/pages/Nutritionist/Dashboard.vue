<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import NutritionistLayout from '@/Layouts/NutritionistLayout.vue'
import {
  Users,
  MessageSquare,
  Clock,
  Star,
  ArrowRight,
  Video,
  CheckCircle2,
  Calendar,
} from 'lucide-vue-next'

defineProps<{
  stats: {
    klien_aktif: number
    konsultasi_hari_ini: number
    menunggu_respon: number
    rating_kepuasan: string
  }
  jadwal_hari_ini: Array<{
    id: number
    klien_nama: string
    topik: string
    jam: string
    status: string
  }>
  antrean_konsultasi: Array<{
    id: number
    nama: string
    subjudul: string
    pesan_terakhir: string
    waktu: string
    unread: boolean
  }>
}>()
</script>

<template>
  <Head title="Dashboard Ahli Gizi" />

  <NutritionistLayout>
    <div class="space-y-6 max-w-6xl">
      <!-- 1. Header & Direct CTA to Konsultasi Workspace -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight">Dashboard Ahli Gizi</h1>
          <p class="text-sm text-gray-500 mt-1">
            Pantau jadwal sesi, antrean klien, dan status konsultasi gizi keluarga.
          </p>
        </div>

        <Link
          :href="route('nutritionist.consultations')"
          class="inline-flex items-center gap-2 bg-[#0f6b36] hover:bg-[#0c592c] text-white px-5 py-2.5 rounded-full text-xs font-semibold transition-all shadow-xs self-start sm:self-center"
        >
          <MessageSquare class="w-4 h-4" />
          <span>Buka Ruang Konsultasi</span>
        </Link>
      </div>

      <!-- 2. Metric Overview Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-3xl border border-gray-100 shadow-xs">
          <div class="flex items-center gap-2 text-gray-500 text-xs font-medium mb-3">
            <Users class="w-4 h-4 text-gray-400" />
            <span>Klien Aktif</span>
          </div>
          <div class="text-3xl font-extrabold text-gray-900 tracking-tight">
            {{ stats.klien_aktif }}
          </div>
          <div class="text-[11px] text-emerald-600 mt-2 font-medium">
            Dalam pemantauan rutin
          </div>
        </div>

        <div class="bg-white p-5 rounded-3xl border border-gray-100 shadow-xs">
          <div class="flex items-center gap-2 text-gray-500 text-xs font-medium mb-3">
            <Calendar class="w-4 h-4 text-gray-400" />
            <span>Konsultasi Hari Ini</span>
          </div>
          <div class="text-3xl font-extrabold text-gray-900 tracking-tight">
            {{ stats.konsultasi_hari_ini }}
          </div>
          <div class="text-[11px] text-gray-400 mt-2 font-medium">
            3 chat • 2 video meeting
          </div>
        </div>

        <div class="bg-white p-5 rounded-3xl border border-gray-100 shadow-xs">
          <div class="flex items-center gap-2 text-gray-500 text-xs font-medium mb-3">
            <Clock class="w-4 h-4 text-amber-500" />
            <span>Menunggu Respon</span>
          </div>
          <div class="text-3xl font-extrabold text-amber-600 tracking-tight">
            {{ stats.menunggu_respon }}
          </div>
          <div class="text-[11px] text-amber-600 mt-2 font-medium">
            Perlu balasan &gt; 1 jam
          </div>
        </div>

        <div class="bg-white p-5 rounded-3xl border border-gray-100 shadow-xs">
          <div class="flex items-center gap-2 text-gray-500 text-xs font-medium mb-3">
            <Star class="w-4 h-4 text-yellow-400 fill-current" />
            <span>Rating Kepuasan</span>
          </div>
          <div class="text-3xl font-extrabold text-gray-900 tracking-tight">
            {{ stats.rating_kepuasan }}
          </div>
          <div class="text-[11px] text-gray-400 mt-2 font-medium">
            Dari 89 ulasan klien
          </div>
        </div>
      </div>

      <!-- 3. Bottom Grid: Antrean Konsultasi & Jadwal Hari Ini -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Antrean Konsultasi Masuk (7 cols) -->
        <div class="lg:col-span-7 bg-white rounded-3xl p-6 border border-gray-100 shadow-xs">
          <div class="flex items-center justify-between mb-5">
            <h2 class="text-base font-bold text-gray-900">Pesan Konsultasi Masuk</h2>
            <Link
              :href="route('nutritionist.consultations')"
              class="text-xs font-semibold text-[#0f6b36] hover:underline"
            >
              Lihat semua chat
            </Link>
          </div>

          <div class="divide-y divide-gray-100">
            <Link
              v-for="item in antrean_konsultasi"
              :key="item.id"
              :href="route('nutritionist.consultations')"
              class="py-3.5 flex items-start justify-between gap-4 hover:bg-gray-50/80 -mx-3 px-3 rounded-2xl transition-colors"
            >
              <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-full bg-emerald-100 text-[#0f6b36] font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">
                  {{ item.nama.slice(0, 2).toUpperCase() }}
                </div>
                <div>
                  <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-900">{{ item.nama }}</span>
                    <span v-if="item.unread" class="w-2 h-2 rounded-full bg-orange-500"></span>
                  </div>
                  <div class="text-xs text-gray-400">{{ item.subjudul }}</div>
                  <div class="text-xs text-gray-600 mt-1 line-clamp-1 max-w-md">
                    {{ item.pesan_terakhir }}
                  </div>
                </div>
              </div>

              <div class="text-[11px] text-gray-400 shrink-0 font-medium">
                {{ item.waktu }}
              </div>
            </Link>
          </div>
        </div>

        <!-- Jadwal Konsultasi Hari Ini (5 cols) -->
        <div class="lg:col-span-5 bg-white rounded-3xl p-6 border border-gray-100 shadow-xs">
          <div class="flex items-center justify-between mb-5">
            <h2 class="text-base font-bold text-gray-900">Jadwal Sesi Hari Ini</h2>
            <span class="text-xs font-medium text-gray-400">
              {{ jadwal_hari_ini.length }} sesi
            </span>
          </div>

          <div class="space-y-3">
            <div
              v-for="sesi in jadwal_hari_ini"
              :key="sesi.id"
              class="p-3.5 rounded-2xl border transition-all"
              :class="
                sesi.status === 'Berlangsung'
                  ? 'border-emerald-300 bg-emerald-50/40'
                  : 'border-gray-100 bg-gray-50/50'
              "
            >
              <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-gray-800">{{ sesi.jam }}</span>
                <span
                  class="px-2 py-0.5 rounded-full text-[11px] font-semibold"
                  :class="
                    sesi.status === 'Berlangsung'
                      ? 'bg-emerald-600 text-white'
                      : 'bg-gray-200 text-gray-700'
                  "
                >
                  {{ sesi.status }}
                </span>
              </div>
              <div class="text-sm font-semibold text-gray-900 mt-1.5">{{ sesi.klien_nama }}</div>
              <div class="text-xs text-gray-500 mt-0.5">{{ sesi.topik }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </NutritionistLayout>
</template>
