<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Check, Loader2 } from 'lucide-vue-next';
import ProfileField from '@/Components/Profile/ProfileField.vue';

const props = defineProps<{ name: string; email: string }>();

const form = useForm({ name: props.name, email: props.email });

function submit() {
    form.patch(route('profile.update'), { preserveScroll: true });
}
</script>

<template>
    <form
        class="rounded-3xl bg-white p-6 shadow-[0_1px_2px_rgba(30,35,33,0.06),0_4px_12px_rgba(30,35,33,0.06)] dark:bg-[#1c1c1c]"
        aria-labelledby="info-title"
        @submit.prevent="submit"
    >
        <h3
            id="info-title"
            class="font-display text-lg font-bold text-[#191919] dark:text-[#f0efea]"
        >
            Nama dan email
        </h3>
        <div class="mt-4 flex flex-col gap-4">
            <ProfileField
                id="akun-nama"
                v-model="form.name"
                label="Nama"
                autocomplete="name"
                required
                :error="form.errors.name"
            />
            <ProfileField
                id="akun-email"
                v-model="form.email"
                label="Email"
                type="email"
                autocomplete="username"
                required
                :error="form.errors.email"
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
                Simpan
            </button>
            <p
                v-if="form.recentlySuccessful"
                class="inline-flex items-center gap-1.5 text-sm text-[#0b8043] dark:text-[#3fcb7a]"
                role="status"
            >
                <Check class="h-4 w-4" aria-hidden="true" /> Tersimpan.
            </p>
        </div>
    </form>
</template>
