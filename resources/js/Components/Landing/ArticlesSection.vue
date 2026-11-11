<script setup lang="ts">
import { ref } from 'vue';
import { articleCategories } from '../../data/landingData';
import {
    ChevronLeft,
    ChevronRight,
    ArrowRight,
    Sparkles,
    BookOpen,
} from 'lucide-vue-next';

const activeFilter = ref('semua');
</script>

<template>
    <section id="bacaan" class="bg-white py-20">
        <div class="mx-auto max-w-[1440px] px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div
                class="mb-14 flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <span
                        class="mb-2 inline-flex items-center gap-1.5 rounded-full bg-[#fff4cc] px-3 py-1 text-xs font-bold text-[#c7370c]"
                    >
                        Edukasi Nutrisi
                    </span>
                    <h2
                        class="font-display text-3xl font-bold tracking-tight text-[#191919] sm:text-5xl"
                    >
                        Bacaan untuk keluarga
                    </h2>
                </div>
                <a
                    href="#bacaan"
                    class="group inline-flex items-center gap-2 text-sm font-bold text-[#0b8043] transition-colors hover:text-[#086637]"
                >
                    <span>Lihat semua artikel</span>
                    <ArrowRight
                        class="h-4 w-4 transition-transform group-hover:translate-x-1.5"
                    />
                </a>
            </div>

            <!-- Category Circles Row -->
            <div class="relative">
                <div
                    class="flex scrollbar-none items-center justify-center gap-4 overflow-x-auto px-2 py-6 sm:gap-6 lg:gap-10"
                >
                    <div
                        v-for="cat in articleCategories"
                        :key="cat.id"
                        class="group flex cursor-pointer flex-col items-center text-center transition-all duration-300"
                        :class="
                            cat.isFeatured
                                ? 'z-10 scale-105 sm:scale-110'
                                : 'hover:scale-105'
                        "
                    >
                        <!-- Photo / Visual Circle -->
                        <div
                            class="relative rounded-full p-1.5 shadow-md transition-all duration-300 group-hover:shadow-xl"
                            :class="[
                                cat.isFeatured
                                    ? 'h-36 w-36 bg-gradient-to-tr from-[#eb4416] via-[#ffc933] to-[#0b8043] ring-4 ring-[#eb4416]/20 sm:h-48 sm:w-48'
                                    : 'h-28 w-28 bg-[#eceae5] group-hover:bg-[#0b8043] sm:h-36 sm:w-36',
                            ]"
                        >
                            <div
                                class="relative flex h-full w-full flex-col items-center justify-center overflow-hidden rounded-full bg-gradient-to-br p-3 text-white"
                                :class="cat.imagePlaceholderBg"
                            >
                                <BookOpen
                                    v-if="!cat.isFeatured"
                                    class="h-8 w-8 opacity-90 drop-shadow"
                                />
                                <div v-else class="p-2 text-center">
                                    <Sparkles
                                        class="mx-auto mb-1 h-6 w-6 animate-pulse"
                                    />
                                    <span
                                        class="block text-[11px] font-bold tracking-wider uppercase sm:text-xs"
                                        >Spesial</span
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Name & Tag -->
                        <div class="mt-4 max-w-[160px]">
                            <h3
                                class="font-display font-bold text-[#191919] transition-colors group-hover:text-[#0b8043]"
                                :class="
                                    cat.isFeatured
                                        ? 'text-lg text-[#eb4416] sm:text-xl'
                                        : 'text-sm sm:text-base'
                                "
                            >
                                {{ cat.name }}
                            </h3>
                            <p
                                class="mt-0.5 line-clamp-1 text-xs font-medium text-[#5e5e5e]"
                            >
                                {{ cat.tag }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
