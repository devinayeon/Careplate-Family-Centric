<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { ArrowLeft, CheckCircle2, Database, MessageCircle, Utensils } from 'lucide-vue-next'
import UserLayout from '@/Layouts/UserLayout.vue'
import FoodStatusBadge from '@/Components/UI/FoodStatusBadge.vue'
import { DAILY_REFERENCE, idNumber } from '@/lib/foodUi'

const props = defineProps<{
    food: {
        id: number; nama: string; status: string; energi: number; protein: number; lemak: number; karbohidrat: number
        gula: number; natrium: number; takaran_saji: string; sumber: string; kategori: string | null
        foto_makanan_url: string | null; diverifikasi_pada: string | null
    }
}>()

// Percent of the daily reference, capped at 100 for the bar; the printed number stays honest.
const rows = computed(() => [
    { label: 'Lemak total', value: props.food.lemak, unit: 'g', ref: DAILY_REFERENCE.lemak, note: 'AKG' },
    { label: 'Gula', value: props.food.gula, unit: 'g', ref: DAILY_REFERENCE.gula, note: 'dari batas 50 g' },
    { label: 'Protein', value: props.food.protein, unit: 'g', ref: DAILY_REFERENCE.protein, note: 'AKG' },
    { label: 'Karbohidrat total', value: props.food.karbohidrat, unit: 'g', ref: DAILY_REFERENCE.karbohidrat, note: 'AKG' },
    { label: 'Natrium', value: props.food.natrium, unit: 'mg', ref: DAILY_REFERENCE.natrium, note: 'dari batas 2.000 mg' },
].map((r) => ({ ...r, percent: Math.round((r.value / r.ref) * 100) })))

const verifiedOn = computed(() => (props.food.diverifikasi_pada ? new Date(props.food.diverifikasi_pada).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : null))
</script>

<template>
    <Head :title="food.nama" />
    <UserLayout>
        <div class="flex flex-col gap-6">
            <Link :href="route('user.foods')" class="inline-flex w-fit items-center gap-2 text-sm font-semibold text-[#191919] hover:underline focus-visible:outline-2 focus-visible:outline-[#191919]"><ArrowLeft class="h-4 w-4" aria-hidden="true" /> Data Makanan</Link>

            <div class="flex h-56 items-center justify-center overflow-hidden rounded-3xl bg-[#f4f3f0] sm:h-72">
                <img v-if="food.foto_makanan_url" :src="food.foto_makanan_url" :alt="`Foto ${food.nama}`" class="h-full w-full object-cover" />
                <Utensils v-else class="h-14 w-14 text-[#d6d3cc]" aria-hidden="true" />
            </div>

            <div class="grid gap-6 lg:grid-cols-[22rem_1fr]">
                <section aria-labelledby="food-name">
                    <h1 id="food-name" class="font-display text-3xl font-extrabold text-[#191919]">{{ food.nama }}</h1>
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-[#5e5e5e]">
                        <FoodStatusBadge :status="food.status" />
                        <span v-if="food.kategori">{{ food.kategori }}</span>
                    </div>
                    <dl class="mt-5 divide-y divide-[#eceae5] text-base">
                        <div class="flex justify-between gap-4 py-3"><dt class="text-[#5e5e5e]">Takaran saji</dt><dd class="text-right font-semibold text-[#191919]">{{ food.takaran_saji }}</dd></div>
                        <div class="flex justify-between gap-4 py-3"><dt class="text-[#5e5e5e]">Sumber</dt><dd class="text-right font-semibold text-[#191919]">{{ food.sumber }}</dd></div>
                    </dl>
                    <Link :href="route('user.chat_gizi')" class="mt-5 inline-flex h-11 w-full items-center justify-center gap-2 rounded-full border-2 border-[#191919] bg-white text-sm font-semibold text-[#191919] hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919]"><MessageCircle class="h-4 w-4" aria-hidden="true" /> Tanya Chat Gizi</Link>
                </section>

                <section class="rounded-3xl bg-white p-6 shadow-[0_1px_2px_rgba(30,35,33,0.06),0_4px_12px_rgba(30,35,33,0.06)]" aria-labelledby="food-energy">
                    <p id="food-energy" class="text-sm font-semibold text-[#191919]">Energi per takaran saji</p>
                    <p class="mt-1 flex items-end gap-2"><span class="font-display text-6xl font-extrabold leading-none text-[#191919]">{{ idNumber.format(food.energi) }}</span><span class="pb-1 text-base text-[#5e5e5e]">kkal</span></p>
                    <ul class="mt-6 divide-y divide-[#eceae5] border-t border-[#eceae5]">
                        <li v-for="r in rows" :key="r.label" class="py-4">
                            <div class="flex items-baseline justify-between gap-4"><span class="text-base text-[#191919]">{{ r.label }}</span><span class="text-base font-semibold text-[#191919]">{{ idNumber.format(r.value) }} {{ r.unit }}</span></div>
                            <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-[#eceae5]" role="progressbar" :aria-label="`${r.label}: sumbangan terhadap kebutuhan harian`" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="Math.min(100, r.percent)"><div class="h-full rounded-full" :class="r.percent >= 30 && r.label !== 'Protein' ? 'bg-[#a04b00]' : 'bg-[#0b8043]'" :style="{ width: `${Math.min(100, Math.max(2, r.percent))}%` }" /></div>
                            <p class="mt-1 text-xs text-[#5e5e5e]">{{ r.percent }}% {{ r.note }}</p>
                        </li>
                    </ul>
                    <p class="flex items-start gap-2 border-t border-[#eceae5] pt-4 text-sm text-[#5e5e5e]">
                        <component :is="food.status === 'terverifikasi' ? Database : CheckCircle2" class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                        <span v-if="food.status === 'terverifikasi'">Sumber: Database CarePlate<template v-if="verifiedOn">, diverifikasi Admin pada {{ verifiedOn }}</template>.</span>
                        <span v-else>Pengajuan ini belum diverifikasi Admin, jadi belum dipakai Chat Gizi.</span>
                    </p>
                </section>
            </div>
        </div>
    </UserLayout>
</template>
