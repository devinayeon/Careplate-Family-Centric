<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Calculator } from 'lucide-vue-next';
import { idNumber } from '@/lib/foodUi';

export interface Ringkasan {
    kalori: number;
    target_kalori: number | null;
    protein: number;
    karbohidrat: number;
    lemak: number;
    gula: number;
    natrium: number;
}

const props = defineProps<{
    ringkasan: Ringkasan;
    batas: { gula: number; natrium: number; lemak: number };
}>();

const RADIUS = 52;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

const target = computed(() => props.ringkasan.target_kalori);
const ratio = computed(() =>
    target.value ? props.ringkasan.kalori / target.value : 0,
);
const over = computed(() => ratio.value > 1);
const dashOffset = computed(
    () => CIRCUMFERENCE * (1 - Math.min(1, ratio.value)),
);
const remainingText = computed(() => {
    if (!target.value) return '';
    const diff = Math.round(target.value - props.ringkasan.kalori);

    return diff >= 0
        ? `Sisa ${idNumber.format(diff)} kkal`
        : `Lebih ${idNumber.format(-diff)} kkal`;
});

const macros = computed(() => [
    { label: 'Protein', value: props.ringkasan.protein },
    { label: 'Karbohidrat', value: props.ringkasan.karbohidrat },
    { label: 'Lemak', value: props.ringkasan.lemak },
]);

const limits = computed(() =>
    (
        [
            { key: 'gula', label: 'Gula', unit: 'g' },
            { key: 'natrium', label: 'Natrium', unit: 'mg' },
            { key: 'lemak', label: 'Lemak', unit: 'g' },
        ] as const
    ).map((row) => {
        const value = props.ringkasan[row.key];
        const limit = props.batas[row.key];
        const percent = limit > 0 ? Math.round((value / limit) * 100) : 0;

        return {
            ...row,
            value,
            limit,
            percent,
            barWidth: Math.min(100, percent),
            warn: percent >= 70,
        };
    }),
);
</script>

<template>
    <div class="flex flex-col gap-6">
        <section
            class="rounded-3xl bg-white p-6 shadow-[0_1px_2px_rgba(30,35,33,0.06),0_4px_12px_rgba(30,35,33,0.06)] dark:bg-[#1c1c1c]"
            aria-labelledby="ringkasan-title"
        >
            <h2
                id="ringkasan-title"
                class="font-display text-xl font-bold text-[#191919] dark:text-[#f0efea]"
            >
                Ringkasan hari ini
            </h2>

            <div v-if="target" class="mt-5 flex items-center gap-5">
                <div class="relative h-32 w-32 shrink-0">
                    <svg
                        viewBox="0 0 120 120"
                        class="h-full w-full -rotate-90"
                        aria-hidden="true"
                    >
                        <circle
                            cx="60"
                            cy="60"
                            :r="RADIUS"
                            fill="none"
                            stroke-width="10"
                            class="stroke-[#eceae5] dark:stroke-[#2e2e2e]"
                        />
                        <circle
                            cx="60"
                            cy="60"
                            :r="RADIUS"
                            fill="none"
                            stroke-width="10"
                            stroke-linecap="round"
                            :stroke-dasharray="CIRCUMFERENCE"
                            :stroke-dashoffset="dashOffset"
                            :class="
                                over
                                    ? 'stroke-[#a04b00] dark:stroke-[#e8a35a]'
                                    : 'stroke-[#0b8043] dark:stroke-[#3fcb7a]'
                            "
                        />
                    </svg>
                    <div
                        class="absolute inset-0 flex flex-col items-center justify-center"
                    >
                        <span
                            class="font-display text-2xl leading-none font-extrabold text-[#191919] tabular-nums dark:text-[#f0efea]"
                            >{{ idNumber.format(ringkasan.kalori) }}</span
                        >
                        <span
                            class="mt-1 text-xs text-[#5e5e5e] dark:text-[#a3a3a3]"
                            >dari {{ idNumber.format(target) }}
                            <span class="sr-only">kkal</span></span
                        >
                    </div>
                </div>
                <div class="min-w-0">
                    <p
                        class="font-display text-lg font-bold"
                        :class="
                            over
                                ? 'text-[#a04b00] dark:text-[#e8a35a]'
                                : 'text-[#191919] dark:text-[#f0efea]'
                        "
                    >
                        {{ remainingText }}
                    </p>
                    <p class="mt-1 text-sm text-[#5e5e5e] dark:text-[#a3a3a3]">
                        Target dari kebutuhan kalori terakhir.
                    </p>
                </div>
            </div>

            <div v-else class="mt-4">
                <p class="flex items-baseline gap-2">
                    <span
                        class="font-display text-4xl leading-none font-extrabold text-[#191919] tabular-nums dark:text-[#f0efea]"
                        >{{ idNumber.format(ringkasan.kalori) }}</span
                    >
                    <span class="text-base text-[#5e5e5e] dark:text-[#a3a3a3]"
                        >kkal hari ini</span
                    >
                </p>
                <div
                    class="mt-4 rounded-2xl bg-[#f4f3f0] px-4 py-3 dark:bg-[#262626]"
                >
                    <p class="text-sm text-[#191919] dark:text-[#f0efea]">
                        Belum ada target kalori.
                    </p>
                    <Link
                        :href="route('user.calculator')"
                        class="mt-1 inline-flex items-center gap-1.5 text-sm font-semibold text-[#0b8043] underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919] dark:text-[#3fcb7a] dark:focus-visible:outline-[#f0efea]"
                    >
                        <Calculator class="h-4 w-4" aria-hidden="true" />
                        Hitung di Kalkulator
                    </Link>
                </div>
            </div>

            <dl
                class="mt-6 grid grid-cols-3 gap-3 border-t border-[#eceae5] pt-5 dark:border-[#2e2e2e]"
            >
                <div v-for="m in macros" :key="m.label">
                    <dt class="text-sm text-[#5e5e5e] dark:text-[#a3a3a3]">
                        {{ m.label }}
                    </dt>
                    <dd
                        class="mt-1 text-lg font-bold text-[#191919] tabular-nums dark:text-[#f0efea]"
                    >
                        {{ idNumber.format(m.value) }} g
                    </dd>
                </div>
            </dl>
        </section>

        <section
            class="rounded-3xl bg-white p-6 shadow-[0_1px_2px_rgba(30,35,33,0.06),0_4px_12px_rgba(30,35,33,0.06)] dark:bg-[#1c1c1c]"
            aria-labelledby="batas-title"
        >
            <h2
                id="batas-title"
                class="font-display text-xl font-bold text-[#191919] dark:text-[#f0efea]"
            >
                Batas harian
            </h2>
            <p class="mt-1 text-sm text-[#5e5e5e] dark:text-[#a3a3a3]">
                Mengacu Permenkes 30/2013.
            </p>
            <ul class="mt-4 divide-y divide-[#eceae5] dark:divide-[#2e2e2e]">
                <li v-for="row in limits" :key="row.key" class="py-4">
                    <div class="flex items-baseline justify-between gap-4">
                        <span
                            class="text-base text-[#191919] dark:text-[#f0efea]"
                            >{{ row.label }}</span
                        >
                        <span
                            class="text-base font-semibold text-[#191919] tabular-nums dark:text-[#f0efea]"
                            >{{ idNumber.format(row.value) }}
                            {{ row.unit }}</span
                        >
                    </div>
                    <div
                        class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-[#eceae5] dark:bg-[#2e2e2e]"
                        role="progressbar"
                        :aria-label="`${row.label} terhadap batas harian`"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        :aria-valuenow="row.barWidth"
                        :aria-valuetext="`${row.percent}% dari batas ${idNumber.format(row.limit)} ${row.unit}`"
                    >
                        <div
                            class="h-full rounded-full"
                            :class="
                                row.warn
                                    ? 'bg-[#a04b00] dark:bg-[#e8a35a]'
                                    : 'bg-[#0b8043] dark:bg-[#3fcb7a]'
                            "
                            :style="{ width: `${row.barWidth}%` }"
                        />
                    </div>
                    <p
                        class="mt-1 text-xs"
                        :class="
                            row.percent > 100
                                ? 'font-semibold text-[#a04b00] dark:text-[#e8a35a]'
                                : 'text-[#5e5e5e] dark:text-[#a3a3a3]'
                        "
                    >
                        {{ row.percent }}% dari batas
                        {{ idNumber.format(row.limit) }} {{ row.unit
                        }}<template v-if="row.percent > 100">
                            · lewat batas</template
                        >
                    </p>
                </li>
            </ul>
        </section>
    </div>
</template>
