<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    NotepadText,
    Scan,
    MessageCircle,
    Scale,
    Target,
    Soup,
    BookOpen,
    Stethoscope,
    ChevronRight,
} from 'lucide-vue-next';

interface Props {
    user?: any;
}

defineProps<Props>();

const tiles = [
    {
        id: 'catatan-makanan',
        title: 'Catatan Makanan',
        icon: NotepadText,
        boxBg: 'bg-[#e4f6ea]',
        boxColor: 'text-[#0b8043]',
        href: '/register',
    },
    {
        id: 'pindai-kemasan',
        title: 'Pindai Kemasan',
        icon: Scan,
        boxBg: 'bg-[#fff4cc]',
        boxColor: 'text-[#b45309]',
        href: '/register',
    },
    {
        id: 'chat-gizi',
        title: 'Chat Gizi',
        icon: MessageCircle,
        boxBg: 'bg-[#ffece5]',
        boxColor: 'text-[#eb4416]',
        href: '/register',
    },
    {
        id: 'kalkulator-bmi',
        title: 'Kalkulator BMI',
        icon: Scale,
        boxBg: 'bg-[#eee9ff]',
        boxColor: 'text-[#6c4fd8]',
        href: '/register',
    },
    {
        id: 'kebutuhan-kalori',
        title: 'Kebutuhan Kalori',
        icon: Target,
        boxBg: 'bg-[#e4f6ea]',
        boxColor: 'text-[#0b8043]',
        href: '/register',
    },
    {
        id: 'data-makanan',
        title: 'Data Makanan',
        icon: Soup,
        boxBg: 'bg-[#fff4cc]',
        boxColor: 'text-[#b45309]',
        href: '/register',
    },
    {
        id: 'artikel',
        title: 'Artikel',
        icon: BookOpen,
        boxBg: 'bg-[#ffece5]',
        boxColor: 'text-[#eb4416]',
        href: '#bacaan',
    },
    {
        id: 'konsultasi-ahli-gizi',
        title: 'Konsultasi Ahli Gizi',
        icon: Stethoscope,
        boxBg: 'bg-[#eee9ff]',
        boxColor: 'text-[#6c4fd8]',
        href: '/register',
    },
];
</script>

<template>
    <section id="fitur" class="relative bg-white py-24">
        <div class="mx-auto max-w-[1440px] px-4 sm:px-6 lg:px-8">
            <div
                class="grid grid-cols-1 items-center gap-12 lg:grid-cols-12 lg:gap-16"
            >
                <!-- Left Column: Copy & Action -->
                <div class="space-y-6 lg:col-span-5">
                    <h2
                        class="font-display text-4xl leading-[1.15] font-bold tracking-tight text-[#191919] sm:text-5xl lg:text-[54px]"
                    >
                        Semua kebutuhan gizi, satu aplikasi
                    </h2>
                    <p
                        class="max-w-lg text-base leading-relaxed text-[#5e5e5e] sm:text-lg"
                    >
                        Dari sarapan sampai konsultasi dengan Ahli Gizi
                        terdaftar. Semua fitur terbuka untuk setiap anggota
                        keluarga.
                    </p>
                    <div class="pt-2">
                        <Link
                            :href="
                                user ? route('dashboard') : route('register')
                            "
                            class="inline-flex items-center justify-center rounded-full bg-[#0b8043] px-8 py-3.5 text-sm font-bold text-white shadow-sm transition-all hover:scale-105 hover:bg-[#086637] hover:shadow sm:text-base"
                        >
                            {{ user ? 'Buka Dashboard' : 'Buat Akun Keluarga' }}
                        </Link>
                    </div>
                </div>

                <!-- Right Column: 8 Feature Tiles (2 cols x 4 rows) with Green Border & White Card Background -->
                <div class="lg:col-span-7">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <a
                            v-for="tile in tiles"
                            :key="tile.id"
                            :href="tile.href"
                            class="group flex items-center justify-between rounded-2xl border-[1.5px] border-[#0b8043] bg-white p-3.5 shadow-xs transition-all duration-200 hover:bg-[#e4f6ea]/30 hover:shadow-md sm:p-4"
                        >
                            <div class="flex items-center gap-3.5">
                                <div
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl transition-transform group-hover:scale-105"
                                    :class="[tile.boxBg, tile.boxColor]"
                                >
                                    <component
                                        :is="tile.icon"
                                        class="h-5 w-5"
                                    />
                                </div>
                                <span
                                    class="text-sm font-bold text-[#191919] transition-colors group-hover:text-[#0b8043] sm:text-base"
                                >
                                    {{ tile.title }}
                                </span>
                            </div>
                            <ChevronRight
                                class="h-4 w-4 text-[#5e5e5e] transition-all group-hover:translate-x-1 group-hover:text-[#0b8043]"
                            />
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
