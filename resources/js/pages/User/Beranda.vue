<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import UserLayout from '@/Layouts/UserLayout.vue'
import {
  Plus,
  Calculator,
  ScanLine,
  Calendar,
  Utensils,
  Award,
  CheckCircle2,
  Circle,
  Users2,
  ArrowRight,
} from 'lucide-vue-next'

const props = defineProps<{
  pengguna?: any
  targetKalori: number
  kaloriTerkonsumsi: number
  streakHari: number
  keluarga?: any
  misi: {
    list: Array<{
      id: number
      title: string
      action_label: string
      route: string
      completed: boolean
    }>
    completed_count: number
    total_count: number
  }
  lencana: Array<{
    id: string
    title: string
    description: string
    achieved: boolean
  }>
}>()

const page = usePage()
const user = computed(() => (page.props.auth as any)?.user)

const firstName = computed(() => {
  if (!user.value?.name) return 'Sobat'
  return user.value.name.split(' ')[0]
})

// Current formatted date in Indonesian
const todayFormatted = computed(() => {
  const options: Intl.DateTimeFormatOptions = {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  }
  return new Intl.DateTimeFormat('id-ID', options).format(new Date())
})

const hasCalorieTarget = computed(() => {
  return props.targetKalori > 0
})

const progressPercentage = computed(() => {
  if (!props.targetKalori || props.targetKalori <= 0) return 0
  return Math.min(100, Math.round((props.kaloriTerkonsumsi / props.targetKalori) * 100))
})
</script>

<template>
  <Head title="Beranda" />

  <UserLayout>
    <div class="space-y-6 max-w-5xl">
      <!-- 1. Hero Welcome Banner (Figma Node 24-5629) -->
      <div class="relative overflow-hidden bg-[#fff2ea] dark:bg-[#3a1e14] rounded-3xl p-6 sm:p-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6 border border-[#fed7c0]/40 dark:border-[#542817] transition-colors">
        <div class="z-10 max-w-lg">
          <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-[#f0efea] tracking-tight">
            Halo, {{ firstName }}
          </h1>
          <p class="text-gray-600 dark:text-[#a3a3a3] mt-1 text-sm sm:text-base">
            {{ todayFormatted }}. Yuk mulai dari catatan pertama.
          </p>

          <div class="mt-5">
            <Link
              :href="route('user.meals')"
              class="inline-flex items-center gap-2 bg-[#0f6b36] hover:bg-[#0c592c] dark:bg-[#3fcb7a] dark:text-[#0e1a13] dark:hover:bg-[#34b86c] text-white px-5 py-2.5 rounded-full font-medium text-sm transition-all shadow-sm hover:shadow-md active:scale-95"
            >
              <Plus class="w-4 h-4" />
              <span>Catat Makanan</span>
            </Link>
          </div>
        </div>

        <!-- Illustration Bowl / Mascot -->
        <div class="shrink-0 flex items-center justify-center self-end sm:self-center pr-2">
          <div class="w-28 h-28 sm:w-36 sm:h-36 relative flex items-center justify-center">
            <!-- Decorative Bowl Vector -->
            <div class="w-24 h-16 bg-white/90 dark:bg-[#262626] border-2 border-sky-200 dark:border-[#3d3d3d] rounded-b-full shadow-sm flex items-center justify-center relative">
              <div class="absolute -top-3 w-20 h-4 bg-emerald-400/80 dark:bg-[#3fcb7a]/80 rounded-full blur-[1px]"></div>
              <Utensils class="w-8 h-8 text-[#0f6b36] dark:text-[#3fcb7a] opacity-80" />
            </div>
          </div>
        </div>
      </div>

      <!-- 2. Main Grid: Left (Piring & Misi), Right (Keluarga & Lencana) -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Left Section (8 cols) -->
        <div class="lg:col-span-7 space-y-6">
          <!-- Card Piring Hari Ini -->
          <div class="bg-white dark:bg-[#1c1c1c] rounded-3xl p-6 border border-gray-100 dark:border-[#2e2e2e] shadow-xs transition-colors">
            <h2 class="text-base font-bold text-gray-900 dark:text-[#f0efea] mb-5">Piring hari ini</h2>

            <div class="flex flex-col sm:flex-row items-center gap-6 sm:gap-8">
              <!-- Radial Ring Counter -->
              <div class="relative w-36 h-36 flex items-center justify-center shrink-0">
                <svg class="w-full h-full -rotate-90" viewBox="0 0 100 100">
                  <!-- Background Circle -->
                  <circle
                    cx="50"
                    cy="50"
                    r="42"
                    fill="transparent"
                    stroke="#f0f2f4"
                    class="dark:stroke-[#262626]"
                    stroke-width="8"
                  />
                  <!-- Progress Circle -->
                  <circle
                    v-if="hasCalorieTarget"
                    cx="50"
                    cy="50"
                    r="42"
                    fill="transparent"
                    stroke="#0f6b36"
                    stroke-width="8"
                    stroke-linecap="round"
                    :stroke-dasharray="264"
                    :stroke-dashoffset="264 - (264 * progressPercentage) / 100"
                    class="dark:stroke-[#3fcb7a] transition-all duration-700 ease-out"
                  />
                </svg>

                <div class="absolute inset-0 flex flex-col items-center justify-center text-center px-2">
                  <span class="text-3xl font-extrabold text-gray-900 dark:text-[#f0efea] tracking-tight">
                    {{ hasCalorieTarget ? Math.round(kaloriTerkonsumsi) : '0' }}
                  </span>
                  <span class="text-[11px] font-medium text-gray-400 dark:text-[#a3a3a3] leading-tight">
                    {{ hasCalorieTarget ? `dari ${Math.round(targetKalori)} kkal` : 'belum ada target' }}
                  </span>
                </div>
              </div>

              <!-- Explanation & CTA -->
              <div class="flex-1 text-center sm:text-left">
                <h3 class="text-base font-semibold text-gray-900 dark:text-[#f0efea]">
                  {{ hasCalorieTarget ? 'Kebutuhan Kalori Terpasang' : 'Piringmu belum punya target' }}
                </h3>
                <p class="text-xs text-gray-500 dark:text-[#a3a3a3] mt-1 leading-relaxed">
                  {{
                    hasCalorieTarget
                      ? 'Lanjutkan mencatat porsi makan harianmu untuk menjaga keseimbangan gizi keluarga.'
                      : 'Hitung kebutuhan kalori dulu, lalu catatan makan mengisi piring.'
                  }}
                </p>

                <div class="mt-4">
                  <Link
                    :href="route('user.calculator')"
                    class="inline-flex items-center gap-2 bg-[#0f6b36] hover:bg-[#0c592c] dark:bg-[#3fcb7a] dark:text-[#0e1a13] dark:hover:bg-[#34b86c] text-white px-4 py-2 rounded-xl text-xs font-semibold transition-all shadow-xs"
                  >
                    <Calculator class="w-3.5 h-3.5" />
                    <span>Hitung Kebutuhan Kalori</span>
                  </Link>
                </div>
              </div>
            </div>
          </div>

          <!-- Card Misi Hari Ini -->
          <div class="bg-white dark:bg-[#1c1c1c] rounded-3xl p-6 border border-gray-100 dark:border-[#2e2e2e] shadow-xs transition-colors">
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-base font-bold text-gray-900 dark:text-[#f0efea]">Misi hari ini</h2>
              <span class="text-xs font-medium text-gray-400 dark:text-[#a3a3a3]">
                {{ misi.completed_count }} dari {{ misi.total_count }}
              </span>
            </div>

            <div class="divide-y divide-gray-50 dark:divide-[#262626]">
              <div
                v-for="item in misi.list"
                :key="item.id"
                class="py-3 flex items-center justify-between gap-4"
              >
                <div class="flex items-center gap-3">
                  <CheckCircle2
                    v-if="item.completed"
                    class="w-5 h-5 text-[#0f6b36] dark:text-[#3fcb7a] shrink-0"
                  />
                  <Circle
                    v-else
                    class="w-5 h-5 text-gray-300 dark:text-gray-600 shrink-0"
                  />
                  <span
                    class="text-sm"
                    :class="item.completed ? 'text-gray-400 dark:text-gray-500 line-through' : 'text-gray-700 dark:text-[#f0efea] font-medium'"
                  >
                    {{ item.title }}
                  </span>
                </div>

                <Link
                  :href="route().has(item.route) ? route(item.route) : '#'"
                  class="text-xs font-semibold text-[#0f6b36] dark:text-[#3fcb7a] hover:underline shrink-0"
                >
                  {{ item.action_label }}
                </Link>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Section (5 cols) -->
        <div class="lg:col-span-5 space-y-6">
          <!-- Card Grup Keluarga -->
          <div class="bg-white dark:bg-[#1c1c1c] rounded-3xl p-6 border border-gray-100 dark:border-[#2e2e2e] shadow-xs text-center sm:text-left transition-colors">
            <div class="w-12 h-12 rounded-full bg-orange-50 dark:bg-[#3a1e14] flex items-center justify-center mb-4 mx-auto sm:mx-0">
              <img src="/assets/piri-mascot.png" alt="CarePlate Mascot" class="w-8 h-8 object-contain" />
            </div>

            <div v-if="!keluarga">
              <h3 class="text-base font-bold text-gray-900 dark:text-[#f0efea]">Belum ada grup keluarga</h3>
              <p class="text-xs text-gray-500 dark:text-[#a3a3a3] mt-1 leading-relaxed">
                Buat grup atau gabung dengan kode keluargamu.
              </p>

              <div class="mt-5">
                <Link
                  :href="route('user.family')"
                  class="w-full inline-flex items-center justify-center gap-2 bg-[#0f6b36] hover:bg-[#0c592c] dark:bg-[#3fcb7a] dark:text-[#0e1a13] dark:hover:bg-[#34b86c] text-white px-4 py-2.5 rounded-xl text-xs font-semibold transition-all shadow-xs"
                >
                  <Users2 class="w-4 h-4" />
                  <span>Mulai Grup Keluarga</span>
                </Link>
              </div>
            </div>

            <div v-else>
              <h3 class="text-base font-bold text-gray-900 dark:text-[#f0efea]">{{ keluarga.nama_keluarga }}</h3>
              <p class="text-xs text-gray-500 dark:text-[#a3a3a3] mt-1">
                Kode grup: <span class="font-mono font-bold text-emerald-800 dark:text-emerald-300">{{ keluarga.kode_keluarga }}</span>
              </p>

              <div class="mt-5">
                <Link
                  :href="route('user.family')"
                  class="w-full inline-flex items-center justify-center gap-2 bg-emerald-50 dark:bg-[#17321f] hover:bg-emerald-100 dark:hover:bg-[#20442b] text-[#0f6b36] dark:text-[#3fcb7a] px-4 py-2.5 rounded-xl text-xs font-semibold transition-all"
                >
                  <span>Lihat Anggota Keluarga</span>
                  <ArrowRight class="w-3.5 h-3.5" />
                </Link>
              </div>
            </div>
          </div>

          <!-- Card Lencana Pertama Menunggu -->
          <div class="bg-white dark:bg-[#1c1c1c] rounded-3xl p-6 border border-gray-100 dark:border-[#2e2e2e] shadow-xs transition-colors">
            <h3 class="text-base font-bold text-gray-900 dark:text-[#f0efea] mb-4">Lencana pertama menunggu</h3>

            <div class="grid grid-cols-2 gap-4">
              <div
                v-for="badge in lencana"
                :key="badge.id"
                class="flex flex-col items-center text-center p-3 rounded-2xl border border-dashed border-gray-200 dark:border-[#3d3d3d] bg-gray-50/50 dark:bg-[#262626]/50"
              >
                <div class="w-14 h-14 rounded-full bg-gray-200/80 dark:bg-[#3d3d3d] flex items-center justify-center mb-2 text-gray-500 dark:text-gray-300">
                  <Award class="w-6 h-6" />
                </div>
                <div class="text-xs font-bold text-gray-800 dark:text-[#f0efea]">{{ badge.title }}</div>
                <div class="text-[11px] text-gray-400 dark:text-[#a3a3a3] mt-0.5">{{ badge.description }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </UserLayout>
</template>
