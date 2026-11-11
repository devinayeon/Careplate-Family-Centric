<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CheckCircle2, Plus } from 'lucide-vue-next';
import UserLayout from '@/Layouts/UserLayout.vue';
import FoodStatusBadge from '@/Components/UI/FoodStatusBadge.vue';
import { idNumber } from '@/lib/foodUi';

defineProps<{
    submissions: Array<{
        id: number;
        nama: string;
        status: string;
        energi: number;
        takaran_saji: string;
        diajukan: string | null;
        alasan_penolakan: string | null;
    }>;
}>();

const flash = computed(
    () => (usePage().props.flash as { success?: string } | undefined)?.success,
);
const date = (d: string | null) =>
    d
        ? new Date(d).toLocaleDateString('id-ID', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : '-';
const note = (s: { status: string; alasan_penolakan: string | null }) =>
    s.status === 'ditolak'
        ? (s.alasan_penolakan ?? 'Ditolak tanpa catatan.')
        : s.status === 'terverifikasi'
          ? 'Ditambahkan ke Data Makanan'
          : '-';
</script>

<template>
    <Head title="Pengajuan saya" />
    <UserLayout>
        <div class="flex flex-col gap-6">
            <header
                class="flex flex-wrap items-center justify-between gap-4 rounded-3xl bg-[#e4f6ea] px-7 py-6 dark:bg-[#17321f]"
            >
                <h1
                    class="font-display text-3xl font-extrabold text-[#191919] dark:text-[#f0efea]"
                >
                    Pengajuan saya
                </h1>
                <Link
                    :href="route('user.foods.create')"
                    class="inline-flex h-11 items-center gap-2 rounded-full bg-[#0b8043] px-5 text-sm font-semibold text-white hover:bg-[#086637] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919]"
                    ><Plus class="h-4 w-4" aria-hidden="true" /> Ajukan Data
                    Makanan</Link
                >
            </header>

            <p
                v-if="flash"
                class="flex items-center gap-2 rounded-2xl bg-[#e6f6ec] px-5 py-3 text-sm font-semibold text-[#0b7a46] dark:bg-[#17321f] dark:text-[#3fcb7a]"
                role="status"
            >
                <CheckCircle2 class="h-4 w-4" aria-hidden="true" />{{ flash }}
            </p>

            <ul
                v-if="submissions.length"
                class="flex flex-col gap-3 sm:hidden"
                aria-label="Makanan yang kamu ajukan"
            >
                <li
                    v-for="s in submissions"
                    :key="s.id"
                    class="rounded-2xl bg-white p-4 shadow-[0_1px_2px_rgba(30,35,33,0.06),0_4px_12px_rgba(30,35,33,0.06)] dark:bg-[#1c1c1c]"
                >
                    <Link
                        :href="route('user.foods.show', s.id)"
                        class="font-display text-lg font-bold text-[#191919] hover:underline focus-visible:outline-2 focus-visible:outline-[#191919] dark:text-[#f0efea]"
                        >{{ s.nama }}</Link
                    >
                    <p class="text-sm text-[#5e5e5e] dark:text-[#a3a3a3]">
                        {{ idNumber.format(s.energi) }} kkal ·
                        {{ s.takaran_saji }} · {{ date(s.diajukan) }}
                    </p>
                    <div class="mt-2">
                        <FoodStatusBadge
                            :status="s.status"
                            :label="
                                s.status === 'menunggu'
                                    ? 'Menunggu verifikasi'
                                    : undefined
                            "
                        />
                    </div>
                    <p
                        v-if="note(s) !== '-'"
                        class="mt-2 text-sm"
                        :class="
                            s.status === 'ditolak'
                                ? 'text-[#b42318] dark:text-red-300'
                                : 'text-[#191919] dark:text-[#f0efea]'
                        "
                    >
                        {{ note(s) }}
                    </p>
                </li>
            </ul>
            <section
                v-if="submissions.length"
                class="hidden overflow-x-auto rounded-3xl bg-white shadow-[0_1px_2px_rgba(30,35,33,0.06),0_4px_12px_rgba(30,35,33,0.06)] sm:block dark:bg-[#1c1c1c]"
            >
                <table class="w-full min-w-[40rem] text-left">
                    <caption class="sr-only">
                        Makanan yang kamu ajukan
                    </caption>
                    <thead
                        class="border-b border-[#eceae5] text-sm text-[#5e5e5e] dark:border-[#2e2e2e] dark:text-[#a3a3a3]"
                    >
                        <tr>
                            <th scope="col" class="px-6 py-4 font-semibold">
                                Makanan
                            </th>
                            <th scope="col" class="px-4 py-4 font-semibold">
                                Diajukan
                            </th>
                            <th scope="col" class="px-4 py-4 font-semibold">
                                Status
                            </th>
                            <th scope="col" class="px-6 py-4 font-semibold">
                                Catatan Admin
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-[#eceae5] dark:divide-[#2e2e2e]"
                    >
                        <tr v-for="s in submissions" :key="s.id">
                            <th scope="row" class="px-6 py-4 font-normal">
                                <Link
                                    :href="route('user.foods.show', s.id)"
                                    class="font-display text-lg font-bold text-[#191919] hover:underline focus-visible:outline-2 focus-visible:outline-[#191919] dark:text-[#f0efea]"
                                    >{{ s.nama }}</Link
                                ><span
                                    class="block text-sm text-[#5e5e5e] dark:text-[#a3a3a3]"
                                    >{{ idNumber.format(s.energi) }} kkal ·
                                    {{ s.takaran_saji }}</span
                                >
                            </th>
                            <td
                                class="px-4 py-4 text-base text-[#191919] dark:text-[#f0efea]"
                            >
                                {{ date(s.diajukan) }}
                            </td>
                            <td class="px-4 py-4">
                                <FoodStatusBadge
                                    :status="s.status"
                                    :label="
                                        s.status === 'menunggu'
                                            ? 'Menunggu verifikasi'
                                            : undefined
                                    "
                                />
                            </td>
                            <td
                                class="px-6 py-4 text-sm"
                                :class="
                                    s.status === 'ditolak'
                                        ? 'text-[#b42318] dark:text-red-300'
                                        : 'text-[#191919] dark:text-[#f0efea]'
                                "
                            >
                                {{ note(s) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
            <section
                v-else
                class="rounded-3xl bg-white px-6 py-14 text-center dark:bg-[#1c1c1c]"
            >
                <h2
                    class="font-display text-xl font-bold text-[#191919] dark:text-[#f0efea]"
                >
                    Belum ada pengajuan
                </h2>
                <p class="mt-2 text-sm text-[#5e5e5e] dark:text-[#a3a3a3]">
                    Makanan yang kamu ajukan muncul di sini beserta hasil
                    pemeriksaan Admin.
                </p>
            </section>

            <p
                class="flex items-center gap-2 rounded-2xl bg-[#e4f6ea] px-5 py-4 text-base text-[#191919] dark:bg-[#17321f] dark:text-[#f0efea]"
            >
                <CheckCircle2
                    class="h-5 w-5 shrink-0 text-[#0b8043] dark:text-[#3fcb7a]"
                    aria-hidden="true"
                />Pengajuan yang terverifikasi ikut dipakai Chat Gizi untuk
                menjawab.
            </p>
        </div>
    </UserLayout>
</template>
