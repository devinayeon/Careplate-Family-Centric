<script setup lang="ts">
import { computed } from 'vue'
import { AlertTriangle, Info } from 'lucide-vue-next'
import type { ScanResult } from '@/types/ai'

const props = defineProps<{ result: ScanResult }>()

interface Row {
    key: string
    label: string
    unit: string
    contributionKey: string | null
    limitText: string | null
}

// Contribution keys come from the server (sugars_total, sodium, fat_total); the other nutrients have no daily bar.
const rows: Row[] = [
    { key: 'fat_g', label: 'Lemak total', unit: 'g', contributionKey: 'fat_total', limitText: 'AKG' },
    { key: 'sugars_total_g', label: 'Gula', unit: 'g', contributionKey: 'sugars_total', limitText: 'batas 50 g' },
    { key: 'protein_g', label: 'Protein', unit: 'g', contributionKey: null, limitText: null },
    { key: 'carbohydrate_g', label: 'Karbohidrat total', unit: 'g', contributionKey: null, limitText: null },
    { key: 'sodium_mg', label: 'Natrium', unit: 'mg', contributionKey: 'sodium', limitText: 'batas 2.000 mg' },
]

const formatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 })

const energy = computed(() => props.result.normalized_per_serving.energy_kcal ?? null)
const servingText = computed(() => {
    const s = props.result.extraction.serving_size
    if (!s || s.value === null) return 'takaran saji tidak terbaca'
    return `takaran saji ${formatter.format(s.value)} ${s.unit ?? ''}`.trim()
})

const lines = computed(() =>
    rows.map((row) => {
        const value = props.result.normalized_per_serving[row.key] ?? null
        const percent = row.contributionKey ? (props.result.assessment.daily_reference_contribution_percent[row.contributionKey] ?? null) : null

        return {
            ...row,
            value,
            display: value === null ? 'Tidak terbaca' : `${formatter.format(value)} ${row.unit}`,
            percent,
            percentText: percent === null ? null : `${formatter.format(percent)}% ${row.limitText === 'AKG' ? 'AKG' : `dari ${row.limitText}`}`,
            barWidth: percent === null ? 0 : Math.min(100, Math.max(2, percent)),
        }
    }),
)

// Yellow is only for "check the photo" problems; the server's standing notes (policy, reference) stay neutral.
const cautions = computed(() => {
    const list: string[] = []
    if (props.result.extraction_status === 'partial') list.push('Sebagian angka tidak terbaca dari foto. Angka yang kosong tidak dihitung.')
    if (props.result.extraction.needs_review) list.push('Beberapa angka sulit dibaca. Cocokkan dengan label aslinya.')

    return list
})
</script>

<template>
    <section class="rounded-3xl bg-white dark:bg-[#1c1c1c] p-6 shadow-[0_1px_2px_rgba(30,35,33,0.06),0_4px_12px_rgba(30,35,33,0.06)]" aria-labelledby="scan-energy-label">
        <div v-if="cautions.length" class="mb-5 flex flex-col gap-2" role="status">
            <p v-for="notice in cautions" :key="notice" class="flex items-start gap-2 rounded-xl bg-[#fff4cc] dark:bg-amber-950/40 px-3 py-2 text-sm text-[#7a4b00] dark:text-amber-300">
                <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                <span>{{ notice }}</span>
            </p>
        </div>

        <p id="scan-energy-label" class="text-sm font-semibold text-[#191919] dark:text-[#f0efea]">Energi per sajian</p>
        <p class="mt-1 flex items-end gap-2">
            <span class="font-display text-6xl font-extrabold leading-none text-[#191919] dark:text-[#f0efea]">{{ energy === null ? '—' : formatter.format(energy) }}</span>
            <span class="pb-1 text-base text-[#5e5e5e] dark:text-[#a3a3a3]">kkal</span>
        </p>
        <p class="mt-2 text-sm text-[#5e5e5e] dark:text-[#a3a3a3]">{{ servingText }}</p>

        <ul class="mt-6 divide-y divide-[#eceae5] dark:divide-[#2e2e2e] border-t border-[#eceae5] dark:border-[#2e2e2e]">
            <li v-for="line in lines" :key="line.key" class="py-4">
                <div class="flex items-baseline justify-between gap-4">
                    <span class="text-base text-[#191919] dark:text-[#f0efea]">{{ line.label }}</span>
                    <span class="text-base" :class="line.value === null ? 'font-normal text-[#5e5e5e] dark:text-[#a3a3a3]' : 'font-semibold text-[#191919] dark:text-[#f0efea]'">{{ line.display }}</span>
                </div>
                <template v-if="line.contributionKey">
                    <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-[#eceae5] dark:bg-[#2e2e2e]" role="progressbar" :aria-label="`${line.label}: sumbangan terhadap kebutuhan harian`" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="line.percent ?? 0">
                        <div class="h-full rounded-full" :class="line.percent !== null && line.percent >= 20 ? 'bg-[#a04b00] dark:bg-[#e8a35a]' : 'bg-[#0b8043]'" :style="{ width: `${line.barWidth}%` }" />
                    </div>
                    <p class="mt-1 text-xs text-[#5e5e5e] dark:text-[#a3a3a3]">{{ line.percentText ?? 'Persen harian tidak bisa dihitung' }}</p>
                </template>
            </li>
        </ul>

        <div class="flex flex-col gap-1 border-t border-[#eceae5] dark:border-[#2e2e2e] pt-4 text-sm text-[#5e5e5e] dark:text-[#a3a3a3]">
            <p class="flex items-start gap-2">
                <Info class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                <span>Hasil baca otomatis dari foto label. Periksa kembali angka penting.</span>
            </p>
            <p v-for="note in result.warnings" :key="note" class="pl-6 text-xs">{{ note }}</p>
        </div>
    </section>
</template>
