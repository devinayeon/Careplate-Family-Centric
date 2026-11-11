<script setup lang="ts">
defineProps<{
  label: string
  count?: number | null
  active?: boolean
}>()

defineEmits<{
  (e: 'click'): void
}>()
</script>

<template>
  <button
    type="button"
    @click="$emit('click')"
    class="h-9 sm:h-10 px-4 rounded-full text-xs sm:text-sm font-bold inline-flex items-center gap-1.5 transition-all select-none whitespace-nowrap cursor-pointer"
    :class="
      active
        ? 'bg-[#191919] text-white dark:bg-[#f0efea] dark:text-[#121212] shadow-xs'
        : 'bg-[#f4f3f0] hover:bg-[#eae8e3] text-[#191919] dark:bg-[#1c1c1c] dark:hover:bg-[#262626] dark:text-[#f0efea] border border-transparent dark:border-[#2e2e2e]'
    "
  >
    <span>{{ label }}</span>
    <span
      v-if="typeof count === 'number'"
      class="text-[11px] px-1.5 py-0.2 rounded-full font-semibold opacity-80"
      :class="active ? 'bg-white/20 text-white dark:bg-black/20 dark:text-[#121212]' : 'bg-black/10 text-gray-800 dark:bg-white/10 dark:text-[#f0efea]'"
    >
      {{ count }}
    </span>
  </button>
</template>
