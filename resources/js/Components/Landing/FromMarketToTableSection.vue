<script setup lang="ts">
import { ChevronRight, Check } from 'lucide-vue-next';

const scenes = [
    {
        id: 'scene-makanan',
        image: '/assets/from-market-to-table-first.png',
        badge: 'Data Makanan',
        title: 'Makanan rumahan, nilai gizinya jelas',
        description: 'Tempe, sayur asem, sampai soto ayam, diverifikasi Admin.',
        linkText: 'Buka Data Makanan',
        linkHref: '/register',
        cardBg: 'bg-[#e6f6ec]',
        badgeBg: 'bg-white/90 text-[#0b7a46]',
        linkHover: 'hover:text-[#086637]',
    },
    {
        id: 'scene-konsultasi',
        image: '/assets/from-market-to-table-second.png',
        badge: 'Konsultasi',
        title: 'Tanya Ahli Gizi lewat chat atau video',
        description:
            'Bagikan catatan 7 hari kalau mau, lalu jadwalkan meeting.',
        linkText: 'Lihat Ahli Gizi',
        linkHref: '/register',
        cardBg: 'bg-[#eee9ff]',
        badgeBg: 'bg-white/90 text-[#6c4fd8]',
        linkHover: 'hover:text-[#5538b8]',
    },
    {
        id: 'scene-bacaan',
        image: '/assets/from-market-to-table-third.png',
        badge: 'Artikel',
        title: 'Ide bekal dan menu yang mudah',
        description: 'Bacaan 3 sampai 6 menit, ditulis untuk dapur keluarga.',
        linkText: 'Baca artikel',
        linkHref: '#bacaan',
        cardBg: 'bg-[#fff4cc]',
        badgeBg: 'bg-white/90 text-[#b45309]',
        linkHover: 'hover:text-[#92400e]',
    },
];
</script>

<template>
    <section id="ahli-gizi" class="bg-white py-24">
        <div class="mx-auto max-w-[1440px] px-4 sm:px-6 lg:px-8">
            <!-- Section Header -->
            <div class="mx-auto mb-16 max-w-3xl text-center">
                <h2
                    class="font-display text-4xl font-bold tracking-tight text-[#191919] sm:text-5xl lg:text-[54px]"
                >
                    Dari pasar sampai meja makan
                </h2>
            </div>

            <!-- 3 Scene Cards Grid -->
            <div
                class="mx-auto grid max-w-6xl grid-cols-1 gap-8 md:grid-cols-3"
            >
                <div
                    v-for="scene in scenes"
                    :key="scene.id"
                    class="flex flex-col justify-between rounded-[28px] p-5 transition-all duration-300 hover:-translate-y-1 hover:shadow-lg sm:p-6"
                    :class="scene.cardBg"
                >
                    <!-- Top Image in Rounded Frame -->
                    <div>
                        <div
                            class="mb-5 h-60 w-full overflow-hidden rounded-2xl bg-white shadow-xs"
                        >
                            <img
                                :src="scene.image"
                                :alt="scene.title"
                                class="pointer-events-none h-full w-full object-cover select-none"
                                loading="lazy"
                            />
                        </div>

                        <!-- Pill Badge with Checkmark -->
                        <div
                            class="mb-3 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold shadow-2xs"
                            :class="scene.badgeBg"
                        >
                            <Check class="h-3.5 w-3.5 stroke-[3]" />
                            <span>{{ scene.badge }}</span>
                        </div>

                        <!-- Heading & Description -->
                        <h3
                            class="mb-2 font-display text-xl leading-snug font-bold text-[#191919] sm:text-2xl"
                        >
                            {{ scene.title }}
                        </h3>
                        <p
                            class="mb-6 text-sm leading-relaxed font-medium text-[#5e5e5e]"
                        >
                            {{ scene.description }}
                        </p>
                    </div>

                    <!-- Bottom Link with Chevron -->
                    <div class="pt-2">
                        <a
                            :href="scene.linkHref"
                            class="group inline-flex items-center gap-1.5 text-sm font-bold text-[#0b8043] transition-colors"
                            :class="scene.linkHover"
                        >
                            <span>{{ scene.linkText }}</span>
                            <ChevronRight
                                class="h-4 w-4 transition-transform group-hover:translate-x-1"
                            />
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
