<script setup lang="ts">
import { computed } from 'vue'
import { Shield, Sparkles, User } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{
    role: 'customer' | 'ahli_gizi' | 'admin' | string
    label?: string
  }>(),
  {
    role: 'customer',
  }
)

const displayLabel = computed(() => {
  if (props.label) return props.label
  if (props.role === 'ahli_gizi' || props.role === 'nutritionist') return 'Ahli Gizi'
  if (props.role === 'admin') return 'Admin'
  return 'Customer'
})

const badgeConfig = computed(() => {
  const r = props.role.toLowerCase()
  if (r === 'ahli_gizi' || r === 'nutritionist') {
    return {
      bg: 'bg-[#e4f6ea] dark:bg-[#17321f]',
      text: 'text-[#0b7a46] dark:text-[#3fcb7a]',
      icon: Sparkles,
    }
  }
  if (r === 'admin') {
    return {
      bg: 'bg-[#e1f4f5] dark:bg-cyan-950/60',
      text: 'text-[#0b6a73] dark:text-cyan-300',
      icon: Shield,
    }
  }
  return {
    bg: 'bg-[#f4f3f0] dark:bg-[#262626]',
    text: 'text-[#5e5e5e] dark:text-[#a3a3a3]',
    icon: User,
  }
})
</script>

<template>
  <div
    class="inline-flex items-center gap-1.5 pl-2 pr-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap"
    :class="[badgeConfig.bg, badgeConfig.text]"
  >
    <component :is="badgeConfig.icon" class="w-3.5 h-3.5 shrink-0 opacity-80" />
    <span>{{ displayLabel }}</span>
  </div>
</template>
