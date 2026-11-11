<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Loader2, TriangleAlert } from 'lucide-vue-next';
import ProfileField from '@/Components/Profile/ProfileField.vue';

const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ (e: 'close'): void }>();

const form = useForm({ password: '' });
const panel = ref<HTMLElement | null>(null);
const field = ref<InstanceType<typeof ProfileField> | null>(null);

watch(
    () => props.open,
    async (open) => {
        if (!open) return;
        form.reset();
        form.clearErrors();
        await nextTick();
        field.value?.focus();
    },
);

function close() {
    if (form.processing) return;
    emit('close');
}

function submit() {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
        onError: () => field.value?.focus(),
        onFinish: () => form.reset(),
    });
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        event.preventDefault();
        close();
        return;
    }
    if (event.key !== 'Tab' || !panel.value) return;
    const items = Array.from(
        panel.value.querySelectorAll<HTMLElement>(
            'input:not([disabled]), button:not([disabled])',
        ),
    );
    if (!items.length) return;
    const first = items[0];
    const last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}
</script>

<template>
    <div
        v-if="props.open"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        @keydown="onKeydown"
    >
        <div
            class="absolute inset-0 bg-[#1e2321]/50"
            aria-hidden="true"
            @click="close"
        />
        <form
            ref="panel"
            role="dialog"
            aria-modal="true"
            aria-labelledby="hapus-akun-title"
            aria-describedby="hapus-akun-desc"
            class="relative w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl sm:p-8 dark:bg-[#1c1c1c]"
            @submit.prevent="submit"
        >
            <span
                class="flex h-12 w-12 items-center justify-center rounded-full bg-[#fde8e6] dark:bg-[#3a1614]"
            >
                <TriangleAlert
                    class="h-5 w-5 text-[#b42318] dark:text-red-300"
                    aria-hidden="true"
                />
            </span>
            <h2
                id="hapus-akun-title"
                class="mt-4 font-display text-2xl font-bold text-[#191919] dark:text-[#f0efea]"
            >
                Hapus akun?
            </h2>
            <p
                id="hapus-akun-desc"
                class="mt-3 text-base text-[#5e5e5e] dark:text-[#a3a3a3]"
            >
                Akun dan semua datamu dihapus permanen. Masukkan kata sandi
                untuk melanjutkan.
            </p>
            <div class="mt-5">
                <ProfileField
                    id="hapus-akun-sandi"
                    ref="field"
                    v-model="form.password"
                    label="Kata sandi"
                    type="password"
                    autocomplete="current-password"
                    required
                    :error="form.errors.password"
                />
            </div>
            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <button
                    type="button"
                    :disabled="form.processing"
                    class="h-11 rounded-full border-2 border-[#191919] bg-white px-6 text-sm font-semibold text-[#191919] hover:bg-[#f4f3f0] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919] disabled:opacity-50 dark:border-[#f0efea] dark:bg-[#1c1c1c] dark:text-[#f0efea] dark:hover:bg-[#262626] dark:focus-visible:outline-[#f0efea]"
                    @click="close"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex h-11 items-center gap-2 rounded-full bg-[#b42318] px-6 text-sm font-semibold text-white hover:bg-[#912018] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919] disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:outline-[#f0efea]"
                >
                    <Loader2
                        v-if="form.processing"
                        class="h-4 w-4 animate-spin"
                        aria-hidden="true"
                    />
                    {{ form.processing ? 'Menghapus…' : 'Hapus akun' }}
                </button>
            </div>
        </form>
    </div>
</template>
