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
  XCircle,
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
    error_item: string | null
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
          <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-[#f0efea] tracking-tight">Dashboard</h1>
          <p class="text-sm text-gray-500 dark:text-[#a3a3a3] mt-1">
            Ringkasan data pengguna, katalog makanan, artikel edukasi, dan status verifikasi CarePlate.
          </p>
        </div>

        <button
          @click="refreshData"
          class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-[#1c1c1c] border border-gray-200 dark:border-[#2e2e2e] rounded-full text-xs font-semibold text-gray-700 dark:text-[#f0efea] hover:bg-gray-50 dark:hover:bg-[#262626] transition-all shadow-2xs self-start sm:self-center cursor-pointer"
        >
          <RefreshCw class="w-3.5 h-3.5 text-gray-500 dark:text-gray-400" />
          <span>Muat Ulang</span>
        </button>
      </div>

      <!-- 2. Four Metric Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card Total Pengguna -->
        <Link
          :href="route('admin.users')"
          class="bg-white dark:bg-[#1c1c1c] p-5 rounded-3xl border border-gray-100 dark:border-[#2e2e2e] shadow-xs hover:border-gray-200 dark:hover:border-[#3d3d3d] hover:shadow-sm transition-all block group cursor-pointer"
        >
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2 text-gray-500 dark:text-[#a3a3a3] text-xs font-medium">
              <Users class="w-4 h-4 text-gray-400 dark:text-gray-500 group-hover:text-emerald-600 dark:group-hover:text-[#3fcb7a] transition-colors" />
              <span>Total Pengguna</span>
            </div>
            <ArrowRight class="w-3.5 h-3.5 text-gray-300 dark:text-gray-600 group-hover:text-emerald-600 dark:group-hover:text-[#3fcb7a] transition-colors" />
          </div>
          <div class="text-3xl font-extrabold text-gray-900 dark:text-[#f0efea] tracking-tight">
            {{ metrics.total_pengguna.total }}
          </div>
          <div class="text-[11px] text-gray-400 dark:text-[#a3a3a3] mt-2 font-medium">
            {{ metrics.total_pengguna.detail }}
          </div>
        </Link>

        <!-- Card Total Artikel -->
        <Link
          :href="route('admin.articles')"
          class="bg-white dark:bg-[#1c1c1c] p-5 rounded-3xl border border-gray-100 dark:border-[#2e2e2e] shadow-xs hover:border-gray-200 dark:hover:border-[#3d3d3d] hover:shadow-sm transition-all block group cursor-pointer"
        >
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2 text-gray-500 dark:text-[#a3a3a3] text-xs font-medium">
              <BookOpen class="w-4 h-4 text-gray-400 dark:text-gray-500 group-hover:text-emerald-600 dark:group-hover:text-[#3fcb7a] transition-colors" />
              <span>Total Artikel</span>
            </div>
            <ArrowRight class="w-3.5 h-3.5 text-gray-300 dark:text-gray-600 group-hover:text-emerald-600 dark:group-hover:text-[#3fcb7a] transition-colors" />
          </div>
          <div class="text-3xl font-extrabold text-gray-900 dark:text-[#f0efea] tracking-tight">
            {{ metrics.total_artikel.total }}
          </div>
          <div class="text-[11px] text-gray-400 dark:text-[#a3a3a3] mt-2 font-medium">
            {{ metrics.total_artikel.detail }}
          </div>
        </Link>

        <!-- Card Total Database Makanan -->
        <Link
          :href="route('admin.foods')"
          class="bg-white dark:bg-[#1c1c1c] p-5 rounded-3xl border border-gray-100 dark:border-[#2e2e2e] shadow-xs hover:border-gray-200 dark:hover:border-[#3d3d3d] hover:shadow-sm transition-all block group cursor-pointer"
        >
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2 text-gray-500 dark:text-[#a3a3a3] text-xs font-medium">
              <UtensilsCrossed class="w-4 h-4 text-gray-400 dark:text-gray-500 group-hover:text-emerald-600 dark:group-hover:text-[#3fcb7a] transition-colors" />
              <span>Total Database Makanan</span>
            </div>
            <ArrowRight class="w-3.5 h-3.5 text-gray-300 dark:text-gray-600 group-hover:text-emerald-600 dark:group-hover:text-[#3fcb7a] transition-colors" />
          </div>
          <div class="text-3xl font-extrabold text-gray-900 dark:text-[#f0efea] tracking-tight">
            {{ metrics.total_makanan.total }}
          </div>
          <div class="text-[11px] text-emerald-600 dark:text-[#3fcb7a] mt-2 font-medium">
            {{ metrics.total_makanan.detail }}
          </div>
        </Link>

        <!-- Card Total Grup Keluarga -->
        <Link
          :href="route('admin.users')"
          class="bg-white dark:bg-[#1c1c1c] p-5 rounded-3xl border border-gray-100 dark:border-[#2e2e2e] shadow-xs hover:border-gray-200 dark:hover:border-[#3d3d3d] hover:shadow-sm transition-all block group cursor-pointer"
        >
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2 text-gray-500 dark:text-[#a3a3a3] text-xs font-medium">
              <Users2 class="w-4 h-4 text-gray-400 dark:text-gray-500 group-hover:text-emerald-600 dark:group-hover:text-[#3fcb7a] transition-colors" />
              <span>Total Grup Keluarga</span>
            </div>
            <ArrowRight class="w-3.5 h-3.5 text-gray-300 dark:text-gray-600 group-hover:text-emerald-600 dark:group-hover:text-[#3fcb7a] transition-colors" />
          </div>
          <div class="text-3xl font-extrabold text-gray-900 dark:text-[#f0efea] tracking-tight">
            {{ metrics.total_keluarga.total }}
          </div>
          <div class="text-[11px] text-gray-400 dark:text-[#a3a3a3] mt-2 font-medium">
            {{ metrics.total_keluarga.detail }}
          </div>
        </Link>
      </div>

      <!-- 3. Alert Action Banner: Pengajuan Makanan -->
      <div class="bg-[#fff1eb] dark:bg-[#3a1e14] border border-[#fed9c8] dark:border-[#542817] rounded-3xl p-5 sm:p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 transition-colors">
        <div class="flex items-center gap-4">
          <div class="w-11 h-11 rounded-2xl bg-white dark:bg-[#1c1c1c] border border-[#fed4c1] dark:border-[#542817] flex items-center justify-center shrink-0 text-[#0f6b36] dark:text-[#3fcb7a]">
            <CheckCircle2 class="w-5 h-5" />
          </div>
          <div>
            <div class="text-xs font-medium text-gray-500 dark:text-[#a3a3a3]">Pengajuan Makanan</div>
            <div class="text-base sm:text-lg font-bold text-gray-900 dark:text-[#f0efea]">
              <template v-if="antrean_verifikasi.jumlah > 0">
                <span class="text-[#0f6b36] dark:text-[#3fcb7a] font-extrabold">{{ antrean_verifikasi.jumlah }}</span> pengajuan menunggu verifikasi, terlama {{ antrean_verifikasi.terlama_hari }} hari
              </template>
              <template v-else>
                Tidak ada antrean pending, semua pengajuan makanan telah diverifikasi
              </template>
            </div>
          </div>
        </div>

        <Link
          :href="route('admin.verifications')"
          class="inline-flex items-center justify-center bg-[#0f6b36] hover:bg-[#0c592c] dark:bg-[#3fcb7a] dark:text-[#0e1a13] dark:hover:bg-[#34b86c] text-white px-5 py-2.5 rounded-full text-xs font-semibold transition-all shadow-xs shrink-0 self-stretch sm:self-auto text-center cursor-pointer"
        >
          <span>Buka Antrean Verifikasi</span>
        </Link>
      </div>

      <!-- 4. Bottom Grid: Pengajuan Terbaru & Sinkronisasi Chat Gizi -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Left: Pengajuan Terbaru (7 cols) -->
        <div class="lg:col-span-7 bg-white dark:bg-[#1c1c1c] rounded-3xl p-6 border border-gray-100 dark:border-[#2e2e2e] shadow-xs transition-colors">
          <div class="flex items-center justify-between mb-5">
            <h2 class="text-base font-bold text-gray-900 dark:text-[#f0efea]">Pengajuan terbaru</h2>
            <Link
              :href="route('admin.verifications')"
              class="text-xs font-semibold text-[#0f6b36] dark:text-[#3fcb7a] hover:underline"
            >
              Lihat semua
            </Link>
          </div>

          <div v-if="pengajuan_terbaru.length > 0" class="divide-y divide-gray-100 dark:divide-[#262626]">
            <div
              v-for="item in pengajuan_terbaru"
              :key="item.id"
              class="py-3.5 flex items-center justify-between gap-4"
            >
              <div>
                <div class="text-sm font-semibold text-gray-900 dark:text-[#f0efea]">{{ item.nama }}</div>
                <div class="text-xs text-gray-400 dark:text-[#a3a3a3] mt-0.5">
                  {{ item.pengaju }} • {{ item.tanggal }}
                </div>
              </div>

              <div>
                <span
                  v-if="item.status === 'Menunggu'"
                  class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border border-amber-200/60 dark:border-amber-800/50"
                >
                  <Clock class="w-3 h-3 text-amber-500" />
                  <span>Menunggu</span>
                </span>
                <span
                  v-else-if="item.status === 'Ditolak'"
                  class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-red-50 dark:bg-red-950/50 text-red-700 dark:text-red-300 border border-red-200/60 dark:border-red-800/50"
                >
                  <XCircle class="w-3 h-3 text-red-500" />
                  <span>Ditolak</span>
                </span>
                <span
                  v-else
                  class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 dark:bg-[#17321f] text-emerald-700 dark:text-[#3fcb7a] border border-emerald-200/60 dark:border-emerald-800/50"
                >
                  <CheckCircle2 class="w-3 h-3 text-emerald-500" />
                  <span>Terverifikasi</span>
                </span>
              </div>
            </div>
          </div>

          <div v-else class="py-12 text-center text-xs text-gray-400 dark:text-[#a3a3a3]">
            Belum ada riwayat pengajuan makanan.
          </div>
        </div>

        <!-- Right: Sinkronisasi Chat Gizi (5 cols) -->
        <div class="lg:col-span-5 bg-white dark:bg-[#1c1c1c] rounded-3xl p-6 border border-gray-100 dark:border-[#2e2e2e] shadow-xs flex flex-col justify-between transition-colors">
          <div>
            <h2 class="text-base font-bold text-gray-900 dark:text-[#f0efea] mb-4">Sinkronisasi Chat Gizi</h2>

            <!-- Status Indicator -->
            <div class="flex items-center gap-2 text-xs font-medium text-gray-700 dark:text-[#f0efea] mb-4">
              <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
              <span>Indeks terbaru, {{ sinkronisasi_ai.terakhir_sinkron }}</span>
            </div>

            <!-- Error alert box jika ada anomali item -->
            <div
              v-if="sinkronisasi_ai.error_item"
              class="bg-[#fef2f2] dark:bg-red-950/40 border border-[#fecaca] dark:border-red-900/60 rounded-2xl p-4 flex items-start justify-between gap-3"
            >
              <div class="flex items-start gap-2.5">
                <AlertCircle class="w-4 h-4 text-red-500 shrink-0 mt-0.5" />
                <div class="text-xs text-red-800 dark:text-red-300 leading-snug">
                  1 item perlu penyesuaian: <span class="font-semibold">{{ sinkronisasi_ai.error_item }}</span>.
                </div>
              </div>

              <button
                type="button"
                @click="refreshData"
                class="text-xs font-bold text-red-600 dark:text-red-400 hover:text-red-700 hover:underline shrink-0 cursor-pointer"
              >
                Coba lagi
              </button>
            </div>

            <!-- Healthy status box -->
            <div
              v-else
              class="bg-[#e4f6ea] dark:bg-[#17321f] border border-[#a2e3bc] dark:border-emerald-800/60 rounded-2xl p-4 flex items-center gap-3"
            >
              <CheckCircle2 class="w-5 h-5 text-[#0b8043] dark:text-[#3fcb7a] shrink-0" />
              <div class="text-xs text-[#0b8043] dark:text-[#3fcb7a] font-medium leading-snug">
                Seluruh katalog makanan terverifikasi tersinkronisasi optimal ke basis data AI Chat Gizi.
              </div>
            </div>
          </div>

          <div class="pt-4 border-t border-gray-50 dark:border-[#2e2e2e] mt-6">
            <p class="text-[11px] text-gray-400 dark:text-[#a3a3a3]">
              Database nutrisi disinkronkan secara otomatis ke basis pengetahuan AI untuk menjawab konsultasi cepat pengguna.
            </p>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
