<script setup lang="ts">
import { ref } from 'vue';
import { habitCards } from '../../data/landingData';
import { ChevronLeft, ChevronRight, ArrowRight, Info } from 'lucide-vue-next';

const infoItems = [
    'Nilai gizi diverifikasi dari Tabel Komposisi Pangan Indonesia (TKPI - Kemenkes).',
    'Pantau kecukupan makronutrisi harian: karbohidrat, protein, lemak, dan serat.',
    'Panduan porsi "Isi Piringku" disesuaikan otomatis untuk anak dan orang dewasa.',
];
const currentInfoIndex = ref(0);

const nextInfo = () => {
    currentInfoIndex.value = (currentInfoIndex.value + 1) % infoItems.length;
};

const prevInfo = () => {
    currentInfoIndex.value =
        (currentInfoIndex.value - 1 + infoItems.length) % infoItems.length;
};

const getFeatureImage = (illustrationType: string) => {
    switch (illustrationType) {
        case 'record-meal':
            return '/assets/feature-first.png';
        case 'scan-label':
            return '/assets/feature-second.png';
        case 'cook-together':
            return '/assets/feature-third.png';
        default:
            return '/assets/feature-first.png';
    }
};
</script>

<template>
    <section id="kebiasaan" class="relative overflow-hidden bg-[#fdf8e6] py-20">
        <div class="mx-auto max-w-[1440px] px-4 sm:px-6 lg:px-8">
            <!-- Section Header -->
            <div class="mx-auto mb-14 max-w-3xl text-center">
                <h2
                    class="font-display text-3xl leading-tight font-bold tracking-tight text-[#191919] sm:text-5xl lg:text-[52px]"
                >
                    Kebiasaan kecil yang mengisi piring keluarga
                </h2>
            </div>

            <!-- Cards Container with Side Navigation Arrows -->
            <div
                class="relative mx-auto flex max-w-6xl items-center justify-center"
            >
                <!-- Side Prev Button -->
                <button
                    class="absolute -left-16 z-20 hidden h-12 w-12 items-center justify-center rounded-full border border-[#eceae5] bg-white text-[#191919] shadow-md transition-transform hover:scale-105 hover:bg-[#f4f3f0] xl:flex"
                    aria-label="Sebelumnya"
                >
                    <ChevronLeft class="h-5 w-5" />
                </button>

                <!-- 3 Feature Cards Grid -->
                <div
                    class="grid w-full grid-cols-1 gap-6 md:grid-cols-3 lg:gap-8"
                >
                    <div
                        v-for="card in habitCards"
                        :key="card.id"
                        class="flex min-h-[540px] flex-col justify-between overflow-hidden rounded-[32px] p-7 shadow-lg transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl sm:p-8"
                        :class="card.bgColor"
                    >
                        <!-- Text Details -->
                        <div>
                            <h3
                                class="mb-2.5 font-display text-2xl leading-snug font-bold text-white sm:text-3xl"
                            >
                                {{ card.title }}
                            </h3>
                            <p
                                class="mb-6 text-sm leading-relaxed font-medium text-white/90 sm:text-base"
                            >
                                {{ card.description }}
                            </p>
                            <!-- Link with White Circle Arrow -->
                            <a
                                :href="card.actionHref"
                                class="group inline-flex items-center gap-2.5 text-sm font-bold text-white"
                            >
                                <span class="hover:underline">{{
                                    card.actionText
                                }}</span>
                                <span
                                    class="flex h-6 w-6 items-center justify-center rounded-full bg-white text-[#191919] shadow-xs transition-transform group-hover:scale-110"
                                >
                                    <ChevronRight
                                        class="h-3.5 w-3.5 stroke-[3]"
                                    />
                                </span>
                            </a>
                        </div>

                        <!-- Arch Window Illustration Image -->
                        <div
                            class="relative mt-6 flex h-64 w-full items-end justify-center overflow-hidden rounded-t-full border-x border-t border-white/30 bg-white/20 backdrop-blur-xs"
                        >
                            <img
                                :src="getFeatureImage(card.illustrationType)"
                                :alt="card.title"
                                class="pointer-events-none h-full w-full object-cover object-bottom select-none"
                                loading="lazy"
                            />
                        </div>
                    </div>
                </div>

                <!-- Side Next Button -->
                <button
                    class="absolute -right-16 z-20 hidden h-12 w-12 items-center justify-center rounded-full border border-[#eceae5] bg-white text-[#191919] shadow-md transition-transform hover:scale-105 hover:bg-[#f4f3f0] xl:flex"
                    aria-label="Berikutnya"
                >
                    <ChevronRight class="h-5 w-5" />
                </button>
            </div>

            <!-- Pagination Dots Indicator -->
            <div class="mt-10 flex items-center justify-center gap-2">
                <span class="h-2.5 w-8 rounded-full bg-[#0b8043]"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-[#d6d3cc]"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-[#d6d3cc]"></span>
            </div>

            <!-- Info Bar Ticker -->
            <div
                class="mx-auto mt-12 flex max-w-4xl items-center justify-between gap-4 rounded-full border border-[#eceae5] bg-white px-5 py-3 shadow-xs"
            >
                <div class="flex items-center gap-3 overflow-hidden">
                    <span
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-[#6c4fd8] px-3 py-1 text-xs font-bold text-white shadow-xs"
                    >
                        <Info class="h-3.5 w-3.5" />
                        <span>Info</span>
                    </span>
                    <p
                        class="truncate text-xs font-semibold text-[#191919] sm:text-sm"
                    >
                        {{ infoItems[currentInfoIndex] }}
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <button
                        @click="prevInfo"
                        class="flex h-7 w-7 items-center justify-center rounded-full border border-[#eceae5] text-[#5e5e5e] transition-colors hover:bg-[#f4f3f0] hover:text-[#191919]"
                        aria-label="Previous info"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <button
                        @click="nextInfo"
                        class="flex h-7 w-7 items-center justify-center rounded-full border border-[#eceae5] text-[#5e5e5e] transition-colors hover:bg-[#f4f3f0] hover:text-[#191919]"
                        aria-label="Next info"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>
