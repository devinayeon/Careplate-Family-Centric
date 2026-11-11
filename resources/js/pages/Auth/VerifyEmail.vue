<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthBrandPanel from '@/Components/Auth/AuthBrandPanel.vue';

const props = defineProps<{
    status?: string;
}>();

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <div class="flex min-h-screen flex-col bg-white lg:flex-row">
        <Head title="Verifikasi Email - CarePlate" />

        <!-- Left Brand & Feature Carousel Panel -->
        <AuthBrandPanel class="hidden lg:flex" />

        <!-- Right Form Panel -->
        <main class="relative flex flex-1 flex-col justify-between">
            <!-- Mobile Brand Header -->
            <div class="p-6 pb-0 lg:hidden">
                <Link href="/" class="inline-flex items-center gap-2.5">
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-full bg-[#e4f6ea] p-1.5 shadow-xs"
                    >
                        <img
                            src="/assets/logo.png"
                            alt="CarePlate Logo"
                            class="h-full w-full object-contain"
                        />
                    </div>
                    <span
                        class="font-display text-2xl font-bold tracking-tight text-[#0b8043]"
                    >
                        Care<span class="text-[#191919]">Plate</span>
                    </span>
                </Link>
            </div>

            <!-- Center Content Area -->
            <div
                class="mx-auto my-auto w-full max-w-[440px] px-6 py-10 sm:px-8"
            >
                <header class="text-center">
                    <h1
                        class="font-display text-3xl font-bold tracking-tight text-[#191919] sm:text-[38px] sm:leading-tight"
                    >
                        Verifikasi Alamat Email
                    </h1>
                    <p
                        class="mt-2.5 text-sm leading-relaxed font-normal text-[#5e5e5e] sm:text-base"
                    >
                        Terima kasih telah bergabung! Silakan klik tautan
                        verifikasi yang kami kirimkan ke email Anda untuk
                        mengaktifkan seluruh fitur keluarga.
                    </p>
                </header>

                <div
                    v-if="verificationLinkSent"
                    class="mt-6 rounded-2xl border border-green-200 bg-green-50 p-4 text-center text-sm font-semibold text-green-700"
                >
                    Tautan verifikasi baru telah berhasil dikirimkan ke alamat
                    email Anda.
                </div>

                <form
                    id="verify-email-form"
                    @submit.prevent="submit"
                    class="mt-8 space-y-4"
                >
                    <!-- Resend Button -->
                    <div>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-full bg-[#0b8043] px-6 py-3.5 text-base font-bold text-white shadow-sm transition-all hover:scale-[1.01] hover:bg-[#086637] hover:shadow disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:scale-100"
                        >
                            <span
                                v-if="form.processing"
                                class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"
                            ></span>
                            <span>Kirim Ulang Email Verifikasi</span>
                        </button>
                    </div>

                    <!-- Logout Button -->
                    <div class="pt-2 text-center text-sm">
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="font-semibold text-[#5e5e5e] transition-colors hover:text-[#191919] hover:underline"
                        >
                            Keluar dari Akun
                        </Link>
                    </div>
                </form>
            </div>

            <!-- Bottom Bar -->
            <footer
                class="w-full border-t border-[#eceae5] px-6 py-5 text-center"
            >
                <p class="text-xs leading-relaxed text-[#5e5e5e]">
                    Dengan melanjutkan, kamu menyetujui
                    <a href="#" class="font-bold text-[#191919] hover:underline"
                        >Syarat Layanan</a
                    >
                    dan
                    <a href="#" class="font-bold text-[#191919] hover:underline"
                        >Kebijakan Privasi</a
                    >
                </p>
            </footer>
        </main>
    </div>
</template>
