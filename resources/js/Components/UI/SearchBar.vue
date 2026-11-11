<script setup lang="ts">
import { Search, X } from 'lucide-vue-next'

withDefaults(
  defineProps<{
    modelValue: string
    placeholder?: string
  }>(),
  {
    placeholder: 'Cari nama atau email',
  }
)

const emit = defineEmits<{
  (e: 'update:modelValue', val: string): void
  (e: 'search'): void
  (e: 'clear'): void
}>()

const handleInput = (event: Event) => {
  const target = event.target as HTMLInputElement
  emit('update:modelValue', target.value)
}

const clear = () => {
  emit('update:modelValue', '')
  emit('clear')
}
</script>

<template>
  <div class="relative w-full max-w-xs sm:max-w-sm">
    <div
      class="bg-[#f4f3f0] dark:bg-[#262626] hover:bg-[#eceae5] dark:hover:bg-[#2e2e2e] focus-within:bg-white dark:focus-within:bg-[#1c1c1c] focus-within:ring-2 focus-within:ring-[#0b8043]/30 rounded-full h-10 px-4 flex items-center gap-2.5 transition-all border border-transparent focus-within:border-[#eceae5] dark:focus-within:border-[#3d3d3d]"
    >
      <Search class="w-4 h-4 text-[#5e5e5e] dark:text-[#a3a3a3] shrink-0" />
      <input
        type="text"
        :value="modelValue"
        @input="handleInput"
        @keydown.enter="$emit('search')"
        :placeholder="placeholder"
        class="w-full bg-transparent border-none text-xs sm:text-sm text-[#191919] dark:text-[#f0efea] placeholder-[#8a8a8a] dark:placeholder-gray-500 focus:outline-hidden p-0"
      />
      <button
        v-if="modelValue"
        type="button"
        @click="clear"
        class="p-0.5 rounded-full hover:bg-gray-200 dark:hover:bg-[#3d3d3d] text-gray-500 dark:text-gray-400 transition-colors shrink-0 cursor-pointer"
      >
        <X class="w-3.5 h-3.5" />
      </button>
    </div>
  </div>
</template>
