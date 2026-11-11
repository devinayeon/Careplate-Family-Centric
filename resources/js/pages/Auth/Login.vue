<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthBrandPanel from '@/Components/Auth/AuthBrandPanel.vue';
import InputError from '@/Components/InputError.vue';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <div class="flex min-h-screen flex-col bg-white lg:flex-row">
        <Head title="Masuk ke CarePlate" />

        <!-- Left Brand & Feature Carousel Panel (Matching Figma Node 10:675) -->
        <AuthBrandPanel class="hidden lg:flex" />

        <!-- Right Form Panel (Matching Figma Node 8:572) -->
        <main class="relative flex flex-1 flex-col justify-between">
            <!-- Mobile Brand Header (Visible on small screens) -->
            <div class="p-6 pb-0 lg:hidden">
                <Link href="/" class="inline-flex items-center">
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-full p-1.5 shadow"
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
                        are<span class="text-[#191919]">Plate</span>
                    </span>
                </Link>
            </div>

            <!-- Center Form Area (Matching Figma Node 8:577) -->
            <div
                class="mx-auto my-auto w-full max-w-[440px] px-6 py-10 sm:px-8"
            >
                <header class="text-center">
                    <h1
                        class="font-display text-3xl font-bold tracking-tight text-[#191919] sm:text-[38px] sm:leading-tight"
                    >
                        Masuk ke CarePlate
                    </h1>
                    <p class="mt-2 text-base font-normal text-[#5e5e5e]">
                        Lanjutkan catatan makan keluargamu.
                    </p>
                </header>

                <!-- Status Flash Message -->
                <div
                    v-if="status"
                    class="mt-4 rounded-xl border border-green-200 bg-green-50 p-3.5 text-sm font-semibold text-green-700"
                >
                    {{ status }}
                </div>

                <!-- Form -->
                <form id="login-form" @submit.prevent="submit" class="mt-8">
                    <!-- Email Field (Matching Figma Node 8:580) -->
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

                    <!-- Password Field with Toggle (Matching Figma Node 8:585) -->
                    <div class="mt-5">
                        <div class="flex items-center justify-between">
                            <label
                                for="password"
                                class="block text-sm font-semibold text-[#191919]"
                            >
                                Kata sandi
                            </label>
                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-xs font-semibold text-[#5e5e5e] transition-colors hover:text-[#0b8043] hover:underline"
                            >
                                Lupa kata sandi?
                            </Link>
                        </div>
                        <div class="relative mt-2 flex items-center">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••••"
                                class="w-full rounded-2xl border-0 bg-[#f4f3f0] px-4 py-3.5 pr-28 text-base tracking-wider text-[#191919] placeholder-[#8f8d88] transition-all focus:bg-white focus:ring-2 focus:ring-[#0b8043] focus:outline-none"
                                :class="{
                                    'bg-red-50/20 ring-2 ring-[#d9381e]/60':
                                        form.errors.password,
                                }"
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

                    <!-- Submit Button (Full width matching Figma Node 8:547) -->
                    <div class="mt-7">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-full bg-[#0b8043] px-6 py-3.5 text-base font-bold text-white shadow-sm transition-all hover:scale-[1.01] hover:bg-[#086637] hover:shadow disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:scale-100"
                        >
                            <span
                                v-if="form.processing"
                                class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"
                            ></span>
                            <span>Masuk</span>
                        </button>
                    </div>

                    <!-- Belum punya akun? Daftar (Centered below button matching Figma Node 212:14004) -->
                    <div class="mt-6 text-center text-sm">
                        <span class="text-[#5e5e5e]">Belum punya akun?</span>
                        <Link
                            :href="route('register')"
                            class="ml-1.5 font-bold text-[#0b8043] transition-colors hover:text-[#086637] hover:underline"
                        >
                            Daftar
                        </Link>
                    </div>
                </form>
            </div>

            <!-- Bottom Bar (Matching Figma Node 8:590) -->
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
