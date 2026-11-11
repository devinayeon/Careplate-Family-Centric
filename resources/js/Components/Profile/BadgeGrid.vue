<script lang="ts">
export interface Badge {
    key: string;
    judul: string;
    deskripsi: string;
    terbuka: boolean;
    tanggal: string | null;
    progres: { sekarang: number; target: number; satuan: string } | null;
}
</script>

<script setup lang="ts">
import type { Component } from 'vue';
import {
    Award,
    Coffee,
    Database,
    Flame,
    Footprints,
    Ruler,
    Soup,
    Target,
    Users,
} from 'lucide-vue-next';

const props = defineProps<{
    lencana: { terbuka: number; total: number; daftar: Badge[] };
    rekor: number;
}>();

const icons: Record<string, Component> = {
    langkah_pertama: Footprints,
    kenal_tubuhku: Ruler,
    tahu_kebutuhanku: Target,
    satu_meja: Users,
    rajin_mencatat_7: Coffee,
    rajin_mencatat_30: Soup,
    penyumbang_data: Database,
};

const iconFor = (key: string): Component => icons[key] ?? Award;

function caption(badge: Badge): string {
    if (badge.terbuka) return badge.tanggal ?? 'Terbuka';
    if (badge.progres) {
        const p = badge.progres;
        return `${p.sekarang} dari ${p.target} ${p.satuan}`;
    }
    return badge.deskripsi;
}
</script>

<template>
    <section
        class="rounded-3xl bg-white p-6 shadow-[0_1px_2px_rgba(30,35,33,0.06),0_4px_12px_rgba(30,35,33,0.06)] dark:bg-[#1c1c1c]"
        aria-labelledby="badge-title"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2
                    id="badge-title"
                    class="font-display text-2xl font-bold text-[#191919] dark:text-[#f0efea]"
                >
                    Lencana
                </h2>
                <p class="mt-1 text-base text-[#5e5e5e] dark:text-[#a3a3a3]">
                    {{ props.lencana.terbuka }} dari
                    {{ props.lencana.total }} terbuka
                </p>
            </div>
            <p
                v-if="props.rekor > 0"
                class="inline-flex items-center gap-2 rounded-full border border-[#eceae5] bg-white px-4 py-2 text-sm font-semibold text-[#191919] dark:border-[#2e2e2e] dark:bg-[#1c1c1c] dark:text-[#f0efea]"
            >
                <Flame
                    class="h-4 w-4 text-[#e8590c] dark:text-orange-400"
                    aria-hidden="true"
                />
                Rekor terbaik {{ props.rekor }} hari
            </p>
        </div>

        <ul
            v-if="props.lencana.daftar.length"
            class="mt-6 grid grid-cols-2 gap-x-3 gap-y-6 sm:grid-cols-3 xl:grid-cols-4"
        >
            <li
                v-for="badge in props.lencana.daftar"
                :key="badge.key"
                class="flex flex-col items-center text-center"
                :title="badge.deskripsi"
            >
                <span
                    class="flex h-20 w-20 items-center justify-center rounded-full sm:h-24 sm:w-24"
                    :class="
                        badge.terbuka
                            ? 'bg-[#e4f6ea] ring-4 ring-[#ffc933] ring-inset dark:bg-[#17321f]'
                            : 'border-2 border-dashed border-[#d6d3cc] bg-[#f4f3f0] dark:border-[#3d3d3d] dark:bg-[#262626]'
                    "
                    aria-hidden="true"
                >
                    <component
                        :is="iconFor(badge.key)"
                        class="h-8 w-8 sm:h-9 sm:w-9"
                        :class="
                            badge.terbuka
                                ? 'text-[#191919] dark:text-[#f0efea]'
                                : 'text-[#5e5e5e] dark:text-[#a3a3a3]'
                        "
                    />
                </span>
                <span
                    class="mt-3 text-sm font-semibold"
                    :class="
                        badge.terbuka
                            ? 'text-[#191919] dark:text-[#f0efea]'
                            : 'text-[#5e5e5e] dark:text-[#a3a3a3]'
                    "
                >
                    {{ badge.judul }}
                    <span class="sr-only">
                        {{ badge.terbuka ? ', terbuka' : ', belum terbuka' }}
                    </span>
                </span>
                <span class="mt-0.5 text-sm text-[#5e5e5e] dark:text-[#a3a3a3]">
                    {{ caption(badge) }}
                </span>
            </li>
        </ul>
        <p v-else class="mt-6 text-base text-[#5e5e5e] dark:text-[#a3a3a3]">
            Belum ada lencana.
        </p>
    </section>
</template>
