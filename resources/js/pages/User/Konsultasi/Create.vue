<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import UserLayout from '@/Layouts/UserLayout.vue';
import {
    ArrowLeft,
    User,
    Users,
    ShieldCheck,
    Award,
    Clock,
    DollarSign,
    Loader2,
    CheckCircle2,
} from 'lucide-vue-next';

interface AhliGiziDetail {
    id: number;
    nama: string;
    spesialisasi: string;
    nomor_str: string;
    jam_layanan: string;
    bio: string;
    tarif_konsultasi: number;
}

interface UserProfile {
    nama: string;
    usia: number;
    gender: string;
    health_snapshot: {
        bmi_usia: string;
        energi_rata_rata: string;
        camilan_manis: string;
        sayur_tercatat: string;
    };
}

const props = defineProps<{
    ahli_gizi: AhliGiziDetail;
    user_profile: UserProfile;
}>();

const targetTipe = ref<'diri_sendiri' | 'orang_lain'>('diri_sendiri');
const sertakanDataKesehatan = ref(true);

const form = useForm({
    target_tipe: 'diri_sendiri',
    nama_pasien: props.user_profile.nama || '',
    usia_pasien: props.user_profile.usia || 25,
    topik: props.ahli_gizi.spesialisasi || 'Konsultasi Gizi',
    keluhan: '',
    sertakan_data_kesehatan: true,
    data_kesehatan: props.user_profile.health_snapshot,
});

watch(targetTipe, (newVal) => {
    form.target_tipe = newVal;
    if (newVal === 'diri_sendiri') {
        form.nama_pasien = props.user_profile.nama || '';
        form.usia_pasien = props.user_profile.usia || 25;
        form.sertakan_data_kesehatan = sertakanDataKesehatan.value;
    } else {
        form.nama_pasien = '';
        form.usia_pasien = 0;
        form.sertakan_data_kesehatan = false;
    }
});

watch(sertakanDataKesehatan, (newVal) => {
    form.sertakan_data_kesehatan = newVal;
});

const submit = () => {
    form.post(route('user.consultation.store', props.ahli_gizi.id));
};

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(val);
};

const getInitials = (name: string) => {
    const parts = name.split(' ');
    let init = '';
    for (const p of parts) {
        if (p) init += p[0].toUpperCase();
        if (init.length >= 2) break;
    }
    return init || 'DR';
};
</script>

<template>
    <Head title="Form Pengajuan Konsultasi" />

    <UserLayout>
        <div class="mx-auto max-w-4xl space-y-6 pb-12">
            <!-- Back Navigation -->
            <div>
                <Link
                    :href="route('user.consultation')"
                    class="inline-flex items-center gap-2 text-xs font-semibold text-gray-500 transition-colors hover:text-gray-900 dark:text-gray-400 dark:hover:text-[#f0efea]"
                >
                    <ArrowLeft class="h-4 w-4" />
                    <span>Kembali ke Katalog Ahli Gizi</span>
                </Link>
            </div>

            <!-- Page Header -->
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-[#f0efea] sm:text-3xl">
                    Form Pengajuan Konsultasi
                </h1>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Lengkapi informasi berikut untuk memulai sesi konsultasi gizi terarah.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                <!-- Left Column: Form Fields (7 cols) -->
                <div class="space-y-6 lg:col-span-7">
                    <form @submit.prevent="submit" class="space-y-6 rounded-3xl border border-gray-100 bg-white p-6 shadow-xs dark:border-[#262626] dark:bg-[#1a1a1a]">
                        <!-- 1. Pilihan Target Pasien -->
                        <div>
                            <label class="block text-xs font-bold text-gray-900 dark:text-[#f0efea]">
                                Konsultasi Diajukan Untuk:
                            </label>
                            <div class="mt-2.5 grid grid-cols-2 gap-3">
                                <button
                                    type="button"
                                    @click="targetTipe = 'diri_sendiri'"
                                    class="flex items-center gap-2.5 rounded-2xl border p-3.5 text-left transition-all"
                                    :class="
                                        targetTipe === 'diri_sendiri'
                                            ? 'border-emerald-600 bg-emerald-50/50 text-emerald-900 shadow-2xs dark:border-emerald-500 dark:bg-emerald-950/40 dark:text-emerald-200'
                                            : 'border-gray-200 text-gray-700 hover:bg-gray-50 dark:border-[#262626] dark:text-gray-300 dark:hover:bg-[#222]'
                                    "
                                >
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-xl"
                                        :class="targetTipe === 'diri_sendiri' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-500 dark:bg-[#262626]'"
                                    >
                                        <User class="h-4 w-4" />
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold">Saya Sendiri</div>
                                        <div class="text-[10px] text-gray-400">Gunakan data pribadi</div>
                                    </div>
                                </button>

                                <button
                                    type="button"
                                    @click="targetTipe = 'orang_lain'"
                                    class="flex items-center gap-2.5 rounded-2xl border p-3.5 text-left transition-all"
                                    :class="
                                        targetTipe === 'orang_lain'
                                            ? 'border-emerald-600 bg-emerald-50/50 text-emerald-900 shadow-2xs dark:border-emerald-500 dark:bg-emerald-950/40 dark:text-emerald-200'
                                            : 'border-gray-200 text-gray-700 hover:bg-gray-50 dark:border-[#262626] dark:text-gray-300 dark:hover:bg-[#222]'
                                    "
                                >
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-xl"
                                        :class="targetTipe === 'orang_lain' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-500 dark:bg-[#262626]'"
                                    >
                                        <Users class="h-4 w-4" />
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold">Anak / Keluarga</div>
                                        <div class="text-[10px] text-gray-400">Input nama & usia</div>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- 2. Data Pasien (Nama & Usia) -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                                    Nama Lengkap Pasien <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.nama_pasien"
                                    type="text"
                                    placeholder="Contoh: Dina Santoso"
                                    required
                                    class="mt-1.5 w-full rounded-2xl border border-gray-200 bg-gray-50/50 py-2.5 px-3.5 text-xs text-gray-900 transition-colors focus:border-emerald-600 focus:bg-white focus:outline-none dark:border-[#262626] dark:bg-[#121212] dark:text-[#f0efea]"
                                />
                                <span v-if="form.errors.nama_pasien" class="mt-1 text-[11px] text-red-500">
                                    {{ form.errors.nama_pasien }}
                                </span>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                                    Usia (Tahun) <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.usia_pasien"
                                    type="number"
                                    min="0"
                                    max="120"
                                    required
                                    class="mt-1.5 w-full rounded-2xl border border-gray-200 bg-gray-50/50 py-2.5 px-3.5 text-xs text-gray-900 transition-colors focus:border-emerald-600 focus:bg-white focus:outline-none dark:border-[#262626] dark:bg-[#121212] dark:text-[#f0efea]"
                                />
                                <span v-if="form.errors.usia_pasien" class="mt-1 text-[11px] text-red-500">
                                    {{ form.errors.usia_pasien }}
                                </span>
                            </div>
                        </div>

                        <!-- 3. Topik Konsultasi (Terkunci Sesuai Spesialisasi) -->
                        <div>
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                                    Topik Konsultasi (Terkunci)
                                </label>
                                <span class="text-[10px] text-gray-400">Sesuai spesialisasi dokter</span>
                            </div>
                            <input
                                :value="ahli_gizi.spesialisasi || 'Gizi Umum'"
                                disabled
                                type="text"
                                class="mt-1.5 w-full cursor-not-allowed rounded-2xl border border-gray-200 bg-gray-100/80 py-2.5 px-3.5 text-xs font-semibold text-gray-600 dark:border-[#262626] dark:bg-[#222] dark:text-gray-400"
                            />
                        </div>

                        <!-- 4. Keluhan / Deskripsi Masalah Gizi -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                                Keluhan & Kondisi Gizi <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                v-model="form.keluhan"
                                rows="4"
                                placeholder="Jelaskan pola makan harian, kebiasaan ngemil, alergi makanan, atau target perubahan yang diinginkan..."
                                required
                                class="mt-1.5 w-full rounded-2xl border border-gray-200 bg-gray-50/50 p-3.5 text-xs leading-relaxed text-gray-900 transition-colors focus:border-emerald-600 focus:bg-white focus:outline-none dark:border-[#262626] dark:bg-[#121212] dark:text-[#f0efea]"
                            ></textarea>
                            <span v-if="form.errors.keluhan" class="mt-1 text-[11px] text-red-500">
                                {{ form.errors.keluhan }}
                            </span>
                        </div>

                        <!-- 5. Opsi Sertakan Data Kesehatan (Bila diri sendiri) -->
                        <div v-if="targetTipe === 'diri_sendiri'" class="rounded-2xl border border-emerald-100 bg-emerald-50/40 p-4 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input
                                    v-model="sertakanDataKesehatan"
                                    type="checkbox"
                                    class="h-4 w-4 rounded-md border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                />
                                <div class="text-xs">
                                    <span class="font-bold text-gray-900 dark:text-[#f0efea]">
                                        Sertakan Ringkasan Data Kesehatan Pribadi
                                    </span>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                        Membagikan snapshot BMI, estimasi energi harian, dan log pola makan 7 hari kepada ahli gizi.
                                    </p>
                                </div>
                            </label>
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-2">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-[#0b8043] py-3 text-xs font-bold text-white shadow-xs transition-all hover:bg-[#096a37] disabled:opacity-50"
                            >
                                <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                                <span>Kirim Pengajuan Konsultasi</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Right Column: Nutritionist Summary & Data Preview (5 cols) -->
                <div class="space-y-5 lg:col-span-5">
                    <!-- Nutritionist Profile Card -->
                    <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-xs dark:border-[#262626] dark:bg-[#1a1a1a]">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 block mb-3">
                            Ahli Gizi yang Dituju
                        </span>
                        <div class="flex items-start gap-3.5">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-sm font-bold text-[#0f6b36] dark:bg-emerald-950 dark:text-emerald-300">
                                {{ getInitials(ahli_gizi.nama) }}
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-sm font-bold text-gray-900 dark:text-[#f0efea]">
                                    {{ ahli_gizi.nama }}
                                </h3>
                                <div class="mt-0.5 text-xs text-emerald-700 dark:text-emerald-400 font-semibold">
                                    {{ ahli_gizi.spesialisasi }}
                                </div>
                                <div class="mt-0.5 text-[11px] text-gray-400">
                                    STR: {{ ahli_gizi.nomor_str }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 space-y-2 border-t border-gray-100 pt-3 text-xs dark:border-[#262626]">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-400">Jadwal Praktik</span>
                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ ahli_gizi.jam_layanan }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-400">Biaya Sesi</span>
                                <span class="font-bold text-gray-900 dark:text-[#f0efea]">
                                    {{ ahli_gizi.tarif_konsultasi ? formatCurrency(ahli_gizi.tarif_konsultasi) : 'Gratis' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Health Data Snapshot Preview (if checked) -->
                    <div
                        v-if="targetTipe === 'diri_sendiri' && sertakanDataKesehatan"
                        class="rounded-3xl border border-gray-100 bg-white p-6 shadow-xs dark:border-[#262626] dark:bg-[#1a1a1a]"
                    >
                        <div class="flex items-center gap-2 font-bold text-emerald-800 dark:text-emerald-400 text-xs mb-3">
                            <ShieldCheck class="h-4 w-4" />
                            <span>Preview Data Kesehatan yang Dibagikan</span>
                        </div>

                        <div class="space-y-2.5 text-xs">
                            <div class="flex items-center justify-between py-1 border-b border-gray-50 dark:border-[#262626]">
                                <span class="text-gray-500 dark:text-gray-400">Status BMI</span>
                                <span class="font-bold text-gray-900 dark:text-[#f0efea]">{{ user_profile.health_snapshot.bmi_usia }}</span>
                            </div>
                            <div class="flex items-center justify-between py-1 border-b border-gray-50 dark:border-[#262626]">
                                <span class="text-gray-500 dark:text-gray-400">Energi Rata-rata 7 Hari</span>
                                <span class="font-bold text-gray-900 dark:text-[#f0efea]">{{ user_profile.health_snapshot.energi_rata_rata }}</span>
                            </div>
                            <div class="flex items-center justify-between py-1 border-b border-gray-50 dark:border-[#262626]">
                                <span class="text-gray-500 dark:text-gray-400">Camilan Manis</span>
                                <span class="font-bold text-gray-900 dark:text-[#f0efea]">{{ user_profile.health_snapshot.camilan_manis }}</span>
                            </div>
                            <div class="flex items-center justify-between py-1">
                                <span class="text-gray-500 dark:text-gray-400">Sayur Tercatat</span>
                                <span class="font-bold text-gray-900 dark:text-[#f0efea]">{{ user_profile.health_snapshot.sayur_tercatat }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
