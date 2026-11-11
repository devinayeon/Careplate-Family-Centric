<script setup lang="ts">
import { nextTick, ref } from 'vue';
import { Trash2 } from 'lucide-vue-next';
import ProfileInfoForm from '@/Components/Profile/ProfileInfoForm.vue';
import PasswordForm from '@/Components/Profile/PasswordForm.vue';
import DeleteAccountDialog from '@/Components/Profile/DeleteAccountDialog.vue';

defineProps<{ name: string; email: string }>();

const deleting = ref(false);
const deleteButton = ref<HTMLButtonElement | null>(null);

function closeDialog() {
    deleting.value = false;
    // Focus goes back to the button that opened the dialog.
    void nextTick(() => deleteButton.value?.focus());
}
</script>

<template>
    <section aria-labelledby="akun-title" class="flex flex-col gap-6">
        <h2
            id="akun-title"
            class="font-display text-2xl font-bold text-[#191919] dark:text-[#f0efea]"
        >
            Akun
        </h2>
        <div class="grid gap-6 lg:grid-cols-2">
            <ProfileInfoForm :name="name" :email="email" />
            <PasswordForm />
        </div>
        <div
            class="flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-[#eceae5] bg-white p-6 dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
        >
            <div class="max-w-xl">
                <h3
                    class="font-display text-lg font-bold text-[#191919] dark:text-[#f0efea]"
                >
                    Hapus akun
                </h3>
                <p class="mt-1 text-base text-[#5e5e5e] dark:text-[#a3a3a3]">
                    Akun dan semua datamu dihapus permanen. Ini tidak bisa
                    dibatalkan.
                </p>
            </div>
            <button
                ref="deleteButton"
                type="button"
                class="inline-flex h-11 items-center gap-2 rounded-full border-2 border-[#b42318] bg-white px-5 text-sm font-semibold text-[#b42318] hover:bg-[#fde8e6] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919] dark:border-red-300 dark:bg-[#1c1c1c] dark:text-red-300 dark:hover:bg-[#3a1614] dark:focus-visible:outline-[#f0efea]"
                @click="deleting = true"
            >
                <Trash2 class="h-4 w-4" aria-hidden="true" /> Hapus akun
            </button>
        </div>
        <DeleteAccountDialog :open="deleting" @close="closeDialog" />
    </section>
</template>
