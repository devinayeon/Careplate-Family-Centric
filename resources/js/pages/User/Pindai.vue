<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { Camera, Database, Image as ImageIcon, Lightbulb, Loader2, MessageCircle, ScanLine, ShieldCheck, Upload } from 'lucide-vue-next'
import UserLayout from '@/Layouts/UserLayout.vue'
import ScanResultView from '@/Components/Ai/ScanResultView.vue'
import { AiApiError, scanLabel } from '@/lib/aiApi'
import type { ScanResult } from '@/types/ai'

const props = defineProps<{ aiEnabled: boolean; scanLimitPerDay: number }>()

const MAX_BYTES = 5 * 1024 * 1024
const ACCEPTED = ['image/jpeg', 'image/png']

type Phase = 'idle' | 'reading' | 'done' | 'error'
const phase = ref<Phase>('idle')
const result = ref<ScanResult | null>(null)
const errorText = ref('')
const previewUrl = ref<string | null>(null)
const dragging = ref(false)
const uploadInput = ref<HTMLInputElement | null>(null)
const cameraInput = ref<HTMLInputElement | null>(null)

const busy = computed(() => phase.value === 'reading')
const canSubmitFood = computed(() => ((usePage().props.auth as { user?: { permissions?: string[] } })?.user?.permissions ?? []).includes('food:create'))

// "Ajukan ke Data Makanan": hand the per-serving numbers to the submission wizard through this tab only (Figma 07.12).
function submitToFoodData() {
    const r = result.value
    if (!r) return
    const n = r.normalized_per_serving
    const size = r.extraction.serving_size
    const unit = size?.unit?.toLowerCase()
    const draft = {
        ...(size?.value && (unit === 'g' || unit === 'ml') ? { kuantitas: size.value, satuan: unit } : {}),
        energi: n.energy_kcal, protein: n.protein_g, karbohidrat: n.carbohydrate_g, lemak: n.fat_g, gula: n.sugars_total_g, natrium: n.sodium_mg,
    }
    try { sessionStorage.setItem('careplate.scanDraft', JSON.stringify(draft)) } catch { /* the wizard then starts empty */ }
    router.visit(route('user.foods.create', { dari: 'pindai' }))
}

function clearPreview() {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = null
}
onBeforeUnmount(clearPreview)

function fail(message: string) {
    phase.value = 'error'
    errorText.value = message
}

async function readFile(file: File | undefined) {
    if (!file || busy.value) return
    if (!ACCEPTED.includes(file.type)) return fail('Foto harus berformat JPEG atau PNG.')
    if (file.size > MAX_BYTES) return fail('Foto terlalu besar. Pakai foto di bawah 5 MB.')

    clearPreview()
    previewUrl.value = URL.createObjectURL(file)
    phase.value = 'reading'
    errorText.value = ''
    try {
        result.value = await scanLabel(file)
        phase.value = 'done'
    } catch (e) {
        fail(e instanceof AiApiError ? e.helpText : 'Foto tidak bisa dibaca. Coba lagi.')
    } finally {
        // The photo is never stored: drop the browser copy as soon as the answer (or error) is in.
        clearPreview()
    }
}

function onPick(event: Event) {
    const input = event.target as HTMLInputElement
    void readFile(input.files?.[0])
    input.value = ''
}

function onDrop(event: DragEvent) {
    dragging.value = false
    void readFile(event.dataTransfer?.files?.[0])
}

function reset() {
    phase.value = 'idle'
    result.value = null
    errorText.value = ''
}
</script>

<template>
    <Head title="Pindai Kemasan" />
    <UserLayout>
        <div class="flex flex-col gap-6">
            <header class="flex items-center justify-between gap-6 rounded-3xl bg-[#ffece5] dark:bg-[#3a1e14] px-7 py-6">
                <div>
                    <h1 class="font-display text-3xl font-extrabold text-[#191919] dark:text-[#f0efea]">{{ phase === 'done' ? 'Hasil pindai' : 'Pindai Kemasan' }}</h1>
                    <p class="mt-1 text-base text-[#5e5e5e] dark:text-[#a3a3a3]">Foto label gizi, lihat gula, lemak, dan natriumnya.</p>
                </div>
                <div v-if="phase === 'done'" class="flex shrink-0 flex-wrap justify-end gap-2">
                    <button v-if="canSubmitFood" type="button" class="inline-flex h-11 items-center gap-2 rounded-full bg-[#0b8043] px-5 text-sm font-semibold text-white hover:bg-[#086637] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919]" @click="submitToFoodData">
                        <Database class="h-4 w-4" aria-hidden="true" /> Ajukan ke Data Makanan
                    </button>
                    <button type="button" class="inline-flex h-11 items-center gap-2 rounded-full border-2 border-[#191919] dark:border-[#f0efea] bg-white dark:bg-[#1c1c1c] px-5 text-sm font-semibold text-[#191919] dark:text-[#f0efea] hover:bg-gray-50 dark:hover:bg-[#262626] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919]" @click="reset">
                        <ScanLine class="h-4 w-4" aria-hidden="true" /> Pindai Lagi
                    </button>
                </div>
                <ScanLine v-else class="hidden h-16 w-16 shrink-0 text-[#191919] dark:text-[#f0efea] sm:block" aria-hidden="true" />
            </header>

            <p v-if="!aiEnabled" class="rounded-2xl bg-[#fff4cc] dark:bg-amber-950/40 px-5 py-4 text-sm text-[#7a4b00] dark:text-amber-300" role="status">
                Fitur pindai belum diaktifkan di server ini. Kamu tetap bisa mencari makanan di Data Makanan.
            </p>

            <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
                <div>
                    <ScanResultView v-if="phase === 'done' && result" :result="result" />

                    <section
                        v-else
                        class="flex min-h-[28rem] flex-col items-center justify-center rounded-3xl border-2 border-dashed px-6 py-10 text-center transition-colors"
                        :class="dragging ? 'border-[#0b8043] dark:border-[#3fcb7a] bg-[#e4f6ea] dark:bg-[#17321f]' : 'border-[#d6d3cc] dark:border-[#3d3d3d] bg-white dark:bg-[#1c1c1c]'"
                        aria-labelledby="scan-drop-title"
                        @dragover.prevent="dragging = true"
                        @dragleave="dragging = false"
                        @drop.prevent="onDrop"
                    >
                        <template v-if="busy">
                            <img v-if="previewUrl" :src="previewUrl" alt="Foto label yang sedang dibaca" class="mb-5 h-40 w-auto max-w-full rounded-2xl object-contain" />
                            <Loader2 class="h-8 w-8 animate-spin text-[#0b8043] dark:text-[#3fcb7a]" aria-hidden="true" />
                            <h2 id="scan-drop-title" class="mt-4 text-xl font-bold text-[#191919] dark:text-[#f0efea]" role="status">Membaca label…</h2>
                            <p class="mt-1 text-sm text-[#5e5e5e] dark:text-[#a3a3a3]">Biasanya sekitar 5 detik, paling lama 25 detik.</p>
                        </template>

                        <template v-else>
                            <ScanLine class="h-20 w-20 text-[#191919] dark:text-[#f0efea]" aria-hidden="true" />
                            <h2 id="scan-drop-title" class="mt-4 text-xl font-bold text-[#191919] dark:text-[#f0efea]">Seret foto label ke sini</h2>
                            <p class="mt-1 text-sm text-[#5e5e5e] dark:text-[#a3a3a3]">JPEG atau PNG, di bawah 5 MB.</p>

                            <p v-if="phase === 'error'" class="mt-5 max-w-md rounded-xl bg-[#ffece5] dark:bg-[#3a1e14] px-4 py-3 text-sm text-[#a52f08] dark:text-red-300" role="alert">{{ errorText }}</p>

                            <div class="mt-6 flex flex-wrap justify-center gap-3">
                                <button type="button" :disabled="!aiEnabled" class="inline-flex h-11 items-center gap-2 rounded-full bg-[#0b8043] px-6 text-sm font-semibold text-white hover:bg-[#086637] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919] disabled:cursor-not-allowed disabled:opacity-50" @click="cameraInput?.click()">
                                    <Camera class="h-4 w-4" aria-hidden="true" /> Ambil Foto
                                </button>
                                <button type="button" :disabled="!aiEnabled" class="inline-flex h-11 items-center gap-2 rounded-full border-2 border-[#191919] dark:border-[#f0efea] bg-white dark:bg-[#1c1c1c] px-6 text-sm font-semibold text-[#191919] dark:text-[#f0efea] hover:bg-gray-50 dark:hover:bg-[#262626] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919] disabled:cursor-not-allowed disabled:opacity-50" @click="uploadInput?.click()">
                                    <Upload class="h-4 w-4" aria-hidden="true" /> Unggah Foto
                                </button>
                            </div>
                            <input ref="cameraInput" type="file" accept="image/jpeg,image/png" capture="environment" class="sr-only" tabindex="-1" aria-hidden="true" @change="onPick" />
                            <input ref="uploadInput" type="file" accept="image/jpeg,image/png" class="sr-only" tabindex="-1" aria-hidden="true" @change="onPick" />
                        </template>
                    </section>
                </div>

                <aside class="flex flex-col gap-6">
                    <template v-if="phase === 'done'">
                        <section class="rounded-3xl bg-white dark:bg-[#1c1c1c] p-6 shadow-[0_1px_2px_rgba(30,35,33,0.06),0_4px_12px_rgba(30,35,33,0.06)]">
                            <h2 class="font-display text-xl font-bold text-[#191919] dark:text-[#f0efea]">Apa artinya?</h2>
                            <p class="mt-3 text-base text-[#191919] dark:text-[#f0efea]">Periksa gula, lemak, dan natrium per sajian.</p>
                            <p class="mt-2 text-sm text-[#5e5e5e] dark:text-[#a3a3a3]">Bandingkan takaran saji dengan porsi yang kamu makan.</p>
                        </section>
                        <section class="rounded-3xl bg-[#ffece5] dark:bg-[#3a1e14] p-6">
                            <h2 class="font-display text-lg font-bold text-[#191919] dark:text-[#f0efea]">Ingin tahu lebih banyak?</h2>
                            <p class="mt-2 text-sm text-[#191919] dark:text-[#f0efea]">Tanyakan pengganti atau takaran lewat Chat Gizi.</p>
                            <Link :href="route('user.chat_gizi')" class="mt-4 inline-flex h-11 items-center gap-2 rounded-full border-2 border-[#191919] dark:border-[#f0efea] bg-white dark:bg-[#1c1c1c] px-5 text-sm font-semibold text-[#191919] dark:text-[#f0efea] hover:bg-gray-50 dark:hover:bg-[#262626] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919]">
                                <MessageCircle class="h-4 w-4" aria-hidden="true" /> Tanya Chat Gizi
                            </Link>
                        </section>
                    </template>
                    <section v-else class="rounded-3xl bg-white dark:bg-[#1c1c1c] p-6 shadow-[0_1px_2px_rgba(30,35,33,0.06),0_4px_12px_rgba(30,35,33,0.06)]">
                        <h2 class="font-display text-xl font-bold text-[#191919] dark:text-[#f0efea]">Agar terbaca jelas</h2>
                        <ul class="mt-4 flex flex-col gap-3 text-base text-[#191919] dark:text-[#f0efea]">
                            <li class="flex items-center gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#e4f6ea] dark:bg-[#17321f]"><ImageIcon class="h-4 w-4 text-[#0b8043] dark:text-[#3fcb7a]" aria-hidden="true" /></span>Foto tabel gizinya saja</li>
                            <li class="flex items-center gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#e4f6ea] dark:bg-[#17321f]"><Lightbulb class="h-4 w-4 text-[#0b8043] dark:text-[#3fcb7a]" aria-hidden="true" /></span>Tanpa pantulan cahaya</li>
                            <li class="flex items-center gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#e4f6ea] dark:bg-[#17321f]"><ScanLine class="h-4 w-4 text-[#0b8043] dark:text-[#3fcb7a]" aria-hidden="true" /></span>Semua angka terlihat jelas</li>
                        </ul>
                        <p class="mt-5 flex items-start gap-2 rounded-xl bg-[#f4f3f0] dark:bg-[#262626] px-3 py-3 text-sm text-[#191919] dark:text-[#f0efea]"><ShieldCheck class="mt-0.5 h-4 w-4 shrink-0 text-[#0b8043] dark:text-[#3fcb7a]" aria-hidden="true" />Foto langsung dibuang setelah dibaca. Fotonya diproses penyedia AI untuk membaca angkanya.</p>
                        <p class="mt-4 text-sm text-[#5e5e5e] dark:text-[#a3a3a3]">Batas pindai: {{ props.scanLimitPerDay }} foto per hari.</p>
                    </section>
                </aside>
            </div>
        </div>
    </UserLayout>
</template>
