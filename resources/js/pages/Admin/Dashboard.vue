<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import {
    Users,
    BookOpen,
    UtensilsCrossed,
    Users2,
    RefreshCw,
    CheckCircle2,
    Clock,
    AlertCircle,
    ArrowRight,
    XCircle,
} from 'lucide-vue-next';

const props = defineProps<{
    metrics: {
        total_pengguna: { total: string; detail: string };
        total_artikel: { total: string; detail: string };
        total_makanan: { total: string; detail: string };
        total_keluarga: { total: string; detail: string };
    };
    antrean_verifikasi: {
        jumlah: number;
        terlama_hari: number;
    };
    pengajuan_terbaru: Array<{
        id: number;
        nama: string;
        pengaju: string;
        tanggal: string;
        status: string;
    }>;
    sinkronisasi_ai: {
        ready: number;
        needs_weight: number;
        failed: number;
        failed_names: string[];
        terakhir_sinkron: string | null;
    };
}>();

const flash = computed(
    () =>
        usePage().props.flash as
            | { success?: string; error?: string }
            | undefined,
);
const retrying = ref(false);
// "Coba lagi": publish the verified foods the AI chat is missing (same single path as ai:sync-makanan).
const retrySync = () => {
    router.post(
        route('admin.ai-sync.run'),
        {},
        {
            preserveScroll: true,
            onStart: () => (retrying.value = true),
            onFinish: () => (retrying.value = false),
        },
    );
};

const refreshData = () => {
    router.reload();
};
</script>

<template>
    <Head title="Admin Dashboard" />

    <AdminLayout>
        <div class="max-w-6xl space-y-6">
            <!-- 1. Header Title & Reload Button -->
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl dark:text-[#f0efea]"
                    >
                        Dashboard
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-[#a3a3a3]">
                        Ringkasan data pengguna, katalog makanan, artikel
                        edukasi, dan status verifikasi CarePlate.
                    </p>
                </div>

                <button
                    @click="refreshData"
                    class="inline-flex cursor-pointer items-center gap-2 self-start rounded-full border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-gray-700 shadow-2xs transition-all hover:bg-gray-50 sm:self-center dark:border-[#2e2e2e] dark:bg-[#1c1c1c] dark:text-[#f0efea] dark:hover:bg-[#262626]"
                >
                    <RefreshCw
                        class="h-3.5 w-3.5 text-gray-500 dark:text-gray-400"
                    />
                    <span>Muat Ulang</span>
                </button>
            </div>

            <!-- 2. Four Metric Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Card Total Pengguna -->
                <Link
                    :href="route('admin.users')"
                    class="group block cursor-pointer rounded-3xl border border-gray-100 bg-white p-5 shadow-xs transition-all hover:border-gray-200 hover:shadow-sm dark:border-[#2e2e2e] dark:bg-[#1c1c1c] dark:hover:border-[#3d3d3d]"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <div
                            class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-[#a3a3a3]"
                        >
                            <Users
                                class="h-4 w-4 text-gray-400 transition-colors group-hover:text-emerald-600 dark:text-gray-500 dark:group-hover:text-[#3fcb7a]"
                            />
                            <span>Total Pengguna</span>
                        </div>
                        <ArrowRight
                            class="h-3.5 w-3.5 text-gray-300 transition-colors group-hover:text-emerald-600 dark:text-gray-600 dark:group-hover:text-[#3fcb7a]"
                        />
                    </div>
                    <div
                        class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-[#f0efea]"
                    >
                        {{ metrics.total_pengguna.total }}
                    </div>
                    <div
                        class="mt-2 text-[11px] font-medium text-gray-400 dark:text-[#a3a3a3]"
                    >
                        {{ metrics.total_pengguna.detail }}
                    </div>
                </Link>

                <!-- Card Total Artikel -->
                <Link
                    :href="route('admin.articles')"
                    class="group block cursor-pointer rounded-3xl border border-gray-100 bg-white p-5 shadow-xs transition-all hover:border-gray-200 hover:shadow-sm dark:border-[#2e2e2e] dark:bg-[#1c1c1c] dark:hover:border-[#3d3d3d]"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <div
                            class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-[#a3a3a3]"
                        >
                            <BookOpen
                                class="h-4 w-4 text-gray-400 transition-colors group-hover:text-emerald-600 dark:text-gray-500 dark:group-hover:text-[#3fcb7a]"
                            />
                            <span>Total Artikel</span>
                        </div>
                        <ArrowRight
                            class="h-3.5 w-3.5 text-gray-300 transition-colors group-hover:text-emerald-600 dark:text-gray-600 dark:group-hover:text-[#3fcb7a]"
                        />
                    </div>
                    <div
                        class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-[#f0efea]"
                    >
                        {{ metrics.total_artikel.total }}
                    </div>
                    <div
                        class="mt-2 text-[11px] font-medium text-gray-400 dark:text-[#a3a3a3]"
                    >
                        {{ metrics.total_artikel.detail }}
                    </div>
                </Link>

                <!-- Card Total Database Makanan -->
                <Link
                    :href="route('admin.foods')"
                    class="group block cursor-pointer rounded-3xl border border-gray-100 bg-white p-5 shadow-xs transition-all hover:border-gray-200 hover:shadow-sm dark:border-[#2e2e2e] dark:bg-[#1c1c1c] dark:hover:border-[#3d3d3d]"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <div
                            class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-[#a3a3a3]"
                        >
                            <UtensilsCrossed
                                class="h-4 w-4 text-gray-400 transition-colors group-hover:text-emerald-600 dark:text-gray-500 dark:group-hover:text-[#3fcb7a]"
                            />
                            <span>Total Database Makanan</span>
                        </div>
                        <ArrowRight
                            class="h-3.5 w-3.5 text-gray-300 transition-colors group-hover:text-emerald-600 dark:text-gray-600 dark:group-hover:text-[#3fcb7a]"
                        />
                    </div>
                    <div
                        class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-[#f0efea]"
                    >
                        {{ metrics.total_makanan.total }}
                    </div>
                    <div
                        class="mt-2 text-[11px] font-medium text-emerald-600 dark:text-[#3fcb7a]"
                    >
                        {{ metrics.total_makanan.detail }}
                    </div>
                </Link>

                <!-- Card Total Grup Keluarga -->
                <Link
                    :href="route('admin.users')"
                    class="group block cursor-pointer rounded-3xl border border-gray-100 bg-white p-5 shadow-xs transition-all hover:border-gray-200 hover:shadow-sm dark:border-[#2e2e2e] dark:bg-[#1c1c1c] dark:hover:border-[#3d3d3d]"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <div
                            class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-[#a3a3a3]"
                        >
                            <Users2
                                class="h-4 w-4 text-gray-400 transition-colors group-hover:text-emerald-600 dark:text-gray-500 dark:group-hover:text-[#3fcb7a]"
                            />
                            <span>Total Grup Keluarga</span>
                        </div>
                        <ArrowRight
                            class="h-3.5 w-3.5 text-gray-300 transition-colors group-hover:text-emerald-600 dark:text-gray-600 dark:group-hover:text-[#3fcb7a]"
                        />
                    </div>
                    <div
                        class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-[#f0efea]"
                    >
                        {{ metrics.total_keluarga.total }}
                    </div>
                    <div
                        class="mt-2 text-[11px] font-medium text-gray-400 dark:text-[#a3a3a3]"
                    >
                        {{ metrics.total_keluarga.detail }}
                    </div>
                </Link>
            </div>

            <!-- 3. Alert Action Banner: Pengajuan Makanan -->
            <div
                class="flex flex-col items-start justify-between gap-4 rounded-3xl border border-[#fed9c8] bg-[#fff1eb] p-5 transition-colors sm:flex-row sm:items-center sm:p-6 dark:border-[#542817] dark:bg-[#3a1e14]"
            >
                <div class="flex items-center gap-4">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-[#fed4c1] bg-white text-[#0f6b36] dark:border-[#542817] dark:bg-[#1c1c1c] dark:text-[#3fcb7a]"
                    >
                        <CheckCircle2 class="h-5 w-5" />
                    </div>
                    <div>
                        <div
                            class="text-xs font-medium text-gray-500 dark:text-[#a3a3a3]"
                        >
                            Pengajuan Makanan
                        </div>
                        <div
                            class="text-base font-bold text-gray-900 sm:text-lg dark:text-[#f0efea]"
                        >
                            <template v-if="antrean_verifikasi.jumlah > 0">
                                <span
                                    class="font-extrabold text-[#0f6b36] dark:text-[#3fcb7a]"
                                    >{{ antrean_verifikasi.jumlah }}</span
                                >
                                pengajuan menunggu verifikasi, terlama
                                {{ antrean_verifikasi.terlama_hari }} hari
                            </template>
                            <template v-else>
                                Tidak ada antrean pending, semua pengajuan
                                makanan telah diverifikasi
                            </template>
                        </div>
                    </div>
                </div>

                <Link
                    :href="route('admin.verifications')"
                    class="inline-flex shrink-0 cursor-pointer items-center justify-center self-stretch rounded-full bg-[#0f6b36] px-5 py-2.5 text-center text-xs font-semibold text-white shadow-xs transition-all hover:bg-[#0c592c] sm:self-auto dark:bg-[#3fcb7a] dark:text-[#0e1a13] dark:hover:bg-[#34b86c]"
                >
                    <span>Buka Antrean Verifikasi</span>
                </Link>
            </div>

            <!-- 4. Bottom Grid: Pengajuan Terbaru & Sinkronisasi Chat Gizi -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                <!-- Left: Pengajuan Terbaru (7 cols) -->
                <div
                    class="rounded-3xl border border-gray-100 bg-white p-6 shadow-xs transition-colors lg:col-span-7 dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
                >
                    <div class="mb-5 flex items-center justify-between">
                        <h2
                            class="text-base font-bold text-gray-900 dark:text-[#f0efea]"
                        >
                            Pengajuan terbaru
                        </h2>
                        <Link
                            :href="route('admin.verifications')"
                            class="text-xs font-semibold text-[#0f6b36] hover:underline dark:text-[#3fcb7a]"
                        >
                            Lihat semua
                        </Link>
                    </div>

                    <div
                        v-if="pengajuan_terbaru.length > 0"
                        class="divide-y divide-gray-100 dark:divide-[#262626]"
                    >
                        <div
                            v-for="item in pengajuan_terbaru"
                            :key="item.id"
                            class="flex items-center justify-between gap-4 py-3.5"
                        >
                            <div>
                                <div
                                    class="text-sm font-semibold text-gray-900 dark:text-[#f0efea]"
                                >
                                    {{ item.nama }}
                                </div>
                                <div
                                    class="mt-0.5 text-xs text-gray-400 dark:text-[#a3a3a3]"
                                >
                                    {{ item.pengaju }} • {{ item.tanggal }}
                                </div>
                            </div>

                            <div>
                                <span
                                    v-if="item.status === 'Menunggu'"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-amber-200/60 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700 dark:border-amber-800/50 dark:bg-amber-950/50 dark:text-amber-300"
                                >
                                    <Clock class="h-3 w-3 text-amber-500" />
                                    <span>Menunggu</span>
                                </span>
                                <span
                                    v-else-if="item.status === 'Ditolak'"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-red-200/60 bg-red-50 px-3 py-1 text-xs font-medium text-red-700 dark:border-red-800/50 dark:bg-red-950/50 dark:text-red-300"
                                >
                                    <XCircle class="h-3 w-3 text-red-500" />
                                    <span>Ditolak</span>
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200/60 bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 dark:border-emerald-800/50 dark:bg-[#17321f] dark:text-[#3fcb7a]"
                                >
                                    <CheckCircle2
                                        class="h-3 w-3 text-emerald-500"
                                    />
                                    <span>Terverifikasi</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="py-12 text-center text-xs text-gray-400 dark:text-[#a3a3a3]"
                    >
                        Belum ada riwayat pengajuan makanan.
                    </div>
                </div>

                <!-- Right: Sinkronisasi Chat Gizi (5 cols) -->
                <div
                    class="flex flex-col justify-between rounded-3xl border border-gray-100 bg-white p-6 shadow-xs transition-colors lg:col-span-5 dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
                >
                    <div>
                        <h2
                            class="mb-4 text-base font-bold text-gray-900 dark:text-[#f0efea]"
                        >
                            Sinkronisasi Chat Gizi
                        </h2>

                        <!-- Status Indicator -->
                        <div
                            class="mb-4 flex items-center gap-2 text-xs font-medium text-gray-700 dark:text-[#f0efea]"
                        >
                            <span
                                class="h-2.5 w-2.5 rounded-full"
                                :class="
                                    sinkronisasi_ai.failed === 0 &&
                                    sinkronisasi_ai.ready > 0
                                        ? 'bg-emerald-500'
                                        : 'bg-gray-300 dark:bg-[#555]'
                                "
                            ></span>
                            <span v-if="sinkronisasi_ai.ready > 0"
                                >{{ sinkronisasi_ai.ready }} makanan dipakai
                                Chat Gizi<template
                                    v-if="sinkronisasi_ai.terakhir_sinkron"
                                    >, terakhir
                                    {{
                                        sinkronisasi_ai.terakhir_sinkron
                                    }}</template
                                ></span
                            >
                            <span v-else
                                >Belum ada makanan yang dipakai Chat Gizi</span
                            >
                        </div>

                        <p
                            v-if="flash?.success"
                            class="mb-3 rounded-xl bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800 dark:bg-[#17321f] dark:text-[#3fcb7a]"
                            role="status"
                        >
                            {{ flash.success }}
                        </p>
                        <p
                            v-if="flash?.error"
                            class="mb-3 rounded-xl bg-[#fef2f2] px-3 py-2 text-xs font-semibold text-red-800 dark:bg-red-950/40 dark:text-red-300"
                            role="alert"
                        >
                            {{ flash.error }}
                        </p>

                        <!-- Verified and eligible, but not published: the one thing a retry fixes -->
                        <div
                            v-if="sinkronisasi_ai.failed > 0"
                            class="flex items-start justify-between gap-3 rounded-2xl border border-[#fecaca] bg-[#fef2f2] p-4 dark:border-red-900/60 dark:bg-red-950/40"
                        >
                            <div class="flex items-start gap-2.5">
                                <AlertCircle
                                    class="mt-0.5 h-4 w-4 shrink-0 text-red-500"
                                    aria-hidden="true"
                                />
                                <div
                                    class="text-xs leading-snug text-red-800 dark:text-red-300"
                                >
                                    {{ sinkronisasi_ai.failed }} item gagal
                                    disinkronkan:
                                    <span class="font-semibold"
                                        >{{
                                            sinkronisasi_ai.failed_names.join(
                                                ', ',
                                            )
                                        }}<template
                                            v-if="
                                                sinkronisasi_ai.failed >
                                                sinkronisasi_ai.failed_names
                                                    .length
                                            "
                                        >
                                            dan lainnya</template
                                        ></span
                                    >.
                                </div>
                            </div>

                            <button
                                type="button"
                                :disabled="retrying"
                                @click="retrySync"
                                class="shrink-0 cursor-pointer text-xs font-bold text-red-600 hover:text-red-700 hover:underline disabled:opacity-50 dark:text-red-400"
                            >
                                {{ retrying ? 'Mencoba…' : 'Coba lagi' }}
                            </button>
                        </div>

                        <!-- Verified but not in grams: needs a weight per unit -->
                        <div
                            v-if="sinkronisasi_ai.needs_weight > 0"
                            class="mt-3 rounded-2xl border border-amber-200/60 bg-amber-50 p-4 text-xs leading-snug text-amber-800 dark:border-amber-800/40 dark:bg-amber-950/40 dark:text-amber-300"
                        >
                            {{ sinkronisasi_ai.needs_weight }} makanan
                            terverifikasi belum dipakai Chat Gizi karena
                            satuannya bukan gram. Isi
                            <span class="font-semibold">berat per satuan</span>
                            di
                            <Link
                                :href="route('admin.foods')"
                                class="font-bold underline"
                                >Data Makanan</Link
                            >.
                        </div>

                        <!-- Healthy status box -->
                        <div
                            v-if="
                                sinkronisasi_ai.failed === 0 &&
                                sinkronisasi_ai.needs_weight === 0 &&
                                sinkronisasi_ai.ready > 0
                            "
                            class="flex items-center gap-3 rounded-2xl border border-[#a2e3bc] bg-[#e4f6ea] p-4 dark:border-emerald-800/60 dark:bg-[#17321f]"
                        >
                            <CheckCircle2
                                class="h-5 w-5 shrink-0 text-[#0b8043] dark:text-[#3fcb7a]"
                            />
                            <div
                                class="text-xs leading-snug font-medium text-[#0b8043] dark:text-[#3fcb7a]"
                            >
                                Seluruh katalog makanan terverifikasi dipakai
                                Chat Gizi.
                            </div>
                        </div>
                    </div>

                    <div
                        class="mt-6 border-t border-gray-50 pt-4 dark:border-[#2e2e2e]"
                    >
                        <p
                            class="text-[11px] text-gray-400 dark:text-[#a3a3a3]"
                        >
                            Database nutrisi disinkronkan secara otomatis ke
                            basis pengetahuan AI untuk menjawab konsultasi cepat
                            pengguna.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
