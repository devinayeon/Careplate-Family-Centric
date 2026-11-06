<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { CheckCircle2, ScanLine, Flame, Users } from 'lucide-vue-next';

interface Slide {
    id: 'catat' | 'pindai' | 'keluarga';
    title: string;
    subtitle: string;
}

const slides: Slide[] = [
    {
        id: 'catat',
        title: 'Catat',
        subtitle: 'Piring keluarga terisi setiap hari',
    },
    {
        id: 'pindai',
        title: 'Pindai',
        subtitle: 'Baca label kemasan',
    },
    {
        id: 'keluarga',
        title: 'Keluarga',
        subtitle: 'Satu meja, satu target',
    },
];

const currentSlide = ref(0);
let timer: number | null = null;

const setSlide = (index: number) => {
    currentSlide.value = index;
    resetTimer();
};

const nextSlide = () => {
    currentSlide.value = (currentSlide.value + 1) % slides.length;
};

const resetTimer = () => {
    if (timer) clearInterval(timer);
    timer = window.setInterval(nextSlide, 5000);
};

onMounted(() => {
    resetTimer();
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});
</script>

<template>
    <aside
        class="relative flex w-full flex-col justify-between overflow-hidden bg-[#0b8043] p-8 text-white select-none lg:w-[480px] xl:w-[520px] xl:p-12"
    >
        <!-- 1. Header / Brand Link -->
        <div class="relative z-10">
            <Link
                href="/"
                class="inline-block transition-opacity hover:opacity-90"
            >
                <span
                    class="font-display text-3xl font-bold tracking-tight text-white"
                >
                    CarePlate
                </span>
            </Link>
            <p class="mt-1 text-sm font-medium text-white/90">
                Selamat datang di CarePlate
            </p>
        </div>

        <!-- 2. Feature Showcase / Carousel Section -->
        <div class="relative z-10 my-auto py-8">
            <!-- Carousel Container with peek cards on left and right -->
            <div class="relative mx-auto flex items-center justify-center">
                <!-- Left peek card edge (matching Figma) -->
                <div
                    class="pointer-events-none absolute top-6 bottom-6 -left-16 hidden w-20 rounded-3xl bg-white/20 backdrop-blur-xs transition-all xl:block"
                ></div>

                <!-- Active Main Card -->
                <div
                    class="relative w-full max-w-[340px] rounded-3xl bg-white p-6 shadow-2xl transition-all duration-300 xl:max-w-[360px] xl:p-7"
                >
                    <!-- Card Title & Subtitle -->
                    <div class="text-center">
                        <h3
                            class="font-display text-2xl font-bold tracking-tight text-[#191919]"
                        >
                            {{ slides[currentSlide].title }}
                        </h3>
                        <p
                            class="mt-1 text-xs font-medium text-[#5e5e5e] sm:text-sm"
                        >
                            {{ slides[currentSlide].subtitle }}
                        </p>
                    </div>

                    <!-- Slide 0: Catat (Default Figma Node 10:720) -->
                    <div
                        v-if="slides[currentSlide].id === 'catat'"
                        class="relative mt-6"
                    >
                        <!-- Mini UI Window -->
                        <div
                            class="rounded-2xl border border-[#eceae5] bg-white p-4 shadow-xs"
                        >
                            <!-- Donut ring and stats -->
                            <div class="flex items-center gap-3.5">
                                <div class="relative h-20 w-20 shrink-0">
                                    <svg
                                        class="h-20 w-20 -rotate-90"
                                        viewBox="0 0 36 36"
                                    >
                                        <!-- Track -->
                                        <path
                                            class="text-[#eceae5]"
                                            stroke="currentColor"
                                            stroke-width="4.5"
                                            fill="none"
                                            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                        />
                                        <!-- 67% filled green stroke -->
                                        <path
                                            class="text-[#0b8043]"
                                            stroke="currentColor"
                                            stroke-width="4.5"
                                            stroke-dasharray="67, 100"
                                            stroke-linecap="round"
                                            fill="none"
                                            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                        />
                                    </svg>
                                    <div
                                        class="absolute inset-0 flex items-center justify-center"
                                    >
                                        <span
                                            class="font-display text-base font-bold text-[#191919]"
                                        >
                                            67%
                                        </span>
                                    </div>
                                </div>

                                <div>
                                    <p class="text-sm font-bold text-[#191919]">
                                        Piring hari ini
                                    </p>
                                    <p
                                        class="mt-0.5 text-xs font-medium text-[#5e5e5e]"
                                    >
                                        1.240 dari 1.850 kkal
                                    </p>
                                </div>
                            </div>

                            <!-- Skeleton Log Entries -->
                            <div class="mt-4 space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="h-6 w-6 rounded-full bg-[#f4f3f0]"
                                        ></div>
                                        <div
                                            class="h-2 w-24 rounded-full bg-[#f4f3f0]"
                                        ></div>
                                    </div>
                                    <div
                                        class="h-2 w-8 rounded-full bg-[#f4f3f0]"
                                    ></div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="h-6 w-6 rounded-full bg-[#f4f3f0]"
                                        ></div>
                                        <div
                                            class="h-2 w-16 rounded-full bg-[#f4f3f0]"
                                        ></div>
                                    </div>
                                    <div
                                        class="h-2 w-8 rounded-full bg-[#f4f3f0]"
                                    ></div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="h-6 w-6 rounded-full bg-[#f4f3f0]"
                                        ></div>
                                        <div
                                            class="h-2 w-20 rounded-full bg-[#f4f3f0]"
                                        ></div>
                                    </div>
                                    <div
                                        class="h-2 w-8 rounded-full bg-[#f4f3f0]"
                                    ></div>
                                </div>
                            </div>
                        </div>

                        <!-- Floating Toast: Makan siang tercatat -->
                        <div
                            class="absolute right-1 -bottom-3 flex items-center gap-1.5 rounded-full bg-[#191919] px-3.5 py-1.5 text-xs font-semibold text-white shadow-xl"
                        >
                            <CheckCircle2
                                class="h-3.5 w-3.5 fill-[#0b8043] stroke-white text-[#0b8043]"
                            />
                            <span>Makan siang tercatat</span>
                        </div>
                    </div>

                    <!-- Slide 1: Pindai -->
                    <div
                        v-else-if="slides[currentSlide].id === 'pindai'"
                        class="relative mt-6"
                    >
                        <div
                            class="flex flex-col items-center justify-center rounded-2xl border border-[#eceae5] bg-white p-5 shadow-xs"
                        >
                            <div
                                class="relative flex h-24 w-full items-center justify-center rounded-xl bg-[#f4f3f0]"
                            >
                                <ScanLine
                                    class="h-12 w-12 stroke-[1.5] text-[#0b8043]"
                                />
                                <div
                                    class="absolute inset-x-8 top-1/2 h-0.5 bg-[#0b8043]/60 shadow-[0_0_8px_#0b8043]"
                                ></div>
                            </div>
                            <div
                                class="mt-3.5 flex w-full items-center justify-between gap-2 text-xs"
                            >
                                <span
                                    class="rounded-full bg-[#e6f6ec] px-2.5 py-1 font-semibold text-[#0b8043]"
                                >
                                    Gula Rendah
                                </span>
                                <span
                                    class="rounded-full bg-[#f4f3f0] px-2.5 py-1 font-medium text-[#5e5e5e]"
                                >
                                    Tanpa Pengawet
                                </span>
                            </div>
                        </div>

                        <div
                            class="absolute right-1 -bottom-3 flex items-center gap-1.5 rounded-full bg-[#191919] px-3.5 py-1.5 text-xs font-semibold text-white shadow-xl"
                        >
                            <CheckCircle2
                                class="h-3.5 w-3.5 fill-[#0b8043] stroke-white text-[#0b8043]"
                            />
                            <span>Label terverifikasi BPOM</span>
                        </div>
                    </div>

                    <!-- Slide 2: Keluarga -->
                    <div
                        v-else-if="slides[currentSlide].id === 'keluarga'"
                        class="relative mt-6"
                    >
                        <div
                            class="rounded-2xl border border-[#eceae5] bg-white p-4 shadow-xs"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-[#191919]"
                                    >Target Bersama</span
                                >
                                <span
                                    class="text-xs font-semibold text-[#0b8043]"
                                    >4 dari 4 aktif</span
                                >
                            </div>
                            <div class="mt-3 flex items-center gap-1.5">
                                <span
                                    class="flex h-7 w-7 items-center justify-center rounded-full bg-[#0b8043] text-xs font-bold text-white"
                                    >BS</span
                                >
                                <span
                                    class="flex h-7 w-7 items-center justify-center rounded-full bg-[#0b8043] text-xs font-bold text-white"
                                    >RS</span
                                >
                                <span
                                    class="flex h-7 w-7 items-center justify-center rounded-full bg-[#0b8043] text-xs font-bold text-white"
                                    >RK</span
                                >
                                <span
                                    class="flex h-7 w-7 items-center justify-center rounded-full bg-[#0b8043] text-xs font-bold text-white"
                                    >DT</span
                                >
                            </div>
                            <div class="mt-4">
                                <div
                                    class="h-2 w-full overflow-hidden rounded-full bg-[#eceae5]"
                                >
                                    <div
                                        class="h-full w-full rounded-full bg-[#0b8043]"
                                    ></div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="absolute right-1 -bottom-3 flex items-center gap-1.5 rounded-full bg-[#191919] px-3.5 py-1.5 text-xs font-semibold text-white shadow-xl"
                        >
                            <span>🥣 5 hari berturut-turut</span>
                        </div>
                    </div>
                </div>

                <!-- Right peek card edge (matching Figma) -->
                <div
                    class="pointer-events-none absolute top-6 -right-16 bottom-6 hidden w-20 rounded-3xl bg-white/20 backdrop-blur-xs transition-all xl:block"
                ></div>
            </div>

            <!-- Carousel Indicator Dots (matching Figma node 10:724) -->
            <div class="mt-8 flex items-center justify-center gap-2">
                <button
                    v-for="(slide, index) in slides"
                    :key="slide.id"
                    type="button"
                    @click="setSlide(index)"
                    :aria-label="`Slide ${slide.title}`"
                    class="transition-all duration-300 focus:outline-none"
                    :class="[
                        currentSlide === index
                            ? 'h-2 w-6 rounded-full bg-[#053d20]'
                            : 'h-2 w-2 rounded-full bg-white/40 hover:bg-white/80',
                    ]"
                ></button>
            </div>
        </div>

        <!-- 3. Bottom Mascot & Tagline (matching Figma node 10:729) -->
        <div class="relative z-10 flex items-center gap-3.5 pt-4">
            <img
                src="/assets/piri-mascot.png"
                alt="Piri - Maskot CarePlate"
                class="h-14 w-14 shrink-0 rounded-full select-none"
                loading="eager"
            />
            <p class="text-sm leading-snug font-medium text-white/95">
                Catat bersama, lihat seluruh keluarga mencatat minggu ini.
            </p>
        </div>
    </aside>
</template>
