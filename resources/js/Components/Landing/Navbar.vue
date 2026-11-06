<script setup lang="ts">
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { navMenuItems } from '../../data/landingData';
import { Menu, X, UtensilsCrossed } from 'lucide-vue-next';

interface Props {
    canLogin?: boolean;
    canRegister?: boolean;
    user?: any;
}

defineProps<Props>();

const mobileMenuOpen = ref(false);
</script>

<template>
    <header class="sticky top-0 z-50 w-full transition-all duration-300">
        <div class="mx-auto max-w-[1440px] px-4 pt-4 sm:px-6 lg:px-8">
            <nav
                class="flex items-center justify-between rounded-4xl border border-[#eceae5] bg-white/90 px-6 py-3 shadow-sm backdrop-blur-md"
            >
                <!-- Logo -->
                <a href="#" class="group flex items-center">
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-full text-white shadow-sm transition-transform group-hover:scale-105"
                    >
                        <img src="../../../../public/assets/logo.png" alt="" />
                    </div>
                    <span
                        class="font-display text-2xl font-bold tracking-tight text-[#191919]"
                    >
                        are<span class="text-[#0b8043]">Plate</span>
                    </span>
                </a>

                <!-- Desktop Nav Links -->
                <div class="hidden items-center gap-1 md:flex lg:gap-2">
                    <a
                        v-for="item in navMenuItems"
                        :key="item.label"
                        :href="item.href"
                        class="rounded-xl px-4 py-2 text-sm font-semibold text-[#5e5e5e] transition-colors hover:bg-[#e4f6ea]/50 hover:text-[#0b8043]"
                    >
                        {{ item.label }}
                    </a>
                </div>

                <!-- Desktop CTA Actions -->
                <div class="hidden items-center gap-3 md:flex">
                    <template v-if="user">
                        <Link
                            :href="route('dashboard')"
                            class="inline-flex items-center justify-center rounded-full bg-[#0b8043] px-5 py-2.5 text-sm font-bold text-white shadow-sm transition-all hover:bg-[#086637] hover:shadow"
                        >
                            Buka Dashboard
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            v-if="canLogin"
                            :href="route('login')"
                            class="px-4 py-2 text-sm font-bold text-[#191919] transition-colors hover:text-[#0b8043]"
                        >
                            Masuk
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="inline-flex items-center justify-center rounded-full bg-[#0b8043] px-5 py-2.5 text-sm font-bold text-white shadow-sm transition-all hover:scale-[1.02] hover:bg-[#086637] hover:shadow"
                        >
                            Buat Akun
                        </Link>
                    </template>
                </div>

                <!-- Mobile Menu Hamburger -->
                <div class="flex items-center md:hidden">
                    <button
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        class="p-2 text-[#5e5e5e] hover:text-[#191919] focus:outline-none"
                        aria-label="Toggle Navigation"
                    >
                        <Menu v-if="!mobileMenuOpen" class="h-6 w-6" />
                        <X v-else class="h-6 w-6" />
                    </button>
                </div>
            </nav>

            <!-- Mobile Drawer -->
            <transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="transform -translate-y-4 opacity-0"
                enter-to-class="transform translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="transform translate-y-0 opacity-100"
                leave-to-class="transform -translate-y-4 opacity-0"
            >
                <div
                    v-if="mobileMenuOpen"
                    class="mt-2 space-y-4 rounded-2xl border border-[#eceae5] bg-white p-5 shadow-lg md:hidden"
                >
                    <div class="flex flex-col space-y-2">
                        <a
                            v-for="item in navMenuItems"
                            :key="item.label"
                            :href="item.href"
                            @click="mobileMenuOpen = false"
                            class="rounded-lg px-4 py-2 text-sm font-semibold text-[#191919] hover:bg-[#e4f6ea]/40 hover:text-[#0b8043]"
                        >
                            {{ item.label }}
                        </a>
                    </div>
                    <div
                        class="flex flex-col gap-2 border-t border-[#eceae5] pt-4"
                    >
                        <template v-if="user">
                            <Link
                                :href="route('dashboard')"
                                class="w-full rounded-full bg-[#0b8043] py-2.5 text-center text-sm font-bold text-white"
                            >
                                Buka Dashboard
                            </Link>
                        </template>
                        <template v-else>
                            <Link
                                v-if="canLogin"
                                :href="route('login')"
                                class="w-full py-2 text-center text-sm font-bold text-[#191919]"
                            >
                                Masuk
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="w-full rounded-full bg-[#0b8043] py-2.5 text-center text-sm font-bold text-white"
                            >
                                Buat Akun Keluarga
                            </Link>
                        </template>
                    </div>
                </div>
            </transition>
        </div>
    </header>
</template>
