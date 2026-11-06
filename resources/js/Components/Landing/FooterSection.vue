<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { UtensilsCrossed, Heart } from 'lucide-vue-next';
</script>

<template>
    <footer
        class="border-t border-[#0e3b24] bg-[#0e3b24] pt-16 pb-12 text-white"
    >
        <div class="mx-auto max-w-[1440px] px-4 sm:px-6 lg:px-8">
            <div
                class="grid grid-cols-1 gap-10 border-b border-white/10 pb-12 md:grid-cols-2 lg:grid-cols-5"
            >
                <!-- Brand Column -->
                <div class="space-y-4 lg:col-span-2">
                    <a href="#" class="flex items-center">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-full text-[#0b8043] shadow-sm"
                        >
                            <img src="/assets/logo.png" alt="" />
                        </div>
                        <span
                            class="font-display text-2xl font-bold tracking-tight text-white"
                        >
                            are<span class="text-[#bae7cf]">Plate</span>
                        </span>
                    </a>
                    <p
                        class="max-w-sm text-sm leading-relaxed font-medium text-white/80"
                    >
                        Catat makan bersama keluarga, dengan data gizi yang
                        diverifikasi dari Tabel Komposisi Pangan Indonesia.
                    </p>
                </div>

                <!-- Column 1: Produk -->
                <div class="space-y-3">
                    <h4
                        class="font-display text-sm font-bold tracking-wider text-white/90 uppercase"
                    >
                        Produk
                    </h4>
                    <ul class="space-y-2.5 text-sm text-white/70">
                        <li>
                            <a
                                href="#fitur"
                                class="transition-colors hover:text-white"
                                >Catatan Makanan</a
                            >
                        </li>
                        <li>
                            <a
                                href="#fitur"
                                class="transition-colors hover:text-white"
                                >Pindai Kemasan</a
                            >
                        </li>
                        <li>
                            <a
                                href="#fitur"
                                class="transition-colors hover:text-white"
                                >Chat Gizi</a
                            >
                        </li>
                        <li>
                            <a
                                href="#ahli-gizi"
                                class="transition-colors hover:text-white"
                                >Konsultasi</a
                            >
                        </li>
                    </ul>
                </div>

                <!-- Column 2: Bantuan -->
                <div class="space-y-3">
                    <h4
                        class="font-display text-sm font-bold tracking-wider text-white/90 uppercase"
                    >
                        Bantuan
                    </h4>
                    <ul class="space-y-2.5 text-sm text-white/70">
                        <li>
                            <Link
                                :href="route('login')"
                                class="transition-colors hover:text-white"
                                >Masuk</Link
                            >
                        </li>
                        <li>
                            <Link
                                :href="route('register')"
                                class="transition-colors hover:text-white"
                                >Buat Akun</Link
                            >
                        </li>
                        <li>
                            <a
                                href="mailto:bantuan@careplate.id"
                                class="transition-colors hover:text-white"
                                >bantuan@careplate.id</a
                            >
                        </li>
                    </ul>
                </div>

                <!-- Column 3: Legal -->
                <div class="space-y-3">
                    <h4
                        class="font-display text-sm font-bold tracking-wider text-white/90 uppercase"
                    >
                        Legal
                    </h4>
                    <ul class="space-y-2.5 text-sm text-white/70">
                        <li>
                            <a
                                href="#"
                                class="transition-colors hover:text-white"
                                >Syarat Layanan</a
                            >
                        </li>
                        <li>
                            <a
                                href="#"
                                class="transition-colors hover:text-white"
                                >Kebijakan Privasi</a
                            >
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Bottom Note & Copyright -->
            <div
                class="flex flex-col items-center justify-between gap-4 pt-8 text-xs text-white/60 sm:flex-row"
            >
                <p>
                    Data kesehatan diproses sesuai Undang-Undang Pelindungan
                    Data Pribadi (UU PDP).
                </p>
                <p class="flex items-center gap-1">
                    <span>© 2026 CarePlate. Dibuat</span>
                    <span>untuk keluarga Indonesia.</span>
                </p>
            </div>
        </div>
    </footer>
</template>
