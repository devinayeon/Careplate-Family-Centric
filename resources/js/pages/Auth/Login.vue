<script setup lang="ts">
import { ref } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import AuthBrandPanel from "@/Components/Auth/AuthBrandPanel.vue";
import InputError from "@/Components/InputError.vue";
import Checkbox from "@/Components/Checkbox.vue";

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    email: "",
    password: "",
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route("login"), {
        onFinish: () => {
            form.reset("password");
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
        <main
            class="relative flex flex-1 flex-col justify-between p-6 sm:p-10 lg:p-12 xl:p-16"
        >
            <!-- Top Navigation Bar (Matching Figma Node 8:575) -->
            <div class="flex items-center justify-between lg:justify-end">
                <!-- Mobile brand logo (visible on small screens when left panel is hidden) -->
                <Link href="/" class="flex items-center gap-2 lg:hidden">
                    <span
                        class="font-display text-2xl font-bold tracking-tight text-[#0b8043]"
                    >
                        Care<span class="text-[#191919]">Plate</span>
                    </span>
                </Link>

                <div class="flex items-center gap-2 text-sm sm:text-base">
                    <span class="text-[#5e5e5e]">Belum punya akun?</span>
                    <Link
                        :href="route('register')"
                        class="font-bold text-[#0b8043] transition-colors hover:text-[#086637] hover:underline"
                    >
                        Daftar
                    </Link>
                </div>
            </div>

            <!-- Center Form Area (Matching Figma Node 8:576 & 8:577) -->
            <div class="mx-auto my-auto w-full max-w-[440px] py-8">
                <header class="space-y-2">
                    <h1
                        class="font-display text-3xl font-bold tracking-tight text-[#191919] sm:text-4xl"
                    >
                        Masuk ke CarePlate
                    </h1>
                    <p class="text-base font-medium text-[#5e5e5e]">
                        Lanjutkan catatan makan keluarga.
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
                <form
                    id="login-form"
                    @submit.prevent="submit"
                    class="mt-8 space-y-6"
                >
                    <!-- Email Field -->
                    <div>
                        <label
                            for="email"
                            class="block text-sm font-bold text-[#191919]"
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
                            />
                        </div>
                        <InputError
                            class="mt-1.5"
                            :message="form.errors.email"
                        />
                    </div>

                    <!-- Password Field with Toggle (Matching Figma Node 8:585) -->
                    <div>
                        <div class="flex items-center justify-between">
                            <label
                                for="password"
                                class="block text-sm font-bold text-[#191919]"
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
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-4 cursor-pointer text-sm font-bold text-[#191919] transition-colors select-none hover:text-[#0b8043]"
                            >
                                {{ showPassword ? "Sembunyikan" : "Tampilkan" }}
                            </button>
                        </div>
                        <InputError
                            class="mt-1.5"
                            :message="form.errors.password"
                        />
                    </div>

                    <!-- Remember Me Option -->
                    <div class="flex items-center pt-1">
                        <label class="flex cursor-pointer items-center gap-2.5">
                            <Checkbox
                                name="remember"
                                v-model:checked="form.remember"
                                class="rounded border-[#d6d3cc] text-[#0b8043] shadow-xs focus:ring-[#0b8043]"
                            />
                            <span
                                class="text-xs font-medium text-[#5e5e5e] select-none"
                            >
                                Ingat saya di perangkat ini
                            </span>
                        </label>
                    </div>
                </form>
            </div>

            <!-- Bottom Bar (Matching Figma Node 8:590) -->
            <footer
                class="mt-8 flex flex-col items-center justify-between gap-4 border-t border-[#eceae5] pt-6 sm:flex-row"
            >
                <p
                    class="text-center text-xs leading-relaxed text-[#5e5e5e] sm:text-left"
                >
                    Dengan melanjutkan, kamu menyetujui
                    <a href="#" class="font-bold text-[#191919] hover:underline"
                        >Syarat Layanan</a
                    >
                    dan
                    <a href="#" class="font-bold text-[#191919] hover:underline"
                        >Kebijakan Privasi</a
                    >
                </p>

                <!-- Submit Button -->
                <button
                    type="submit"
                    form="login-form"
                    :disabled="form.processing"
                    class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-full bg-[#0b8043] px-9 py-3 text-sm font-bold text-white shadow-sm transition-all hover:scale-105 hover:bg-[#086637] hover:shadow disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:scale-100 sm:w-auto"
                >
                    <span
                        v-if="form.processing"
                        class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"
                    ></span>
                    <span>Masuk</span>
                </button>
            </footer>
        </main>
    </div>
</template>
