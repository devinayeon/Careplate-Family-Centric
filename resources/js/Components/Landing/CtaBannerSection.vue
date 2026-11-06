<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Sparkles, ArrowRight } from 'lucide-vue-next';

interface Props {
    user?: any;
}

defineProps<Props>();
</script>

<template>
    <section
        class="relative overflow-hidden bg-gradient-to-b from-white via-[#fdf8e6] to-[#bae7cf]/60 pt-20"
    >
        <!-- Floating decorative sparkles -->
        <div
            class="pointer-events-none absolute top-16 left-[15%] text-[#ffc933] select-none"
        >
            <Sparkles class="h-8 w-8" />
        </div>
        <div
            class="pointer-events-none absolute top-24 right-[15%] text-[#0b8043]/40 select-none"
        >
            <Sparkles class="h-6 w-6" />
        </div>

        <div
            class="relative z-10 mx-auto max-w-[1440px] px-4 text-center sm:px-6 lg:px-8"
        >
            <div class="mx-auto max-w-3xl space-y-6">
                <h2
                    class="font-display text-4xl leading-tight font-bold tracking-tight text-[#191919] sm:text-6xl lg:text-[64px]"
                >
                    Siap mengisi piring bareng keluarga?
                </h2>

                <p
                    class="mx-auto max-w-xl text-base leading-relaxed text-[#5e5e5e] sm:text-xl"
                >
                    Buat akun, catat sarapan pertama, lalu ajak keluarga lewat
                    kode grup.
                </p>

                <div
                    class="flex flex-wrap items-center justify-center gap-4 pt-4"
                >
                    <Link
                        :href="user ? route('dashboard') : route('register')"
                        class="inline-flex items-center gap-2 rounded-full bg-[#0b8043] px-9 py-4 text-base font-bold text-white shadow-lg transition-all hover:scale-105 hover:bg-[#086637] hover:shadow-xl"
                    >
                        <span>{{
                            user ? 'Masuk ke Dashboard' : 'Buat Akun Keluarga'
                        }}</span>
                        <ArrowRight class="h-5 w-5" />
                    </Link>
                </div>
            </div>
        </div>

        <!-- Scenic Hill Landscape Illustration from public/assets/footer_layer_image.png (mentok kiri dan kanan edge-to-edge) -->
        <div class="relative mt-12 w-full overflow-hidden leading-none">
            <img
                src="/assets/footer_layer_image.png"
                alt="Keluarga Berjalan Sehat Menuju Perbukitan - CarePlate"
                class="pointer-events-none block h-auto w-full object-cover select-none"
                loading="lazy"
            />
        </div>
    </section>
</template>
