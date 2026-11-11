<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthBrandPanel from '@/Components/Auth/AuthBrandPanel.vue';
import InputError from '@/Components/InputError.vue';

const form = useForm({
    password: '',
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <div class="flex min-h-screen flex-col bg-white lg:flex-row">
        <Head title="Konfirmasi Kata Sandi - CarePlate" />

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

            <!-- Center Form Area -->
            <div
                class="mx-auto my-auto w-full max-w-[440px] px-6 py-10 sm:px-8"
            >
                <header class="text-center">
                    <h1
                        class="font-display text-3xl font-bold tracking-tight text-[#191919] sm:text-[38px] sm:leading-tight"
                    >
                        Konfirmasi Kata Sandi
                    </h1>
                    <p
                        class="mt-2.5 text-sm leading-relaxed font-normal text-[#5e5e5e] sm:text-base"
                    >
                        Ini adalah area aman. Harap konfirmasi kata sandi akun
                        Anda sebelum melanjutkan.
                    </p>
                </header>

                <form
                    id="confirm-password-form"
                    @submit.prevent="submit"
                    class="mt-8 space-y-6"
                >
                    <!-- Password Field -->
                    <div>
                        <label
                            for="password"
                            class="block text-sm font-semibold text-[#191919]"
                        >
                            Kata sandi
                        </label>
                        <div class="relative mt-2 flex items-center">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autofocus
                                autocomplete="current-password"
                                placeholder="••••••••••"
                                class="w-full rounded-2xl border-0 bg-[#f4f3f0] px-4 py-3.5 pr-28 text-base tracking-wider text-[#191919] placeholder-[#8f8d88] transition-all focus:bg-white focus:ring-2 focus:ring-[#0b8043] focus:outline-none"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-4 cursor-pointer text-sm font-bold text-[#191919] transition-colors select-none hover:text-[#0b8043]"
                            >
                                {{ showPassword ? 'Sembunyikan' : 'Tampilkan' }}
                            </button>
                        </div>
                        <InputError
                            class="mt-1.5"
                            :message="form.errors.password"
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
                            <span>Konfirmasi</span>
                        </button>
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
