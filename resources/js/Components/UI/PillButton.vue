<script setup lang="ts">
import { computed } from 'vue'
import { Loader2 } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{
    variant?: 'primary' | 'outline' | 'danger' | 'ghost'
    type?: 'button' | 'submit' | 'reset'
    disabled?: boolean
    loading?: boolean
    size?: 'sm' | 'md' | 'lg'
  }>(),
  {
    variant: 'primary',
    type: 'button',
    disabled: false,
    loading: false,
    size: 'md',
  }
)

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'primary':
      return 'bg-[#0b8043] hover:bg-[#086637] text-white shadow-2xs'
    case 'outline':
      return 'bg-white border-2 border-[#191919] text-[#191919] hover:bg-gray-50'
    case 'danger':
      return 'bg-red-50 hover:bg-red-100 text-red-600 border border-red-200'
    case 'ghost':
      return 'bg-transparent hover:bg-gray-100 text-gray-700'
    default:
      return 'bg-[#0b8043] text-white'
  }
})

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'h-8 px-3 text-xs'
    case 'lg':
      return 'h-12 px-6 text-base'
    default:
      return 'h-10 sm:h-11 px-5 text-sm'
  }
})
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    class="rounded-full font-bold inline-flex items-center justify-center gap-2 transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed select-none cursor-pointer"
    :class="[variantClasses, sizeClasses]"
  >
    <Loader2 v-if="loading" class="w-4 h-4 animate-spin" />
    <slot />
  </button>
</template>
