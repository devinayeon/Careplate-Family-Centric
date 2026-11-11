<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import UserLayout from '@/Layouts/UserLayout.vue';
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
} from 'lucide-vue-next';

const props = defineProps<{
    pengguna?: any;
    targetKalori: number;
    kaloriTerkonsumsi: number;
    streakHari: number;
    keluarga?: any;
    misi: {
        list: Array<{
            id: number;
            title: string;
            action_label: string;
            route: string;
            completed: boolean;
        }>;
        completed_count: number;
        total_count: number;
    };
    lencana: Array<{
        id: string;
        title: string;
        description: string;
        achieved: boolean;
    }>;
}>();

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);

const firstName = computed(() => {
    if (!user.value?.name) return 'Sobat';
    return user.value.name.split(' ')[0];
});

// Current formatted date in Indonesian
const todayFormatted = computed(() => {
    const options: Intl.DateTimeFormatOptions = {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    };
    return new Intl.DateTimeFormat('id-ID', options).format(new Date());
});

const hasCalorieTarget = computed(() => {
    return props.targetKalori > 0;
});

const progressPercentage = computed(() => {
    if (!props.targetKalori || props.targetKalori <= 0) return 0;
    return Math.min(
        100,
        Math.round((props.kaloriTerkonsumsi / props.targetKalori) * 100),
    );
});
</script>

<template>
    <Head title="Beranda" />

    <UserLayout>
        <div class="max-w-5xl space-y-6">
            <!-- 1. Hero Welcome Banner (Figma Node 24-5629) -->
            <div
                class="relative flex flex-col items-start justify-between gap-6 overflow-hidden rounded-3xl border border-[#fed7c0]/40 bg-[#fff2ea] p-6 transition-colors sm:flex-row sm:items-center sm:p-8 dark:border-[#542817] dark:bg-[#3a1e14]"
            >
                <div class="z-10 max-w-lg">
                    <h1
                        class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl dark:text-[#f0efea]"
                    >
                        Halo, {{ firstName }}
                    </h1>
                    <p
                        class="mt-1 text-sm text-gray-600 sm:text-base dark:text-[#a3a3a3]"
                    >
                        {{ todayFormatted }}. Yuk mulai dari catatan pertama.
                    </p>

                    <div class="mt-5">
                        <Link
                            :href="route('user.meals')"
                            class="inline-flex items-center gap-2 rounded-full bg-[#0f6b36] px-5 py-2.5 text-sm font-medium text-white shadow-sm transition-all hover:bg-[#0c592c] hover:shadow-md active:scale-95 dark:bg-[#3fcb7a] dark:text-[#0e1a13] dark:hover:bg-[#34b86c]"
                        >
                            <Plus class="h-4 w-4" />
                            <span>Catat Makanan</span>
                        </Link>
                    </div>
                </div>

                <!-- Illustration Bowl / Mascot -->
                <div
                    class="flex shrink-0 items-center justify-center self-end pr-2 sm:self-center"
                >
                    <div
                        class="relative flex h-28 w-28 items-center justify-center sm:h-36 sm:w-36"
                    >
                        <!-- Decorative Bowl Vector -->
                        <div
                            class="relative flex h-16 w-24 items-center justify-center rounded-b-full border-2 border-sky-200 bg-white/90 shadow-sm dark:border-[#3d3d3d] dark:bg-[#262626]"
                        >
                            <div
                                class="absolute -top-3 h-4 w-20 rounded-full bg-emerald-400/80 blur-[1px] dark:bg-[#3fcb7a]/80"
                            ></div>
                            <Utensils
                                class="h-8 w-8 text-[#0f6b36] opacity-80 dark:text-[#3fcb7a]"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Main Grid: Left (Piring & Misi), Right (Keluarga & Lencana) -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                <!-- Left Section (8 cols) -->
                <div class="space-y-6 lg:col-span-7">
                    <!-- Card Piring Hari Ini -->
                    <div
                        class="rounded-3xl border border-gray-100 bg-white p-6 shadow-xs transition-colors dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
                    >
                        <h2
                            class="mb-5 text-base font-bold text-gray-900 dark:text-[#f0efea]"
                        >
                            Piring hari ini
                        </h2>

                        <div
                            class="flex flex-col items-center gap-6 sm:flex-row sm:gap-8"
                        >
                            <!-- Radial Ring Counter -->
                            <div
                                class="relative flex h-36 w-36 shrink-0 items-center justify-center"
                            >
                                <svg
                                    class="h-full w-full -rotate-90"
                                    viewBox="0 0 100 100"
                                >
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
                                        :stroke-dashoffset="
                                            264 -
                                            (264 * progressPercentage) / 100
                                        "
                                        class="transition-all duration-700 ease-out dark:stroke-[#3fcb7a]"
                                    />
                                </svg>

                                <div
                                    class="absolute inset-0 flex flex-col items-center justify-center px-2 text-center"
                                >
                                    <span
                                        class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-[#f0efea]"
                                    >
                                        {{
                                            hasCalorieTarget
                                                ? Math.round(kaloriTerkonsumsi)
                                                : '0'
                                        }}
                                    </span>
                                    <span
                                        class="text-[11px] leading-tight font-medium text-gray-400 dark:text-[#a3a3a3]"
                                    >
                                        {{
                                            hasCalorieTarget
                                                ? `dari ${Math.round(targetKalori)} kkal`
                                                : 'belum ada target'
                                        }}
                                    </span>
                                </div>
                            </div>

                            <!-- Explanation & CTA -->
                            <div class="flex-1 text-center sm:text-left">
                                <h3
                                    class="text-base font-semibold text-gray-900 dark:text-[#f0efea]"
                                >
                                    {{
                                        hasCalorieTarget
                                            ? 'Kebutuhan Kalori Terpasang'
                                            : 'Piringmu belum punya target'
                                    }}
                                </h3>
                                <p
                                    class="mt-1 text-xs leading-relaxed text-gray-500 dark:text-[#a3a3a3]"
                                >
                                    {{
                                        hasCalorieTarget
                                            ? 'Lanjutkan mencatat porsi makan harianmu untuk menjaga keseimbangan gizi keluarga.'
                                            : 'Hitung kebutuhan kalori dulu, lalu catatan makan mengisi piring.'
                                    }}
                                </p>

                                <div class="mt-4">
                                    <Link
                                        :href="route('user.calculator')"
                                        class="inline-flex items-center gap-2 rounded-xl bg-[#0f6b36] px-4 py-2 text-xs font-semibold text-white shadow-xs transition-all hover:bg-[#0c592c] dark:bg-[#3fcb7a] dark:text-[#0e1a13] dark:hover:bg-[#34b86c]"
                                    >
                                        <Calculator class="h-3.5 w-3.5" />
                                        <span>Hitung Kebutuhan Kalori</span>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Misi Hari Ini -->
                    <div
                        class="rounded-3xl border border-gray-100 bg-white p-6 shadow-xs transition-colors dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <h2
                                class="text-base font-bold text-gray-900 dark:text-[#f0efea]"
                            >
                                Misi hari ini
                            </h2>
                            <span
                                class="text-xs font-medium text-gray-400 dark:text-[#a3a3a3]"
                            >
                                {{ misi.completed_count }} dari
                                {{ misi.total_count }}
                            </span>
                        </div>

                        <div
                            class="divide-y divide-gray-50 dark:divide-[#262626]"
                        >
                            <div
                                v-for="item in misi.list"
                                :key="item.id"
                                class="flex items-center justify-between gap-4 py-3"
                            >
                                <div class="flex items-center gap-3">
                                    <CheckCircle2
                                        v-if="item.completed"
                                        class="h-5 w-5 shrink-0 text-[#0f6b36] dark:text-[#3fcb7a]"
                                    />
                                    <Circle
                                        v-else
                                        class="h-5 w-5 shrink-0 text-gray-300 dark:text-gray-600"
                                    />
                                    <span
                                        class="text-sm"
                                        :class="
                                            item.completed
                                                ? 'text-gray-400 line-through dark:text-gray-500'
                                                : 'font-medium text-gray-700 dark:text-[#f0efea]'
                                        "
                                    >
                                        {{ item.title }}
                                    </span>
                                </div>

                                <Link
                                    :href="
                                        route().has(item.route)
                                            ? route(item.route)
                                            : '#'
                                    "
                                    class="shrink-0 text-xs font-semibold text-[#0f6b36] hover:underline dark:text-[#3fcb7a]"
                                >
                                    {{ item.action_label }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Section (5 cols) -->
                <div class="space-y-6 lg:col-span-5">
                    <!-- Card Grup Keluarga -->
                    <div
                        class="rounded-3xl border border-gray-100 bg-white p-6 text-center shadow-xs transition-colors sm:text-left dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
                    >
                        <div
                            class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-orange-50 sm:mx-0 dark:bg-[#3a1e14]"
                        >
                            <img
                                src="/assets/piri-mascot.png"
                                alt="CarePlate Mascot"
                                class="h-8 w-8 object-contain"
                            />
                        </div>

                        <div v-if="!keluarga">
                            <h3
                                class="text-base font-bold text-gray-900 dark:text-[#f0efea]"
                            >
                                Belum ada grup keluarga
                            </h3>
                            <p
                                class="mt-1 text-xs leading-relaxed text-gray-500 dark:text-[#a3a3a3]"
                            >
                                Buat grup atau gabung dengan kode keluargamu.
                            </p>

                            <div class="mt-5">
                                <Link
                                    :href="route('user.family')"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#0f6b36] px-4 py-2.5 text-xs font-semibold text-white shadow-xs transition-all hover:bg-[#0c592c] dark:bg-[#3fcb7a] dark:text-[#0e1a13] dark:hover:bg-[#34b86c]"
                                >
                                    <Users2 class="h-4 w-4" />
                                    <span>Mulai Grup Keluarga</span>
                                </Link>
                            </div>
                        </div>

                        <div v-else>
                            <h3
                                class="text-base font-bold text-gray-900 dark:text-[#f0efea]"
                            >
                                {{ keluarga.nama_keluarga }}
                            </h3>
                            <p
                                class="mt-1 text-xs text-gray-500 dark:text-[#a3a3a3]"
                            >
                                Kode grup:
                                <span
                                    class="font-mono font-bold text-emerald-800 dark:text-emerald-300"
                                    >{{ keluarga.kode_keluarga }}</span
                                >
                            </p>

                            <div class="mt-5">
                                <Link
                                    :href="route('user.family')"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-50 px-4 py-2.5 text-xs font-semibold text-[#0f6b36] transition-all hover:bg-emerald-100 dark:bg-[#17321f] dark:text-[#3fcb7a] dark:hover:bg-[#20442b]"
                                >
                                    <span>Lihat Anggota Keluarga</span>
                                    <ArrowRight class="h-3.5 w-3.5" />
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Card Lencana Pertama Menunggu -->
                    <div
                        class="rounded-3xl border border-gray-100 bg-white p-6 shadow-xs transition-colors dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
                    >
                        <h3
                            class="mb-4 text-base font-bold text-gray-900 dark:text-[#f0efea]"
                        >
                            Lencana pertama menunggu
                        </h3>

                        <div class="grid grid-cols-2 gap-4">
                            <div
                                v-for="badge in lencana"
                                :key="badge.id"
                                class="flex flex-col items-center rounded-2xl border border-dashed border-gray-200 bg-gray-50/50 p-3 text-center dark:border-[#3d3d3d] dark:bg-[#262626]/50"
                            >
                                <div
                                    class="mb-2 flex h-14 w-14 items-center justify-center rounded-full bg-gray-200/80 text-gray-500 dark:bg-[#3d3d3d] dark:text-gray-300"
                                >
                                    <Award class="h-6 w-6" />
                                </div>
                                <div
                                    class="text-xs font-bold text-gray-800 dark:text-[#f0efea]"
                                >
                                    {{ badge.title }}
                                </div>
                                <div
                                    class="mt-0.5 text-[11px] text-gray-400 dark:text-[#a3a3a3]"
                                >
                                    {{ badge.description }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
