<script setup lang="ts">
import { ref } from 'vue';

const props = defineProps<{
    id: string;
    label: string;
    type?: string;
    autocomplete?: string;
    required?: boolean;
    error?: string;
}>();

const model = defineModel<string>({ required: true });
const input = ref<HTMLInputElement | null>(null);

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <div>
        <label
            :for="props.id"
            class="block text-sm font-semibold text-[#191919] dark:text-[#f0efea]"
        >
            {{ props.label }}
        </label>
        <input
            :id="props.id"
            ref="input"
            v-model="model"
            :type="props.type ?? 'text'"
            :autocomplete="props.autocomplete"
            :required="props.required"
            :aria-invalid="props.error ? 'true' : undefined"
            :aria-describedby="props.error ? `${props.id}-error` : undefined"
            class="mt-1.5 h-11 w-full rounded-xl border border-[#d6d3cc] bg-white px-4 text-base text-[#191919] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919] aria-[invalid=true]:border-[#b42318] dark:border-[#3d3d3d] dark:bg-[#262626] dark:text-[#f0efea] dark:focus-visible:outline-[#f0efea]"
        />
        <p
            v-if="props.error"
            :id="`${props.id}-error`"
            class="mt-1.5 text-sm text-[#a52f08] dark:text-red-300"
            role="alert"
        >
            {{ props.error }}
        </p>
    </div>
</template>
