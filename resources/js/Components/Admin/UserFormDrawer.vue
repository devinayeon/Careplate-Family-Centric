<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { X, Copy, Check, RefreshCw } from 'lucide-vue-next';
import PillButton from '@/Components/UI/PillButton.vue';
import InputError from '@/Components/InputError.vue';

export interface UserFormData {
    id?: number | null;
    name: string;
    email: string;
    role: 'customer' | 'ahli_gizi' | 'admin';
    spesialisasi?: string | null;
    nomor_str?: string | null;
    jam_layanan?: string | null;
    password?: string;
}

const props = defineProps<{
    show: boolean;
    userToEdit?: UserFormData | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'success'): void;
}>();

const copied = ref(false);

const form = useForm({
    name: '',
    email: '',
    role: 'customer' as 'customer' | 'ahli_gizi' | 'admin',
    spesialisasi: 'Gizi Keluarga',
    nomor_str: '',
    jam_layanan: 'Senin sampai Sabtu, 08.00 sampai 12.00',
    password: '',
});

// Fungsi pembersih form mutlak ke kondisi kosong semula
const resetToCleanState = () => {
    form.name = '';
    form.email = '';
    form.role = 'customer';
    form.spesialisasi = 'Gizi Keluarga';
    form.nomor_str = '';
    form.jam_layanan = 'Senin sampai Sabtu, 08.00 sampai 12.00';
    form.password = '';
    form.clearErrors();
    copied.value = false;
};

// Generate random strong password
const generatePassword = () => {
    const chars =
        'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    let pass = '';
    for (let i = 0; i < 12; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    form.password = pass;
};

// Copy password to clipboard
const copyPassword = async () => {
    if (!form.password) return;
    try {
        await navigator.clipboard.writeText(form.password);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch (err) {
        console.error('Failed to copy password', err);
    }
};

// Pantau perubahan visibilitas drawer dan data yang diedit
watch(
    () => [props.show, props.userToEdit] as const,
    ([isOpen, userToEdit]) => {
        if (isOpen) {
            if (userToEdit) {
                // Mode Edit: Isi form dengan data yang dipilih
                form.name = userToEdit.name ?? '';
                form.email = userToEdit.email ?? '';
                form.role = userToEdit.role ?? 'customer';
                form.spesialisasi = userToEdit.spesialisasi || 'Gizi Keluarga';
                form.nomor_str = userToEdit.nomor_str || '';
                form.jam_layanan =
                    userToEdit.jam_layanan ||
                    'Senin sampai Sabtu, 08.00 sampai 12.00';
                form.password = '';
            } else {
                // Mode Tambah Baru: Bersihkan seluruh field tanpa sisa
                resetToCleanState();
                generatePassword();
            }
            form.clearErrors();
        } else {
            // Ketika drawer ditutup, bersihkan juga form agar tidak membekas
            resetToCleanState();
        }
    },
    { immediate: true },
);

const handleClose = () => {
    resetToCleanState();
    emit('close');
};

const submit = () => {
    if (props.userToEdit && props.userToEdit.id) {
        form.put(route('admin.users.update', props.userToEdit.id), {
            preserveScroll: true,
            onSuccess: () => {
                resetToCleanState();
                emit('success');
                emit('close');
            },
        });
    } else {
        form.post(route('admin.users.store'), {
            preserveScroll: true,
            onSuccess: () => {
                resetToCleanState();
                emit('success');
                emit('close');
            },
        });
    }
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 overflow-hidden">
        <!-- Scrim Backdrop -->
        <div
            class="backdrop-blur-2xs fixed inset-0 bg-[#1e2321]/50 transition-opacity"
            @click="handleClose"
        ></div>

        <!-- Slide-over Drawer Panel -->
        <div class="fixed inset-y-0 right-0 flex max-w-full pl-10">
            <div
                class="flex w-screen max-w-md flex-col justify-between overflow-y-auto border-l bg-white text-[#191919] shadow-2xl sm:max-w-[560px] dark:border-[#2e2e2e] dark:bg-[#1c1c1c] dark:text-[#f0efea]"
            >
                <!-- Drawer Header -->
                <div
                    class="flex items-center justify-between border-b border-[#eceae5] px-7 pt-6 pb-4 dark:border-[#2e2e2e]"
                >
                    <h2
                        class="text-2xl font-extrabold tracking-tight text-[#191919] sm:text-3xl dark:text-[#f0efea]"
                    >
                        {{ userToEdit ? 'Ubah pengguna' : 'Tambah pengguna' }}
                    </h2>
                    <button
                        type="button"
                        @click="handleClose"
                        class="flex h-10 w-10 items-center justify-center rounded-xl text-gray-500 transition-colors hover:bg-gray-100 dark:text-[#a3a3a3] dark:hover:bg-[#262626]"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Form Body -->
                <form
                    @submit.prevent="submit"
                    id="userForm"
                    class="flex-1 space-y-5 p-7"
                >
                    <!-- Role Segmented Control -->
                    <div>
                        <label
                            class="mb-2 block text-sm font-bold text-[#191919] dark:text-[#f0efea]"
                        >
                            Peran
                        </label>
                        <div
                            class="flex overflow-hidden rounded-xl border-[1.5px] border-[#191919] bg-[#f4f3f0] p-0.5 dark:border-[#3d3d3d] dark:bg-[#262626]"
                        >
                            <button
                                type="button"
                                @click="form.role = 'customer'"
                                class="flex-1 rounded-lg py-2 text-center text-xs font-bold transition-all sm:text-sm"
                                :class="
                                    form.role === 'customer'
                                        ? 'bg-[#191919] text-white shadow-2xs dark:bg-[#3fcb7a] dark:text-[#121212]'
                                        : 'text-[#191919] hover:bg-white/60 dark:text-[#a3a3a3] dark:hover:bg-[#1c1c1c]'
                                "
                            >
                                Customer
                            </button>
                            <button
                                type="button"
                                @click="form.role = 'ahli_gizi'"
                                class="flex-1 rounded-lg border-r border-l border-[#191919]/20 py-2 text-center text-xs font-bold transition-all sm:text-sm dark:border-[#3d3d3d]"
                                :class="
                                    form.role === 'ahli_gizi'
                                        ? 'bg-[#191919] text-white shadow-2xs dark:bg-[#3fcb7a] dark:text-[#121212]'
                                        : 'text-[#191919] hover:bg-white/60 dark:text-[#a3a3a3] dark:hover:bg-[#1c1c1c]'
                                "
                            >
                                Ahli Gizi
                            </button>
                            <button
                                type="button"
                                @click="form.role = 'admin'"
                                class="flex-1 rounded-lg py-2 text-center text-xs font-bold transition-all sm:text-sm"
                                :class="
                                    form.role === 'admin'
                                        ? 'bg-[#191919] text-white shadow-2xs dark:bg-[#3fcb7a] dark:text-[#121212]'
                                        : 'text-[#191919] hover:bg-white/60 dark:text-[#a3a3a3] dark:hover:bg-[#1c1c1c]'
                                "
                            >
                                Admin
                            </button>
                        </div>
                        <InputError :message="form.errors.role" class="mt-1" />
                    </div>

                    <!-- Nama Lengkap -->
                    <div>
                        <label
                            class="mb-1.5 block text-sm font-bold text-[#191919] dark:text-[#f0efea]"
                        >
                            Nama lengkap
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            placeholder="Contoh: Rina Santoso"
                            required
                            class="h-12 w-full rounded-xl border-2 border-transparent bg-[#f4f3f0] px-4 text-sm text-[#191919] placeholder-gray-400 outline-hidden transition-all hover:bg-[#eceae5] focus:border-[#191919] focus:bg-white dark:bg-[#262626] dark:text-[#f0efea] dark:placeholder-[#737373] dark:hover:bg-[#2e2e2e] dark:focus:border-[#3fcb7a] dark:focus:bg-[#1c1c1c]"
                        />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>

                    <!-- Email -->
                    <div>
                        <label
                            class="mb-1.5 block text-sm font-bold text-[#191919] dark:text-[#f0efea]"
                        >
                            Email
                        </label>
                        <input
                            v-model="form.email"
                            type="email"
                            placeholder="nama@email.com"
                            required
                            class="h-12 w-full rounded-xl border-2 border-transparent bg-[#f4f3f0] px-4 text-sm text-[#191919] placeholder-gray-400 outline-hidden transition-all hover:bg-[#eceae5] focus:border-[#191919] focus:bg-white dark:bg-[#262626] dark:text-[#f0efea] dark:placeholder-[#737373] dark:hover:bg-[#2e2e2e] dark:focus:border-[#3fcb7a] dark:focus:bg-[#1c1c1c]"
                        />
                        <InputError :message="form.errors.email" class="mt-1" />
                    </div>

                    <!-- Conditional Fields for Ahli Gizi -->
                    <div
                        v-if="form.role === 'ahli_gizi'"
                        class="space-y-5 border-t border-[#eceae5] pt-2 dark:border-[#2e2e2e]"
                    >
                        <!-- Spesialisasi -->
                        <div>
                            <label
                                class="mb-1.5 block text-sm font-bold text-[#191919] dark:text-[#f0efea]"
                            >
                                Spesialisasi
                            </label>
                            <input
                                v-model="form.spesialisasi"
                                type="text"
                                placeholder="Contoh: Gizi Keluarga, Gizi Anak"
                                class="h-12 w-full rounded-xl border-2 border-[#191919] bg-[#f4f3f0] px-4 text-sm font-medium text-[#191919] outline-hidden transition-all hover:bg-[#eceae5] focus:border-[#0b8043] focus:bg-white dark:border-[#3d3d3d] dark:bg-[#262626] dark:text-[#f0efea] dark:hover:bg-[#2e2e2e] dark:focus:border-[#3fcb7a] dark:focus:bg-[#1c1c1c]"
                            />
                            <InputError
                                :message="form.errors.spesialisasi"
                                class="mt-1"
                            />
                        </div>

                        <!-- Nomor STR -->
                        <div>
                            <label
                                class="mb-1.5 block text-sm font-bold text-[#191919] dark:text-[#f0efea]"
                            >
                                Nomor STR
                            </label>
                            <input
                                v-model="form.nomor_str"
                                type="text"
                                placeholder="1234 5678 9012 3456"
                                class="h-12 w-full rounded-xl border-2 border-transparent bg-[#f4f3f0] px-4 font-mono text-sm text-[#191919] placeholder-gray-400 outline-hidden transition-all hover:bg-[#eceae5] focus:border-[#191919] focus:bg-white dark:bg-[#262626] dark:text-[#f0efea] dark:placeholder-[#737373] dark:hover:bg-[#2e2e2e] dark:focus:border-[#3fcb7a] dark:focus:bg-[#1c1c1c]"
                            />
                            <InputError
                                :message="form.errors.nomor_str"
                                class="mt-1"
                            />
                        </div>

                        <!-- Jam Layanan -->
                        <div>
                            <label
                                class="mb-1.5 block text-sm font-bold text-[#191919] dark:text-[#f0efea]"
                            >
                                Jam layanan
                            </label>
                            <input
                                v-model="form.jam_layanan"
                                type="text"
                                placeholder="Senin sampai Sabtu, 08.00 sampai 12.00"
                                class="h-12 w-full rounded-xl border-2 border-transparent bg-[#f4f3f0] px-4 text-sm text-[#191919] placeholder-gray-400 outline-hidden transition-all hover:bg-[#eceae5] focus:border-[#191919] focus:bg-white dark:bg-[#262626] dark:text-[#f0efea] dark:placeholder-[#737373] dark:hover:bg-[#2e2e2e] dark:focus:border-[#3fcb7a] dark:focus:bg-[#1c1c1c]"
                            />
                            <InputError
                                :message="form.errors.jam_layanan"
                                class="mt-1"
                            />
                        </div>
                    </div>

                    <!-- Password / Kata Sandi Sementara -->
                    <div>
                        <div class="mb-1.5 flex items-center justify-between">
                            <label
                                class="text-sm font-bold text-[#191919] dark:text-[#f0efea]"
                            >
                                {{
                                    userToEdit
                                        ? 'Kata sandi baru (kosongkan jika tidak diubah)'
                                        : 'Kata sandi sementara'
                                }}
                            </label>
                            <button
                                type="button"
                                @click="generatePassword"
                                class="inline-flex items-center gap-1 text-xs font-semibold text-[#0b8043] hover:underline dark:text-[#3fcb7a]"
                            >
                                <RefreshCw class="h-3 w-3" />
                                <span>Acak Sandi</span>
                            </button>
                        </div>

                        <div
                            class="flex h-12 items-center justify-between gap-3 rounded-xl border-2 border-transparent bg-[#f4f3f0] px-4 focus-within:border-[#191919] focus-within:bg-white dark:bg-[#262626] dark:focus-within:border-[#3fcb7a] dark:focus-within:bg-[#1c1c1c]"
                        >
                            <input
                                v-model="form.password"
                                type="text"
                                :placeholder="
                                    userToEdit ? '••••••••' : 'Buat kata sandi'
                                "
                                :required="!userToEdit"
                                class="w-full border-none bg-transparent p-0 font-mono text-sm text-[#191919] placeholder-gray-400 outline-hidden dark:text-[#f0efea] dark:placeholder-[#737373]"
                            />
                            <button
                                v-if="form.password"
                                type="button"
                                @click="copyPassword"
                                class="inline-flex shrink-0 items-center gap-1 rounded-md px-2.5 py-1 text-xs font-bold text-[#086637] transition-colors hover:bg-emerald-50 dark:text-[#3fcb7a] dark:hover:bg-[#17321f]"
                            >
                                <Check
                                    v-if="copied"
                                    class="h-3.5 w-3.5 text-emerald-600 dark:text-[#3fcb7a]"
                                />
                                <Copy
                                    v-else
                                    class="h-3.5 w-3.5 text-[#086637] dark:text-[#3fcb7a]"
                                />
                                <span>{{ copied ? 'Tersalin' : 'Salin' }}</span>
                            </button>
                        </div>
                        <p
                            class="mt-1.5 text-xs text-[#5e5e5e] dark:text-[#a3a3a3]"
                        >
                            Ditampilkan sekali. Pengguna menggantinya saat
                            pertama masuk.
                        </p>
                        <InputError
                            :message="form.errors.password"
                            class="mt-1"
                        />
                    </div>
                </form>

                <!-- Drawer Footer -->
                <div
                    class="flex items-center justify-end gap-3 border-t border-[#eceae5] bg-[#faf9f7] px-7 py-4 dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
                >
                    <PillButton
                        variant="outline"
                        type="button"
                        @click="handleClose"
                    >
                        Batal
                    </PillButton>
                    <PillButton
                        variant="primary"
                        type="submit"
                        form="userForm"
                        :loading="form.processing"
                    >
                        {{
                            userToEdit ? 'Perbarui Pengguna' : 'Simpan Pengguna'
                        }}
                    </PillButton>
                </div>
            </div>
        </div>
    </div>
</template>
