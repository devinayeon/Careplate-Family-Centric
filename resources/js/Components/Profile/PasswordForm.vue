<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Check, Loader2 } from 'lucide-vue-next';
import ProfileField from '@/Components/Profile/ProfileField.vue';

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const currentField = ref<InstanceType<typeof ProfileField> | null>(null);
const newField = ref<InstanceType<typeof ProfileField> | null>(null);

function submit() {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                newField.value?.focus();
            } else if (form.errors.current_password) {
                form.reset('current_password');
                currentField.value?.focus();
            }
        },
    });
}
</script>

<template>
    <form
        class="rounded-3xl bg-white p-6 shadow-[0_1px_2px_rgba(30,35,33,0.06),0_4px_12px_rgba(30,35,33,0.06)] dark:bg-[#1c1c1c]"
        aria-labelledby="password-title"
        @submit.prevent="submit"
    >
        <h3
            id="password-title"
            class="font-display text-lg font-bold text-[#191919] dark:text-[#f0efea]"
        >
            Ganti kata sandi
        </h3>
        <div class="mt-4 flex flex-col gap-4">
            <ProfileField
                id="akun-sandi-lama"
                ref="currentField"
                v-model="form.current_password"
                label="Kata sandi saat ini"
                type="password"
                autocomplete="current-password"
                required
                :error="form.errors.current_password"
            />
            <ProfileField
                id="akun-sandi-baru"
                ref="newField"
                v-model="form.password"
                label="Kata sandi baru"
                type="password"
                autocomplete="new-password"
                required
                :error="form.errors.password"
            />
            <ProfileField
                id="akun-sandi-ulang"
                v-model="form.password_confirmation"
                label="Ulangi kata sandi baru"
                type="password"
                autocomplete="new-password"
                required
                :error="form.errors.password_confirmation"
            />
        </div>
        <div class="mt-5 flex flex-wrap items-center gap-3">
            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex h-11 items-center gap-2 rounded-full bg-[#0b8043] px-6 text-sm font-semibold text-white hover:bg-[#086637] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919] disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:outline-[#f0efea]"
            >
                <Loader2
                    v-if="form.processing"
                    class="h-4 w-4 animate-spin"
                    aria-hidden="true"
                />
                Ganti kata sandi
            </button>
            <p
                v-if="form.recentlySuccessful"
                class="inline-flex items-center gap-1.5 text-sm text-[#0b8043] dark:text-[#3fcb7a]"
                role="status"
            >
                <Check class="h-4 w-4" aria-hidden="true" /> Kata sandi diganti.
            </p>
        </div>
    </form>
</template>
