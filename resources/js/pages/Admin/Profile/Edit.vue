<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import {
    User,
    Mail,
    Lock,
    ShieldCheck,
    KeyRound,
    Eye,
    EyeOff,
    CheckCircle2,
    Calendar,
    Save,
    AlertCircle,
} from 'lucide-vue-next';

const props = defineProps<{
    adminUser: {
        id: number;
        name: string;
        email: string;
        created_at: string;
    };
    success?: string;
    password_success?: string;
}>();

const page = usePage();

// 1. Form Informasi Pribadi
const profileForm = useForm({
    name: props.adminUser.name,
    email: props.adminUser.email,
});

const isUpdatingProfile = ref(false);

const submitProfile = () => {
    isUpdatingProfile.value = true;
    profileForm.patch(route('admin.profile.update'), {
        preserveScroll: true,
        onFinish: () => {
            isUpdatingProfile.value = false;
        },
    });
};

// 2. Form Keamanan Kata Sandi
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);
const isUpdatingPassword = ref(false);

const submitPassword = () => {
    isUpdatingPassword.value = true;
    passwordForm.put(route('admin.profile.password'), {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
        },
        onFinish: () => {
            isUpdatingPassword.value = false;
        },
    });
};

const userInitials = (name: string) => {
    if (!name) return 'AD';
    return name
        .split(' ')
        .map((n) => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
};
</script>

<template>
    <AdminLayout title="Pengaturan Profil">
        <Head title="Pengaturan Profil - CarePlate Admin" />

        <div class="mx-auto max-w-4xl space-y-6 pb-12">
            <!-- Header Section -->
            <div>
                <h1
                    class="text-3xl font-extrabold tracking-tight text-[#191919] sm:text-4xl dark:text-[#f0efea]"
                >
                    Pengaturan Profil
                </h1>
                <p class="mt-1 text-sm text-[#5e5e5e] dark:text-[#a3a3a3]">
                    Kelola data identitas dan keamanan akun administrator
                    CarePlate Anda.
                </p>
            </div>

            <!-- Flash Success Message (Profile Info) -->
            <transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <div
                    v-if="props.success"
                    class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 shadow-2xs dark:border-emerald-800/60 dark:bg-[#17321f] dark:text-emerald-300"
                >
                    <CheckCircle2
                        class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400"
                    />
                    <span class="text-sm font-semibold">
                        {{ props.success }}
                    </span>
                </div>
            </transition>

            <!-- Flash Success Message (Password) -->
            <transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <div
                    v-if="props.password_success"
                    class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 shadow-2xs dark:border-emerald-800/60 dark:bg-[#17321f] dark:text-emerald-300"
                >
                    <CheckCircle2
                        class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400"
                    />
                    <span class="text-sm font-semibold">
                        {{ props.password_success }}
                    </span>
                </div>
            </transition>

            <!-- Profile Overview Summary Card -->
            <div
                class="flex flex-col justify-between gap-6 rounded-2xl border border-gray-100 bg-white p-6 shadow-xs sm:flex-row sm:items-center dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
            >
                <div class="flex items-center gap-4">
                    <div
                        class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-[#0b8043] text-2xl font-bold text-white shadow-md"
                    >
                        {{ userInitials(adminUser.name) }}
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h2
                                class="text-xl font-bold text-gray-900 dark:text-[#f0efea]"
                            >
                                {{ adminUser.name }}
                            </h2>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-[#17321f] dark:text-[#3fcb7a]"
                            >
                                <span
                                    class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"
                                ></span>
                                Administrator
                            </span>
                        </div>
                        <p
                            class="mt-1 flex items-center gap-1.5 text-sm text-gray-500 dark:text-[#a3a3a3]"
                        >
                            <Mail
                                class="h-4 w-4 text-gray-400 dark:text-gray-500"
                            />
                            <span>{{ adminUser.email }}</span>
                        </p>
                    </div>
                </div>

                <div
                    class="flex items-center gap-2 text-xs font-medium text-gray-500 sm:border-l sm:border-gray-100 sm:pl-6 dark:text-[#a3a3a3] dark:sm:border-[#2e2e2e]"
                >
                    <Calendar
                        class="h-4 w-4 text-gray-400 dark:text-gray-500"
                    />
                    <span>Terdaftar: {{ adminUser.created_at }}</span>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <!-- Card 1: Informasi Pribadi -->
                <div
                    class="flex flex-col justify-between rounded-2xl border border-gray-100 bg-white p-6 shadow-xs dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
                >
                    <div>
                        <div
                            class="flex items-center gap-3 border-b border-gray-100 pb-4 dark:border-[#2e2e2e]"
                        >
                            <div
                                class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-[#17321f] dark:text-[#3fcb7a]"
                            >
                                <User class="h-5 w-5" />
                            </div>
                            <div>
                                <h3
                                    class="text-base font-bold text-gray-900 dark:text-[#f0efea]"
                                >
                                    Informasi Pribadi
                                </h3>
                                <p
                                    class="text-xs text-gray-500 dark:text-[#a3a3a3]"
                                >
                                    Perbarui nama lengkap dan email Anda.
                                </p>
                            </div>
                        </div>

                        <form
                            @submit.prevent="submitProfile"
                            class="mt-5 space-y-4"
                        >
                            <!-- Nama Lengkap -->
                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-bold tracking-wider text-gray-700 uppercase dark:text-[#f0efea]"
                                >
                                    Nama Lengkap
                                    <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <User
                                        class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500"
                                    />
                                    <input
                                        v-model="profileForm.name"
                                        type="text"
                                        required
                                        placeholder="Nama Lengkap"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pr-4 pl-10 text-sm text-gray-900 transition outline-none focus:border-[#0b8043] focus:bg-white focus:ring-2 focus:ring-[#0b8043]/30 dark:border-[#3d3d3d] dark:bg-[#262626] dark:text-[#f0efea] dark:focus:bg-[#1c1c1c]"
                                        :class="{
                                            'border-red-500 dark:border-red-500':
                                                profileForm.errors.name,
                                        }"
                                    />
                                </div>
                                <p
                                    v-if="profileForm.errors.name"
                                    class="mt-1 flex items-center gap-1 text-xs font-medium text-red-500"
                                >
                                    <AlertCircle class="h-3.5 w-3.5" />
                                    <span>{{ profileForm.errors.name }}</span>
                                </p>
                            </div>

                            <!-- Alamat Email -->
                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-bold tracking-wider text-gray-700 uppercase dark:text-[#f0efea]"
                                >
                                    Alamat Email
                                    <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <Mail
                                        class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500"
                                    />
                                    <input
                                        v-model="profileForm.email"
                                        type="email"
                                        required
                                        placeholder="admin@careplate.test"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pr-4 pl-10 text-sm text-gray-900 transition outline-none focus:border-[#0b8043] focus:bg-white focus:ring-2 focus:ring-[#0b8043]/30 dark:border-[#3d3d3d] dark:bg-[#262626] dark:text-[#f0efea] dark:focus:bg-[#1c1c1c]"
                                        :class="{
                                            'border-red-500 dark:border-red-500':
                                                profileForm.errors.email,
                                        }"
                                    />
                                </div>
                                <p
                                    v-if="profileForm.errors.email"
                                    class="mt-1 flex items-center gap-1 text-xs font-medium text-red-500"
                                >
                                    <AlertCircle class="h-3.5 w-3.5" />
                                    <span>{{ profileForm.errors.email }}</span>
                                </p>
                            </div>

                            <!-- Submit Button -->
                            <div class="pt-3">
                                <button
                                    type="submit"
                                    :disabled="
                                        profileForm.processing ||
                                        isUpdatingProfile
                                    "
                                    class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-[#0b8043] px-5 py-2.5 text-sm font-bold text-white shadow-xs transition-all hover:bg-[#086637] disabled:opacity-50 dark:bg-[#3fcb7a] dark:text-[#0e1a13] dark:hover:bg-[#34b86c]"
                                >
                                    <Save class="h-4 w-4" />
                                    <span>{{
                                        profileForm.processing
                                            ? 'Menyimpan...'
                                            : 'Simpan Perubahan'
                                    }}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Card 2: Keamanan & Kata Sandi -->
                <div
                    class="flex flex-col justify-between rounded-2xl border border-gray-100 bg-white p-6 shadow-xs dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
                >
                    <div>
                        <div
                            class="flex items-center gap-3 border-b border-gray-100 pb-4 dark:border-[#2e2e2e]"
                        >
                            <div
                                class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-[#17321f] dark:text-[#3fcb7a]"
                            >
                                <ShieldCheck class="h-5 w-5" />
                            </div>
                            <div>
                                <h3
                                    class="text-base font-bold text-gray-900 dark:text-[#f0efea]"
                                >
                                    Perbarui Kata Sandi
                                </h3>
                                <p
                                    class="text-xs text-gray-500 dark:text-[#a3a3a3]"
                                >
                                    Gunakan kata sandi kuat dan aman.
                                </p>
                            </div>
                        </div>

                        <form
                            @submit.prevent="submitPassword"
                            class="mt-5 space-y-4"
                        >
                            <!-- Kata Sandi Saat Ini -->
                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-bold tracking-wider text-gray-700 uppercase dark:text-[#f0efea]"
                                >
                                    Kata Sandi Saat Ini
                                    <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <KeyRound
                                        class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500"
                                    />
                                    <input
                                        v-model="passwordForm.current_password"
                                        :type="
                                            showCurrentPassword
                                                ? 'text'
                                                : 'password'
                                        "
                                        required
                                        placeholder="••••••••"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pr-10 pl-10 text-sm text-gray-900 transition outline-none focus:border-[#0b8043] focus:bg-white focus:ring-2 focus:ring-[#0b8043]/30 dark:border-[#3d3d3d] dark:bg-[#262626] dark:text-[#f0efea] dark:focus:bg-[#1c1c1c]"
                                        :class="{
                                            'border-red-500 dark:border-red-500':
                                                passwordForm.errors
                                                    .current_password,
                                        }"
                                    />
                                    <button
                                        type="button"
                                        @click="
                                            showCurrentPassword =
                                                !showCurrentPassword
                                        "
                                        class="absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                    >
                                        <Eye
                                            v-if="!showCurrentPassword"
                                            class="h-4 w-4"
                                        />
                                        <EyeOff v-else class="h-4 w-4" />
                                    </button>
                                </div>
                                <p
                                    v-if="passwordForm.errors.current_password"
                                    class="mt-1 flex items-center gap-1 text-xs font-medium text-red-500"
                                >
                                    <AlertCircle class="h-3.5 w-3.5" />
                                    <span>{{
                                        passwordForm.errors.current_password
                                    }}</span>
                                </p>
                            </div>

                            <!-- Kata Sandi Baru -->
                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-bold tracking-wider text-gray-700 uppercase dark:text-[#f0efea]"
                                >
                                    Kata Sandi Baru
                                    <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <Lock
                                        class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500"
                                    />
                                    <input
                                        v-model="passwordForm.password"
                                        :type="
                                            showNewPassword
                                                ? 'text'
                                                : 'password'
                                        "
                                        required
                                        placeholder="Minimal 8 karakter"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pr-10 pl-10 text-sm text-gray-900 transition outline-none focus:border-[#0b8043] focus:bg-white focus:ring-2 focus:ring-[#0b8043]/30 dark:border-[#3d3d3d] dark:bg-[#262626] dark:text-[#f0efea] dark:focus:bg-[#1c1c1c]"
                                        :class="{
                                            'border-red-500 dark:border-red-500':
                                                passwordForm.errors.password,
                                        }"
                                    />
                                    <button
                                        type="button"
                                        @click="
                                            showNewPassword = !showNewPassword
                                        "
                                        class="absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                    >
                                        <Eye
                                            v-if="!showNewPassword"
                                            class="h-4 w-4"
                                        />
                                        <EyeOff v-else class="h-4 w-4" />
                                    </button>
                                </div>
                                <p
                                    v-if="passwordForm.errors.password"
                                    class="mt-1 flex items-center gap-1 text-xs font-medium text-red-500"
                                >
                                    <AlertCircle class="h-3.5 w-3.5" />
                                    <span>{{
                                        passwordForm.errors.password
                                    }}</span>
                                </p>
                            </div>

                            <!-- Konfirmasi Kata Sandi Baru -->
                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-bold tracking-wider text-gray-700 uppercase dark:text-[#f0efea]"
                                >
                                    Ulangi Kata Sandi Baru
                                    <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <Lock
                                        class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500"
                                    />
                                    <input
                                        v-model="
                                            passwordForm.password_confirmation
                                        "
                                        :type="
                                            showConfirmPassword
                                                ? 'text'
                                                : 'password'
                                        "
                                        required
                                        placeholder="Ketik ulang kata sandi baru"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pr-10 pl-10 text-sm text-gray-900 transition outline-none focus:border-[#0b8043] focus:bg-white focus:ring-2 focus:ring-[#0b8043]/30 dark:border-[#3d3d3d] dark:bg-[#262626] dark:text-[#f0efea] dark:focus:bg-[#1c1c1c]"
                                        :class="{
                                            'border-red-500 dark:border-red-500':
                                                passwordForm.errors
                                                    .password_confirmation,
                                        }"
                                    />
                                    <button
                                        type="button"
                                        @click="
                                            showConfirmPassword =
                                                !showConfirmPassword
                                        "
                                        class="absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                    >
                                        <Eye
                                            v-if="!showConfirmPassword"
                                            class="h-4 w-4"
                                        />
                                        <EyeOff v-else class="h-4 w-4" />
                                    </button>
                                </div>
                                <p
                                    v-if="
                                        passwordForm.errors
                                            .password_confirmation
                                    "
                                    class="mt-1 flex items-center gap-1 text-xs font-medium text-red-500"
                                >
                                    <AlertCircle class="h-3.5 w-3.5" />
                                    <span>{{
                                        passwordForm.errors
                                            .password_confirmation
                                    }}</span>
                                </p>
                            </div>

                            <!-- Submit Button -->
                            <div class="pt-3">
                                <button
                                    type="submit"
                                    :disabled="
                                        passwordForm.processing ||
                                        isUpdatingPassword
                                    "
                                    class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-[#0b8043] px-5 py-2.5 text-sm font-bold text-white shadow-xs transition-all hover:bg-[#086637] disabled:opacity-50 dark:bg-[#3fcb7a] dark:text-[#0e1a13] dark:hover:bg-[#34b86c]"
                                >
                                    <Lock class="h-4 w-4" />
                                    <span>{{
                                        passwordForm.processing
                                            ? 'Memperbarui...'
                                            : 'Perbarui Kata Sandi'
                                    }}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
