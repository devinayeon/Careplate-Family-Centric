<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Lock } from 'lucide-vue-next';
import AuthBrandPanel from '@/Components/Auth/AuthBrandPanel.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps<{
    email: string;
    token: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);
const showConfirmPassword = ref(false);

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <div class="flex min-h-screen flex-col bg-white lg:flex-row">
        <Head title="Atur Ulang Kata Sandi - CarePlate" />

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
                        Atur Ulang Kata Sandi
                    </h1>
                    <p class="mt-2 text-base font-normal text-[#5e5e5e]">
                        Buat kata sandi baru untuk akun CarePlate keluargamu.
                    </p>
                </header>

                <!-- Form -->
                <form
                    id="reset-password-form"
                    @submit.prevent="submit"
                    class="mt-8 space-y-4"
                >
                    <!-- Email Field (Readonly) -->
                    <div>
                        <div class="flex items-center justify-between">
                            <label
                                for="email"
                                class="block text-sm font-semibold text-[#191919]"
                            >
                                Email
                            </label>
                            <span class="text-xs font-medium text-[#8f8d88]">
                                Tidak dapat diubah
                            </span>
                        </div>
                        <div class="relative mt-2 flex items-center">
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                readonly
                                tabindex="-1"
                                autocomplete="username"
                                class="w-full cursor-not-allowed rounded-2xl border-0 bg-[#ebe9e4]/80 px-4 py-3.5 pr-10 text-base font-medium text-[#5e5e5e] select-none focus:ring-0 focus:outline-none"
                            />
                            <div
                                class="pointer-events-none absolute right-4 text-[#8f8d88]"
                            >
                                <Lock class="h-4 w-4" />
                            </div>
                        </div>
                        <InputError
                            class="mt-1.5"
                            :message="form.errors.email"
                        />
                    </div>

                    <!-- New Password Field -->
                    <div>
                        <label
                            for="password"
                            class="block text-sm font-semibold text-[#191919]"
                        >
                            Kata sandi baru
                        </label>
                        <div class="relative mt-2 flex items-center">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autofocus
                                autocomplete="new-password"
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

                    <!-- Confirm New Password Field -->
                    <div>
                        <label
                            for="password_confirmation"
                            class="block text-sm font-semibold text-[#191919]"
                        >
                            Konfirmasi kata sandi baru
                        </label>
                        <div class="relative mt-2 flex items-center">
                            <input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                :type="
                                    showConfirmPassword ? 'text' : 'password'
                                "
                                required
                                autocomplete="new-password"
                                placeholder="••••••••••"
                                class="w-full rounded-2xl border-0 bg-[#f4f3f0] px-4 py-3.5 pr-28 text-base tracking-wider text-[#191919] placeholder-[#8f8d88] transition-all focus:bg-white focus:ring-2 focus:ring-[#0b8043] focus:outline-none"
                                :class="{
                                    'bg-red-50/20 ring-2 ring-[#d9381e]/60':
                                        form.errors.password_confirmation,
                                }"
                            />
                            <button
                                type="button"
                                @click="
                                    showConfirmPassword = !showConfirmPassword
                                "
                                class="absolute right-4 cursor-pointer text-sm font-bold text-[#191919] transition-colors select-none hover:text-[#0b8043]"
                            >
                                {{
                                    showConfirmPassword
                                        ? 'Sembunyikan'
                                        : 'Tampilkan'
                                }}
                            </button>
                        </div>
                        <InputError
                            class="mt-1.5"
                            :message="form.errors.password_confirmation"
                        />
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-3">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-full bg-[#0b8043] px-6 py-3.5 text-base font-bold text-white shadow-sm transition-all hover:scale-[1.01] hover:bg-[#086637] hover:shadow disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:scale-100"
                        >
                            <span
                                v-if="form.processing"
                                class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"
                            ></span>
                            <span>Simpan Kata Sandi Baru</span>
                        </button>
                    </div>

                    <!-- Back to Login -->
                    <div class="pt-2 text-center text-sm">
                        <Link
                            :href="route('login')"
                            class="font-bold text-[#0b8043] transition-colors hover:text-[#086637] hover:underline"
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
