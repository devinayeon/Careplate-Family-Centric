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
    class="h-9 sm:h-10 px-4 rounded-full text-xs sm:text-sm font-bold inline-flex items-center gap-1.5 transition-all select-none whitespace-nowrap"
    :class="
      active
        ? 'bg-[#191919] text-white shadow-xs'
        : 'bg-[#f4f3f0] hover:bg-[#eae8e3] text-[#191919]'
    "
  >
    <span>{{ label }}</span>
    <span
      v-if="typeof count === 'number'"
      class="text-[11px] px-1.5 py-0.2 rounded-full font-semibold opacity-80"
      :class="active ? 'bg-white/20 text-white' : 'bg-black/10 text-gray-800'"
    >
      {{ count }}
    </span>
  </button>
</template>
