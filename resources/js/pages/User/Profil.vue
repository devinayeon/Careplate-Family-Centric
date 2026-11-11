<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { LogOut, UserCircle } from 'lucide-vue-next';
import UserLayout from '@/Layouts/UserLayout.vue';
import IdentityCard from '@/Components/Profile/IdentityCard.vue';
import ThemeCard from '@/Components/Profile/ThemeCard.vue';
import BadgeGrid, { type Badge } from '@/Components/Profile/BadgeGrid.vue';
import AccountSettings from '@/Components/Profile/AccountSettings.vue';

defineProps<{
    profil: {
        name: string;
        email: string;
        peran: string | null;
        usia: number | null;
        keluarga: string | null;
    };
    streak: { saat_ini: number; terbaik: number };
    lencana: { terbuka: number; total: number; daftar: Badge[] };
    mustVerifyEmail?: boolean;
    status?: string | null;
}>();

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <Head title="Profil" />
    <UserLayout>
        <div class="flex flex-col gap-6">
            <header
                class="flex items-center justify-between gap-6 rounded-3xl bg-[#fff4cc] px-7 py-6 dark:bg-amber-950/40"
            >
                <h1
                    class="font-display text-3xl font-extrabold text-[#191919] dark:text-[#f0efea]"
                >
                    Profil
                </h1>
                <div class="flex shrink-0 items-center gap-6">
                    <button
                        type="button"
                        class="inline-flex h-11 items-center gap-2 rounded-full border-2 border-[#191919] bg-white px-5 text-sm font-semibold text-[#191919] hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919] dark:border-[#f0efea] dark:bg-[#1c1c1c] dark:text-[#f0efea] dark:hover:bg-[#262626] dark:focus-visible:outline-[#f0efea]"
                        @click="logout"
                    >
                        <LogOut class="h-4 w-4" aria-hidden="true" /> Keluar
                    </button>
                    <UserCircle
                        class="hidden h-16 w-16 text-[#191919] sm:block dark:text-[#f0efea]"
                        aria-hidden="true"
                    />
                </div>
            </header>

            <div class="grid items-start gap-6 lg:grid-cols-[22rem_1fr]">
                <div class="flex flex-col gap-6">
                    <IdentityCard :profil="profil" />
                    <ThemeCard />
                </div>
                <BadgeGrid :lencana="lencana" :rekor="streak.terbaik" />
            </div>

            <AccountSettings :name="profil.name" :email="profil.email" />
        </div>
    </UserLayout>
</template>
