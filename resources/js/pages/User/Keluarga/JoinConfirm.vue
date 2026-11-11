<script setup lang="ts">
import { Head, Link, useForm } from "@inertiajs/vue3";
import UserLayout from "@/Layouts/UserLayout.vue";
import { Users, CheckCircle2, ArrowRight, AlertTriangle, Loader2 } from "lucide-vue-next";

const props = defineProps<{
    keluarga: {
        id: number;
        nama_keluarga: string;
        kode_keluarga: string;
        kepala_keluarga: string;
        jumlah_anggota: number;
    };
    userCurrentFamily: string | null;
}>();

const form = useForm({});

const submitJoin = () => {
    form.post(route("user.family.accept_join_link", { kode: props.keluarga.kode_keluarga }));
};
</script>

<template>
    <UserLayout title="Undangan Bergabung Keluarga">
        <Head :title="`Undangan ${keluarga.nama_keluarga} - CarePlate`" />

        <div class="max-w-xl mx-auto py-8 sm:py-12 space-y-6">
            <div
                class="bg-white dark:bg-[#1c1c1c] border border-gray-100 dark:border-[#2e2e2e] rounded-3xl p-6 sm:p-10 shadow-sm text-center space-y-6"
            >
                <!-- Icon -->
                <div class="w-20 h-20 rounded-3xl bg-emerald-50 dark:bg-[#17321f] text-[#0b8043] dark:text-[#3fcb7a] flex items-center justify-center mx-auto shadow-inner">
                    <Users class="w-10 h-10" />
                </div>

                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-[#0b8043] dark:text-[#3fcb7a]">
                        Undangan Grup Keluarga
                    </span>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-[#f0efea] mt-1">
                        {{ keluarga.nama_keluarga }}
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-[#a3a3a3] mt-1">
                        Kepala Keluarga: <span class="font-semibold text-gray-800 dark:text-gray-200">{{ keluarga.kepala_keluarga }}</span> • {{ keluarga.jumlah_anggota }} anggota
                    </p>
                </div>

                <!-- Info Box -->
                <div class="p-4 rounded-2xl bg-gray-50 dark:bg-[#262626] border border-gray-100 dark:border-[#2e2e2e] text-xs text-gray-600 dark:text-[#d4d4d4] leading-relaxed text-left">
                    Dengan bergabung, kamu akan ikut serta dalam meja catatan keluarga mingguan dan saling memotivasi kebiasaan makan sehat. Catatan berat badan dan data medis pribadimu tetap aman privat.
                </div>

                <!-- Warning if already in another family -->
                <div
                    v-if="userCurrentFamily"
                    class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900/50 text-xs text-amber-800 dark:text-amber-300 flex items-start gap-3 text-left"
                >
                    <AlertTriangle class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                    <div>
                        Kamu saat ini masih tergabung di grup <strong>{{ userCurrentFamily }}</strong>. Kamu harus keluar dari grup tersebut terlebih dahulu sebelum bisa bergabung ke grup baru.
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
                    <Link
                        :href="route('user.family')"
                        class="w-full sm:w-auto px-6 py-3 rounded-xl border border-gray-200 dark:border-[#3d3d3d] text-sm font-semibold text-gray-600 dark:text-[#a3a3a3] hover:bg-gray-100 dark:hover:bg-[#262626] transition text-center"
                    >
                        Batal
                    </Link>

                    <button
                        v-if="!userCurrentFamily"
                        type="button"
                        @click="submitJoin"
                        :disabled="form.processing"
                        class="w-full sm:w-auto px-8 py-3 rounded-xl bg-[#0b8043] dark:bg-[#3fcb7a] text-white dark:text-[#121212] text-sm font-bold hover:bg-[#096836] dark:hover:bg-[#34aa66] disabled:opacity-40 shadow-sm transition flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
                        <span>Ya, Bergabung</span>
                        <ArrowRight v-if="!form.processing" class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
