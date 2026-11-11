<script setup lang="ts">
import { ChevronLeft, ChevronRight, Inbox } from 'lucide-vue-next'

export interface TableColumn {
  key: string
  label: string
  width?: string
  align?: 'left' | 'center' | 'right'
}

withDefaults(
  defineProps<{
    columns: TableColumn[]
    items: any[]
    total?: number
    from?: number | null
    to?: number | null
    prevUrl?: string | null
    nextUrl?: string | null
    emptyTitle?: string
    emptyDescription?: string
  }>(),
  {
    total: 0,
    from: 0,
    to: 0,
    emptyTitle: 'Belum ada data',
    emptyDescription: 'Tidak ada data yang cocok dengan kriteria pencarian.',
  }
)

defineEmits<{
  (e: 'page-change', url: string): void
}>()
</script>

<template>
  <div class="bg-white border border-[#eceae5] rounded-2xl overflow-hidden shadow-2xs w-full">
    <!-- Responsive Table Wrapper -->
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <!-- Table Header (bg #f4f3f0) -->
        <thead>
          <tr class="bg-[#f4f3f0] text-xs font-bold text-[#5e5e5e]">
            <th
              v-for="col in columns"
              :key="col.key"
              class="px-6 py-4 whitespace-nowrap"
              :style="{ width: col.width }"
              :class="{
                'text-center': col.align === 'center',
                'text-right': col.align === 'right',
                'text-left': !col.align || col.align === 'left',
              }"
            >
              {{ col.label }}
            </th>
          </tr>
        </thead>

        <!-- Table Body -->
        <tbody class="divide-y divide-[#eceae5] text-sm text-[#191919]">
          <tr
            v-for="(item, index) in items"
            :key="item.id ?? index"
            class="hover:bg-[#faf9f7] transition-colors"
          >
            <slot name="row" :item="item" :index="index" />
          </tr>

          <!-- Empty State -->
          <tr v-if="items.length === 0">
            <td :colspan="columns.length" class="py-16 text-center text-gray-500">
              <slot name="empty">
                <div class="flex flex-col items-center justify-center">
                  <div class="w-12 h-12 rounded-full bg-[#f4f3f0] flex items-center justify-center mb-3 text-gray-400">
                    <Inbox class="w-6 h-6" />
                  </div>
                  <p class="font-bold text-gray-900 text-sm">{{ emptyTitle }}</p>
                  <p class="text-xs text-gray-400 mt-1 max-w-xs">{{ emptyDescription }}</p>
                </div>
              </slot>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Table Pagination Footer -->
    <div
      v-if="total > 0"
      class="border-t border-[#eceae5] px-6 py-3.5 flex items-center justify-between text-xs text-[#5e5e5e] font-medium"
    >
      <div>
        <span>{{ from ?? 1 }} sampai {{ to ?? items.length }} dari {{ total }}</span>
      </div>

      <div class="flex items-center gap-2">
        <button
          type="button"
          :disabled="!prevUrl"
          @click="prevUrl && $emit('page-change', prevUrl)"
          aria-label="Sebelumnya"
          class="w-10 h-10 rounded-xl border border-[#eceae5] bg-white flex items-center justify-center transition-all disabled:opacity-30 disabled:cursor-not-allowed hover:bg-gray-50 active:scale-95 text-[#191919]"
        >
          <ChevronLeft class="w-4 h-4" />
        </button>

        <button
          type="button"
          :disabled="!nextUrl"
          @click="nextUrl && $emit('page-change', nextUrl)"
          aria-label="Berikutnya"
          class="w-10 h-10 rounded-xl border border-[#eceae5] bg-white flex items-center justify-center transition-all disabled:opacity-30 disabled:cursor-not-allowed hover:bg-gray-50 active:scale-95 text-[#191919]"
        >
          <ChevronRight class="w-4 h-4" />
        </button>
      </div>
    </div>
  </div>
</template>
