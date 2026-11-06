<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check, Info } from 'lucide-vue-next';

interface Props {
    user?: any;
}

defineProps<Props>();

const days = ['Sn', 'Sl', 'Rb', 'Km', 'Jm', 'Sb', 'Mg'];

const members = [
    {
        id: 'bs',
        initials: 'BS',
        name: 'Budi',
        role: 'Anggota',
        logs: [true, true, true, false, true, true, false],
        badge: '5 dari 7',
        met: true,
    },
    {
        id: 'rs',
        initials: 'RS',
        name: 'Rina',
        role: 'Kepala Keluarga',
        logs: [true, true, true, true, true, true, false],
        badge: '6 dari 7',
        met: true,
    },
    {
        id: 'rk',
        initials: 'RK',
        name: 'Raka',
        role: 'Anggota',
        logs: [true, true, false, true, true, false, false],
        badge: '4 dari 7',
        met: false,
    },
    {
        id: 'dn',
        initials: 'DN',
        name: 'Dina',
        role: 'Anggota · 17 tahun',
        logs: [true, false, false, true, false, false, false],
        badge: '2 dari 7',
        met: false,
    },
];
</script>

<template>
    <section id="meja-keluarga" class="relative overflow-hidden bg-white py-20">
        <div class="mx-auto max-w-[1440px] px-4 sm:px-6 lg:px-8">
            <div
                class="relative overflow-hidden rounded-[36px] border border-[#eceae5] bg-[#fef9e7] p-6 shadow-xs sm:p-12 lg:p-16"
            >
                <!-- Large Decorative Warm Yellow Circle Behind Mockup -->
                <div
                    class="pointer-events-none absolute top-1/2 -right-16 hidden h-[480px] w-[480px] -translate-y-1/2 rounded-full bg-[#fde8aa]/50 lg:block"
                ></div>

                <!-- 4-point Decorative Stars -->
                <div
                    class="pointer-events-none absolute top-10 right-[48%] text-[#eb4416] select-none"
                >
                    <svg
                        class="h-6 w-6"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                    >
                        <path
                            d="M12 0L14.5 9.5L24 12L14.5 14.5L12 24L9.5 14.5L0 12L9.5 9.5L12 0Z"
                        />
                    </svg>
                </div>
                <div
                    class="pointer-events-none absolute right-12 bottom-8 text-[#6c4fd8] select-none"
                >
                    <svg
                        class="h-6 w-6"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                    >
                        <path
                            d="M12 0L14.5 9.5L24 12L14.5 14.5L12 24L9.5 14.5L0 12L9.5 9.5L12 0Z"
                        />
                    </svg>
                </div>

                <div
                    class="relative z-10 grid grid-cols-1 items-center gap-12 lg:grid-cols-12 lg:gap-14"
                >
                    <!-- Left Column: Copy & Checklist -->
                    <div class="space-y-6 lg:col-span-5">
                        <!-- Top Pill Badge -->
                        <div
                            class="inline-flex items-center gap-1.5 rounded-full bg-[#e4f6ea] px-3 py-1 text-xs font-bold text-[#0b8043]"
                        >
                            <Check class="h-3.5 w-3.5 stroke-[3]" />
                            <span>Meja Keluarga</span>
                        </div>

                        <!-- Main Heading -->
                        <h2
                            class="font-display text-3xl leading-[1.15] font-bold tracking-tight text-[#191919] sm:text-5xl"
                        >
                            Lihat siapa yang sudah mencatat, tanpa membuka isi
                            piringnya
                        </h2>

                        <!-- 3 Checklist Items with Green Circle Icons -->
                        <div class="space-y-4 pt-2">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#0b8043] text-white"
                                >
                                    <Check class="h-3.5 w-3.5 stroke-[3]" />
                                </div>
                                <span
                                    class="text-sm font-semibold text-[#191919] sm:text-base"
                                >
                                    Target bersama, misalnya 5 dari 7 hari
                                </span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#0b8043] text-white"
                                >
                                    <Check class="h-3.5 w-3.5 stroke-[3]" />
                                </div>
                                <span
                                    class="text-sm font-semibold text-[#191919] sm:text-base"
                                >
                                    Isi piring dan berat badan tetap privat
                                </span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#0b8043] text-white"
                                >
                                    <Check class="h-3.5 w-3.5 stroke-[3]" />
                                </div>
                                <span
                                    class="text-sm font-semibold text-[#191919] sm:text-base"
                                >
                                    Lencana saat minggu ini kompak
                                </span>
                            </div>
                        </div>

                        <!-- CTA Button: Buat Grup Keluarga -->
                        <div class="pt-4">
                            <Link
                                :href="
                                    user
                                        ? route('dashboard')
                                        : route('register')
                                "
                                class="inline-flex items-center justify-center rounded-full bg-[#0b8043] px-8 py-3.5 text-base font-bold text-white shadow-md transition-all hover:scale-105 hover:bg-[#086637] hover:shadow-lg"
                            >
                                Buat Grup Keluarga
                            </Link>
                        </div>
                    </div>

                    <!-- Right Column: Interactive Weekly Table Mockup Card -->
                    <div class="relative lg:col-span-7">
                        <!-- Main White Card -->
                        <div
                            class="relative z-10 rounded-3xl border border-[#eceae5] bg-white p-6 shadow-xl sm:p-8"
                        >
                            <!-- Card Header -->
                            <div
                                class="flex items-start justify-between gap-4 border-b border-[#f4f3f0] pb-4"
                            >
                                <div>
                                    <h3
                                        class="font-display text-lg font-bold text-[#191919] sm:text-xl"
                                    >
                                        Meja Keluarga · 26 Okt sampai 1 Nov
                                    </h3>
                                    <p class="mt-1 text-xs text-[#5e5e5e]">
                                        Target bersama: masing-masing mencatat 5
                                        hari
                                    </p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p
                                        class="font-display text-2xl leading-none font-bold text-[#191919]"
                                    >
                                        2 dari 4
                                    </p>
                                    <p
                                        class="mt-0.5 text-[11px] text-[#5e5e5e]"
                                    >
                                        mencapai 5 hari
                                    </p>
                                </div>
                            </div>

                            <!-- Progress Bar -->
                            <div
                                class="my-4 h-2 w-full overflow-hidden rounded-full bg-[#f4f3f0]"
                            >
                                <div
                                    class="h-full rounded-full bg-[#0b8043]"
                                    style="width: 58%"
                                ></div>
                            </div>

                            <!-- Table -->
                            <div class="overflow-x-auto">
                                <table
                                    class="w-full text-left text-xs sm:text-sm"
                                >
                                    <thead>
                                        <tr
                                            class="border-b border-[#f4f3f0] text-xs text-[#5e5e5e]"
                                        >
                                            <th class="py-2.5 font-bold">
                                                Anggota
                                            </th>
                                            <th
                                                v-for="d in days"
                                                :key="d"
                                                class="px-1.5 py-2.5 text-center font-medium"
                                            >
                                                {{ d }}
                                            </th>
                                            <th
                                                class="py-2.5 text-right font-bold"
                                            >
                                                Hari
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[#f4f3f0]">
                                        <tr
                                            v-for="m in members"
                                            :key="m.id"
                                            class="transition-colors hover:bg-[#fef9e7]/50"
                                        >
                                            <!-- Member Info with Initial Box -->
                                            <td class="py-3 pr-2">
                                                <div
                                                    class="flex items-center gap-2.5"
                                                >
                                                    <span
                                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-[#f4f3f0] text-xs font-bold text-[#191919]"
                                                    >
                                                        {{ m.initials }}
                                                    </span>
                                                    <div class="leading-tight">
                                                        <p
                                                            class="text-xs font-bold text-[#191919] sm:text-sm"
                                                        >
                                                            {{ m.name }}
                                                        </p>
                                                        <p
                                                            class="text-[11px] text-[#5e5e5e]"
                                                        >
                                                            {{ m.role }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- 7 Day Checkmarks -->
                                            <td
                                                v-for="(
                                                    recorded, idx
                                                ) in m.logs"
                                                :key="idx"
                                                class="px-1.5 py-3 text-center"
                                            >
                                                <span
                                                    v-if="recorded"
                                                    class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-[#0b8043] text-white"
                                                >
                                                    <Check
                                                        class="h-3 w-3 stroke-[3]"
                                                    />
                                                </span>
                                                <span
                                                    v-else
                                                    class="inline-block h-5 w-5 rounded-full border border-dashed border-[#d6d3cc]"
                                                ></span>
                                            </td>

                                            <!-- Total Days Badge -->
                                            <td class="py-3 pl-2 text-right">
                                                <span
                                                    class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap"
                                                    :class="
                                                        m.met
                                                            ? 'bg-[#e6f6ec] text-[#0b7a46]'
                                                            : 'bg-[#f4f3f0] text-[#5e5e5e]'
                                                    "
                                                >
                                                    <Check
                                                        v-if="m.met"
                                                        class="h-3 w-3 stroke-[3]"
                                                    />
                                                    <span>{{ m.badge }}</span>
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Footer Privacy Notice Inside Card -->
                            <div
                                class="mt-5 flex items-center gap-2 rounded-xl bg-[#f4f3f0] px-4 py-2.5 text-xs text-[#5e5e5e]"
                            >
                                <Info
                                    class="h-3.5 w-3.5 shrink-0 text-[#5e5e5e]"
                                />
                                <span
                                    >Hanya hari tercatat yang terlihat oleh
                                    keluarga, bukan isi piring atau berat
                                    badan.</span
                                >
                            </div>
                        </div>

                        <!-- Floating Streak Pill Below Card -->
                        <div
                            class="absolute -bottom-5 left-10 z-20 inline-flex items-center gap-2.5 rounded-full border border-[#eceae5] bg-white px-5 py-2.5 text-xs font-bold text-[#191919] shadow-lg"
                        >
                            <span
                                class="flex h-5 w-5 items-center justify-center rounded-full bg-[#eb4416]/10 text-xs text-[#eb4416]"
                                >🥣</span
                            >
                            <span>5 hari berturut-turut</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
