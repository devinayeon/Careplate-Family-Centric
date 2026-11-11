<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { PageProps } from '../types';

import HeroSection from '../Components/Landing/HeroSection.vue';
import HabitsSection from '../Components/Landing/HabitsSection.vue';
import FeaturesGridSection from '../Components/Landing/FeaturesGridSection.vue';
import FromMarketToTableSection from '../Components/Landing/FromMarketToTableSection.vue';
import FamilyTableSection from '../Components/Landing/FamilyTableSection.vue';
import ArticlesSection from '../Components/Landing/ArticlesSection.vue';
import CtaBannerSection from '../Components/Landing/CtaBannerSection.vue';
import FooterSection from '../Components/Landing/FooterSection.vue';

interface Props {
    canLogin?: boolean;
    canRegister?: boolean;
    laravelVersion?: string;
    phpVersion?: string;
}

defineProps<Props>();

const page = usePage<PageProps>();
const currentUser = computed(() => page.props.auth?.user);
</script>

<template>
    <Head title="CarePlate - Makan Sehat Sekeluarga, Mulai dari Satu Meja" />

    <div
        class="min-h-screen bg-white font-sans text-[#191919] antialiased selection:bg-[#0b8043] selection:text-white"
    >
        <!-- 1. Hero Section (Integrated Floating Navbar, Headline, Avatars, Outlined Pill, Floating Stickers, Scenic Blend) -->
        <HeroSection
            :can-login="canLogin"
            :can-register="canRegister"
            :user="currentUser"
        />

        <!-- 2. Tiga Kebiasaan Kecil yang Mengisi Piring Keluarga (3 Colorful Cards & Info Ticker) -->
        <HabitsSection />

        <!-- 3. Semua Kebutuhan Gizi, Satu Aplikasi (8 Feature Tiles) -->
        <FeaturesGridSection :user="currentUser" />

        <!-- 4. Dari Pasar Sampai Meja Makan (3 Scene Cards) -->
        <FromMarketToTableSection />

        <!-- 5. Meja Keluarga (Weekly Table Matrix Mockup & Privacy Value Props) -->
        <FamilyTableSection :user="currentUser" />

        <!-- 6. Bacaan untuk Keluarga (Category Photo Circles) -->
        <ArticlesSection />

        <!-- 7. CTA Bukit: Siap Mengisi Piring Bareng Keluarga? -->
        <CtaBannerSection :user="currentUser" />

        <!-- 8. Deep Forest Green Footer -->
        <FooterSection />
    </div>
</template>
