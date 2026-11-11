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

        <div class="space-y-6 max-w-4xl mx-auto pb-12">
            <!-- Header Section -->
            <div>
                <h1 class="text-3xl sm:text-4xl font-extrabold text-[#191919] dark:text-[#f0efea] tracking-tight">
                    Pengaturan Profil
                </h1>
                <p class="text-sm text-[#5e5e5e] dark:text-[#a3a3a3] mt-1">
                    Kelola data identitas dan keamanan akun administrator CarePlate Anda.
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
                    class="p-4 rounded-xl bg-emerald-50 dark:bg-[#17321f] border border-emerald-200 dark:border-emerald-800/60 flex items-center gap-3 text-emerald-800 dark:text-emerald-300 shadow-2xs"
                >
                    <CheckCircle2 class="w-5 h-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
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
                    class="p-4 rounded-xl bg-emerald-50 dark:bg-[#17321f] border border-emerald-200 dark:border-emerald-800/60 flex items-center gap-3 text-emerald-800 dark:text-emerald-300 shadow-2xs"
                >
                    <CheckCircle2 class="w-5 h-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                    <span class="text-sm font-semibold">
                        {{ props.password_success }}
                    </span>
                </div>
            </transition>

            <!-- Profile Overview Summary Card -->
            <div class="bg-white dark:bg-[#1c1c1c] rounded-2xl border border-gray-100 dark:border-[#2e2e2e] p-6 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-500 to-[#0b8043] text-white flex items-center justify-center font-bold text-2xl shadow-md shrink-0">
                        {{ userInitials(adminUser.name) }}
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-[#f0efea]">
                                {{ adminUser.name }}
                            </h2>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-[#17321f] dark:text-[#3fcb7a]">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Administrator
                            </span>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-[#a3a3a3] mt-1 flex items-center gap-1.5">
                            <Mail class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                            <span>{{ adminUser.email }}</span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-[#a3a3a3] sm:border-l sm:border-gray-100 dark:sm:border-[#2e2e2e] sm:pl-6">
                    <Calendar class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                    <span>Terdaftar: {{ adminUser.created_at }}</span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Card 1: Informasi Pribadi -->
                <div class="bg-white dark:bg-[#1c1c1c] rounded-2xl border border-gray-100 dark:border-[#2e2e2e] p-6 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-3 pb-4 border-b border-gray-100 dark:border-[#2e2e2e]">
                            <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-[#17321f] text-emerald-600 dark:text-[#3fcb7a] flex items-center justify-center">
                                <User class="w-5 h-5" />
                            </div>
                            <div>
                                <h3 class="font-bold text-base text-gray-900 dark:text-[#f0efea]">
                                    Informasi Pribadi
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-[#a3a3a3]">
                                    Perbarui nama lengkap dan email Anda.
                                </p>
                            </div>
                        </div>

                        <form @submit.prevent="submitProfile" class="mt-5 space-y-4">
                            <!-- Nama Lengkap -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-[#f0efea] uppercase tracking-wider mb-1.5">
                                    Nama Lengkap <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <User class="w-4 h-4 text-gray-400 dark:text-gray-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
                                    <input
                                        v-model="profileForm.name"
                                        type="text"
                                        required
                                        placeholder="Nama Lengkap"
                                        class="w-full pl-10 pr-4 py-2.5 rounded-xl border text-sm transition outline-none
                                               bg-gray-50 dark:bg-[#262626] border-gray-200 dark:border-[#3d3d3d] text-gray-900 dark:text-[#f0efea]
                                               focus:bg-white dark:focus:bg-[#1c1c1c] focus:ring-2 focus:ring-[#0b8043]/30 focus:border-[#0b8043]"
                                        :class="{ 'border-red-500 dark:border-red-500': profileForm.errors.name }"
                                    />
                                </div>
                                <p v-if="profileForm.errors.name" class="text-xs text-red-500 mt-1 flex items-center gap-1 font-medium">
                                    <AlertCircle class="w-3.5 h-3.5" />
                                    <span>{{ profileForm.errors.name }}</span>
                                </p>
                            </div>

                            <!-- Alamat Email -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-[#f0efea] uppercase tracking-wider mb-1.5">
                                    Alamat Email <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <Mail class="w-4 h-4 text-gray-400 dark:text-gray-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
                                    <input
                                        v-model="profileForm.email"
                                        type="email"
                                        required
                                        placeholder="admin@careplate.test"
                                        class="w-full pl-10 pr-4 py-2.5 rounded-xl border text-sm transition outline-none
                                               bg-gray-50 dark:bg-[#262626] border-gray-200 dark:border-[#3d3d3d] text-gray-900 dark:text-[#f0efea]
                                               focus:bg-white dark:focus:bg-[#1c1c1c] focus:ring-2 focus:ring-[#0b8043]/30 focus:border-[#0b8043]"
                                        :class="{ 'border-red-500 dark:border-red-500': profileForm.errors.email }"
                                    />
                                </div>
                                <p v-if="profileForm.errors.email" class="text-xs text-red-500 mt-1 flex items-center gap-1 font-medium">
                                    <AlertCircle class="w-3.5 h-3.5" />
                                    <span>{{ profileForm.errors.email }}</span>
                                </p>
                            </div>

                            <!-- Submit Button -->
                            <div class="pt-3">
                                <button
                                    type="submit"
                                    :disabled="profileForm.processing || isUpdatingProfile"
                                    class="w-full inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm text-white bg-[#0b8043] hover:bg-[#086637] dark:bg-[#3fcb7a] dark:text-[#0e1a13] dark:hover:bg-[#34b86c] transition-all shadow-xs cursor-pointer disabled:opacity-50"
                                >
                                    <Save class="w-4 h-4" />
                                    <span>{{ profileForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Card 2: Keamanan & Kata Sandi -->
                <div class="bg-white dark:bg-[#1c1c1c] rounded-2xl border border-gray-100 dark:border-[#2e2e2e] p-6 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-3 pb-4 border-b border-gray-100 dark:border-[#2e2e2e]">
                            <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-[#17321f] text-emerald-600 dark:text-[#3fcb7a] flex items-center justify-center">
                                <ShieldCheck class="w-5 h-5" />
                            </div>
                            <div>
                                <h3 class="font-bold text-base text-gray-900 dark:text-[#f0efea]">
                                    Perbarui Kata Sandi
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-[#a3a3a3]">
                                    Gunakan kata sandi kuat dan aman.
                                </p>
                            </div>
                        </div>

                        <form @submit.prevent="submitPassword" class="mt-5 space-y-4">
                            <!-- Kata Sandi Saat Ini -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-[#f0efea] uppercase tracking-wider mb-1.5">
                                    Kata Sandi Saat Ini <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <KeyRound class="w-4 h-4 text-gray-400 dark:text-gray-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
                                    <input
                                        v-model="passwordForm.current_password"
                                        :type="showCurrentPassword ? 'text' : 'password'"
                                        required
                                        placeholder="••••••••"
                                        class="w-full pl-10 pr-10 py-2.5 rounded-xl border text-sm transition outline-none
                                               bg-gray-50 dark:bg-[#262626] border-gray-200 dark:border-[#3d3d3d] text-gray-900 dark:text-[#f0efea]
                                               focus:bg-white dark:focus:bg-[#1c1c1c] focus:ring-2 focus:ring-[#0b8043]/30 focus:border-[#0b8043]"
                                        :class="{ 'border-red-500 dark:border-red-500': passwordForm.errors.current_password }"
                                    />
                                    <button
                                        type="button"
                                        @click="showCurrentPassword = !showCurrentPassword"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                    >
                                        <Eye v-if="!showCurrentPassword" class="w-4 h-4" />
                                        <EyeOff v-else class="w-4 h-4" />
                                    </button>
                                </div>
                                <p v-if="passwordForm.errors.current_password" class="text-xs text-red-500 mt-1 flex items-center gap-1 font-medium">
                                    <AlertCircle class="w-3.5 h-3.5" />
                                    <span>{{ passwordForm.errors.current_password }}</span>
                                </p>
                            </div>

                            <!-- Kata Sandi Baru -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-[#f0efea] uppercase tracking-wider mb-1.5">
                                    Kata Sandi Baru <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <Lock class="w-4 h-4 text-gray-400 dark:text-gray-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
                                    <input
                                        v-model="passwordForm.password"
                                        :type="showNewPassword ? 'text' : 'password'"
                                        required
                                        placeholder="Minimal 8 karakter"
                                        class="w-full pl-10 pr-10 py-2.5 rounded-xl border text-sm transition outline-none
                                               bg-gray-50 dark:bg-[#262626] border-gray-200 dark:border-[#3d3d3d] text-gray-900 dark:text-[#f0efea]
                                               focus:bg-white dark:focus:bg-[#1c1c1c] focus:ring-2 focus:ring-[#0b8043]/30 focus:border-[#0b8043]"
                                        :class="{ 'border-red-500 dark:border-red-500': passwordForm.errors.password }"
                                    />
                                    <button
                                        type="button"
                                        @click="showNewPassword = !showNewPassword"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                    >
                                        <Eye v-if="!showNewPassword" class="w-4 h-4" />
                                        <EyeOff v-else class="w-4 h-4" />
                                    </button>
                                </div>
                                <p v-if="passwordForm.errors.password" class="text-xs text-red-500 mt-1 flex items-center gap-1 font-medium">
                                    <AlertCircle class="w-3.5 h-3.5" />
                                    <span>{{ passwordForm.errors.password }}</span>
                                </p>
                            </div>

                            <!-- Konfirmasi Kata Sandi Baru -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-[#f0efea] uppercase tracking-wider mb-1.5">
                                    Ulangi Kata Sandi Baru <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <Lock class="w-4 h-4 text-gray-400 dark:text-gray-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
                                    <input
                                        v-model="passwordForm.password_confirmation"
                                        :type="showConfirmPassword ? 'text' : 'password'"
                                        required
                                        placeholder="Ketik ulang kata sandi baru"
                                        class="w-full pl-10 pr-10 py-2.5 rounded-xl border text-sm transition outline-none
                                               bg-gray-50 dark:bg-[#262626] border-gray-200 dark:border-[#3d3d3d] text-gray-900 dark:text-[#f0efea]
                                               focus:bg-white dark:focus:bg-[#1c1c1c] focus:ring-2 focus:ring-[#0b8043]/30 focus:border-[#0b8043]"
                                        :class="{ 'border-red-500 dark:border-red-500': passwordForm.errors.password_confirmation }"
                                    />
                                    <button
                                        type="button"
                                        @click="showConfirmPassword = !showConfirmPassword"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                    >
                                        <Eye v-if="!showConfirmPassword" class="w-4 h-4" />
                                        <EyeOff v-else class="w-4 h-4" />
                                    </button>
                                </div>
                                <p v-if="passwordForm.errors.password_confirmation" class="text-xs text-red-500 mt-1 flex items-center gap-1 font-medium">
                                    <AlertCircle class="w-3.5 h-3.5" />
                                    <span>{{ passwordForm.errors.password_confirmation }}</span>
                                </p>
                            </div>

                            <!-- Submit Button -->
                            <div class="pt-3">
                                <button
                                    type="submit"
                                    :disabled="passwordForm.processing || isUpdatingPassword"
                                    class="w-full inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm text-white bg-[#0b8043] hover:bg-[#086637] dark:bg-[#3fcb7a] dark:text-[#0e1a13] dark:hover:bg-[#34b86c] transition-all shadow-xs cursor-pointer disabled:opacity-50"
                                >
                                    <Lock class="w-4 h-4" />
                                    <span>{{ passwordForm.processing ? 'Memperbarui...' : 'Perbarui Kata Sandi' }}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
