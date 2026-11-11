<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthBrandPanel from '@/Components/Auth/AuthBrandPanel.vue';
import InputError from '@/Components/InputError.vue';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <div class="flex min-h-screen flex-col bg-white lg:flex-row">
        <Head title="Lupa Kata Sandi - CarePlate" />

        <!-- Left Brand & Feature Carousel Panel -->
        <AuthBrandPanel class="hidden lg:flex" />

        <!-- Right Form Panel -->
        <main class="relative flex flex-1 flex-col justify-between">
            <!-- Mobile Brand Header (Visible on small screens) -->
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

            <!-- Center Form Area -->
            <div
                class="mx-auto my-auto w-full max-w-[440px] px-6 py-10 sm:px-8"
            >
                <header class="text-center">
                    <h1
                        class="font-display text-3xl font-bold tracking-tight text-[#191919] sm:text-[38px] sm:leading-tight"
                    >
                        Lupa Kata Sandi?
                    </h1>
                    <p
                        class="mt-2.5 text-sm leading-relaxed font-normal text-[#5e5e5e] sm:text-base"
                    >
                        Masukkan email yang terdaftar di akun CarePlate. Kami
                        akan mengirimkan tautan untuk mengatur ulang kata sandi
                        Anda.
                    </p>
                </header>

                <!-- Status Flash Message -->
                <div
                    v-if="status"
                    class="mt-6 rounded-2xl border border-green-200 bg-green-50 p-4 text-center text-sm font-semibold text-green-700"
                >
                    {{ status }}
                </div>

                <!-- Form -->
                <form
                    id="forgot-password-form"
                    @submit.prevent="submit"
                    class="mt-8 space-y-6"
                >
                    <!-- Email Field -->
                    <div>
                        <label
                            for="email"
                            class="block text-sm font-semibold text-[#191919]"
                        >
                            Email
                        </label>
                        <div class="mt-2">
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="rina.santoso@gmail.com"
                                class="w-full rounded-2xl border-0 bg-[#f4f3f0] px-4 py-3.5 text-base text-[#191919] placeholder-[#8f8d88] transition-all focus:bg-white focus:ring-2 focus:ring-[#0b8043] focus:outline-none"
                                :class="{
                                    'bg-red-50/20 ring-2 ring-[#d9381e]/60':
                                        form.errors.email,
                                }"
                            />
                        </div>
                        <InputError
                            class="mt-1.5"
                            :message="form.errors.email"
                        />
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-full bg-[#0b8043] px-6 py-3.5 text-base font-bold text-white shadow-sm transition-all hover:scale-[1.01] hover:bg-[#086637] hover:shadow disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:scale-100"
                        >
                            <span
                                v-if="form.processing"
                                class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"
                            ></span>
                            <span>Kirim Tautan Reset Password</span>
                        </button>
                    </div>

                    <!-- Back to Login -->
                    <div class="pt-2 text-center text-sm">
                        <span class="text-[#5e5e5e]">Ingat kata sandi?</span>
                        <Link
                            :href="route('login')"
                            class="ml-1.5 font-bold text-[#0b8043] transition-colors hover:text-[#086637] hover:underline"
                        >
                            Kembali ke Masuk
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
