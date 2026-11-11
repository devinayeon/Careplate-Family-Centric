<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  Users,
  BookOpen,
  UtensilsCrossed,
  Users2,
  RefreshCw,
  CheckCircle2,
  Clock,
  AlertCircle,
  ArrowRight,
} from 'lucide-vue-next'

defineProps<{
  metrics: {
    total_pengguna: { total: string; detail: string }
    total_artikel: { total: string; detail: string }
    total_makanan: { total: string; detail: string }
    total_keluarga: { total: string; detail: string }
  }
  antrean_verifikasi: {
    jumlah: number
    terlama_hari: number
  }
  pengajuan_terbaru: Array<{
    id: number
    nama: string
    pengaju: string
    tanggal: string
    status: string
  }>
  sinkronisasi_ai: {
    status: string
    terakhir_sinkron: string
    error_item: string
  }
}>()

const refreshData = () => {
  router.reload()
}
</script>

<template>
  <Head title="Admin Dashboard" />

  <AdminLayout>
    <div class="space-y-6 max-w-6xl">
      <!-- 1. Header Title & Reload Button -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight">Dashboard</h1>
          <p class="text-sm text-gray-500 mt-1">
            Ringkasan CarePlate hari ini. Angka di layar ini contoh data.
          </p>
        </div>

        <button
          @click="refreshData"
          class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-full text-xs font-semibold text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition-all shadow-2xs self-start sm:self-center"
        >
          <RefreshCw class="w-3.5 h-3.5 text-gray-500" />
          <span>Muat Ulang</span>
        </button>
      </div>

      <!-- 2. Four Metric Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card Total Pengguna -->
        <div class="bg-white p-5 rounded-3xl border border-gray-100 shadow-xs">
          <div class="flex items-center gap-2 text-gray-500 text-xs font-medium mb-3">
            <Users class="w-4 h-4 text-gray-400" />
            <span>Total Pengguna</span>
          </div>
          <div class="text-3xl font-extrabold text-gray-900 tracking-tight">
            {{ metrics.total_pengguna.total }}
          </div>
          <div class="text-[11px] text-gray-400 mt-2 font-medium">
            {{ metrics.total_pengguna.detail }}
          </div>
        </div>

        <!-- Card Total Artikel -->
        <div class="bg-white p-5 rounded-3xl border border-gray-100 shadow-xs">
          <div class="flex items-center gap-2 text-gray-500 text-xs font-medium mb-3">
            <BookOpen class="w-4 h-4 text-gray-400" />
            <span>Total Artikel</span>
          </div>
          <div class="text-3xl font-extrabold text-gray-900 tracking-tight">
            {{ metrics.total_artikel.total }}
          </div>
          <div class="text-[11px] text-gray-400 mt-2 font-medium">
            {{ metrics.total_artikel.detail }}
          </div>
        </div>

        <!-- Card Total Database Makanan -->
        <div class="bg-white p-5 rounded-3xl border border-gray-100 shadow-xs">
          <div class="flex items-center gap-2 text-gray-500 text-xs font-medium mb-3">
            <UtensilsCrossed class="w-4 h-4 text-gray-400" />
            <span>Total Database Makanan</span>
          </div>
          <div class="text-3xl font-extrabold text-gray-900 tracking-tight">
            {{ metrics.total_makanan.total }}
          </div>
          <div class="text-[11px] text-emerald-600 mt-2 font-medium">
            {{ metrics.total_makanan.detail }}
          </div>
        </div>

        <!-- Card Total Grup Keluarga -->
        <div class="bg-white p-5 rounded-3xl border border-gray-100 shadow-xs">
          <div class="flex items-center gap-2 text-gray-500 text-xs font-medium mb-3">
            <Users2 class="w-4 h-4 text-gray-400" />
            <span>Total Grup Keluarga</span>
          </div>
          <div class="text-3xl font-extrabold text-gray-900 tracking-tight">
            {{ metrics.total_keluarga.total }}
          </div>
          <div class="text-[11px] text-gray-400 mt-2 font-medium">
            {{ metrics.total_keluarga.detail }}
          </div>
        </div>
      </div>

      <!-- 3. Alert Action Banner: Pengajuan Makanan -->
      <div class="bg-[#fff1eb] border border-[#fed9c8] rounded-3xl p-5 sm:p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <div class="w-11 h-11 rounded-2xl bg-white border border-[#fed4c1] flex items-center justify-center shrink-0 text-[#0f6b36]">
            <CheckCircle2 class="w-5 h-5" />
          </div>
          <div>
            <div class="text-xs font-medium text-gray-500">Pengajuan Makanan</div>
            <div class="text-base sm:text-lg font-bold text-gray-900">
              <span class="text-[#0f6b36] font-extrabold">{{ antrean_verifikasi.jumlah }}</span> menunggu verifikasi, terlama {{ antrean_verifikasi.terlama_hari }} hari
            </div>
          </div>
        </div>

        <Link
          :href="route('admin.verifications')"
          class="inline-flex items-center justify-center bg-[#0f6b36] hover:bg-[#0c592c] text-white px-5 py-2.5 rounded-full text-xs font-semibold transition-all shadow-xs shrink-0 self-stretch sm:self-auto text-center"
        >
          <span>Buka Antrean Verifikasi</span>
        </Link>
      </div>

      <!-- 4. Bottom Grid: Pengajuan Terbaru & Sinkronisasi Chat Gizi -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Left: Pengajuan Terbaru (7 cols) -->
        <div class="lg:col-span-7 bg-white rounded-3xl p-6 border border-gray-100 shadow-xs">
          <div class="flex items-center justify-between mb-5">
            <h2 class="text-base font-bold text-gray-900">Pengajuan terbaru</h2>
            <Link
              :href="route('admin.verifications')"
              class="text-xs font-semibold text-[#0f6b36] hover:underline"
            >
              Lihat semua
            </Link>
          </div>

          <div class="divide-y divide-gray-100">
            <div
              v-for="item in pengajuan_terbaru"
              :key="item.id"
              class="py-3.5 flex items-center justify-between gap-4"
            >
              <div>
                <div class="text-sm font-semibold text-gray-900">{{ item.nama }}</div>
                <div class="text-xs text-gray-400 mt-0.5">
                  {{ item.pengaju }} • {{ item.tanggal }}
                </div>
              </div>

              <div>
                <span
                  v-if="item.status === 'Menunggu'"
                  class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200/60"
                >
                  <Clock class="w-3 h-3 text-amber-500" />
                  <span>Menunggu</span>
                </span>
                <span
                  v-else
                  class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200/60"
                >
                  <CheckCircle2 class="w-3 h-3 text-emerald-500" />
                  <span>Terverifikasi</span>
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Sinkronisasi Chat Gizi (5 cols) -->
        <div class="lg:col-span-5 bg-white rounded-3xl p-6 border border-gray-100 shadow-xs flex flex-col justify-between">
          <div>
            <h2 class="text-base font-bold text-gray-900 mb-4">Sinkronisasi Chat Gizi</h2>

            <!-- Status Indicator -->
            <div class="flex items-center gap-2 text-xs font-medium text-gray-700 mb-4">
              <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
              <span>Indeks terbaru, {{ sinkronisasi_ai.terakhir_sinkron }}</span>
            </div>

            <!-- Error alert box -->
            <div class="bg-[#fef2f2] border border-[#fecaca] rounded-2xl p-4 flex items-start justify-between gap-3">
              <div class="flex items-start gap-2.5">
                <AlertCircle class="w-4 h-4 text-red-500 shrink-0 mt-0.5" />
                <div class="text-xs text-red-800 leading-snug">
                  1 item gagal disinkronkan: <span class="font-semibold">{{ sinkronisasi_ai.error_item }}</span>.
                </div>
              </div>

              <button class="text-xs font-bold text-red-600 hover:text-red-700 hover:underline shrink-0">
                Coba lagi
              </button>
            </div>
          </div>

          <div class="pt-4 border-t border-gray-50 mt-6">
            <p class="text-[11px] text-gray-400">
              Database nutrisi disinkronkan secara otomatis ke basis pengetahuan AI untuk menjawab konsultasi cepat pengguna.
            </p>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
