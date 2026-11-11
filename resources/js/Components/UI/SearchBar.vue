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
      class="bg-[#f4f3f0] hover:bg-[#eceae5] focus-within:bg-white focus-within:ring-2 focus-within:ring-[#0b8043]/30 rounded-full h-10 px-4 flex items-center gap-2.5 transition-all border border-transparent focus-within:border-[#eceae5]"
    >
      <Search class="w-4 h-4 text-[#5e5e5e] shrink-0" />
      <input
        type="text"
        :value="modelValue"
        @input="handleInput"
        @keydown.enter="$emit('search')"
        :placeholder="placeholder"
        class="w-full bg-transparent border-none text-xs sm:text-sm text-[#191919] placeholder-[#8a8a8a] focus:outline-hidden p-0"
      />
      <button
        v-if="modelValue"
        type="button"
        @click="clear"
        class="p-0.5 rounded-full hover:bg-gray-200 text-gray-500 transition-colors shrink-0"
      >
        <X class="w-3.5 h-3.5" />
      </button>
    </div>
  </div>
</template>
