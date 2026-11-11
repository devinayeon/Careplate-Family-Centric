<script setup lang="ts">
import { ChevronLeft, ChevronRight, Inbox } from 'lucide-vue-next';

export interface TableColumn {
    key: string;
    label: string;
    width?: string;
    align?: 'left' | 'center' | 'right';
}

withDefaults(
    defineProps<{
        columns: TableColumn[];
        items: any[];
        total?: number;
        from?: number | null;
        to?: number | null;
        prevUrl?: string | null;
        nextUrl?: string | null;
        emptyTitle?: string;
        emptyDescription?: string;
    }>(),
    {
        total: 0,
        from: 0,
        to: 0,
        emptyTitle: 'Belum ada data',
        emptyDescription:
            'Tidak ada data yang cocok dengan kriteria pencarian.',
    },
);

defineEmits<{
    (e: 'page-change', url: string): void;
}>();
</script>

<template>
    <div
        class="w-full overflow-hidden rounded-2xl border border-[#eceae5] bg-white shadow-2xs transition-colors dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
    >
        <!-- Responsive Table Wrapper -->
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <!-- Table Header (bg #f4f3f0 / dark #262626) -->
                <thead>
                    <tr
                        class="border-b border-[#eceae5] bg-[#f4f3f0] text-xs font-bold text-[#5e5e5e] dark:border-[#2e2e2e] dark:bg-[#262626] dark:text-[#a3a3a3]"
                    >
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
                <tbody
                    class="divide-y divide-[#eceae5] text-sm text-[#191919] dark:divide-[#2e2e2e] dark:text-[#f0efea]"
                >
                    <tr
                        v-for="(item, index) in items"
                        :key="item.id ?? index"
                        class="transition-colors hover:bg-[#faf9f7] dark:hover:bg-[#262626]/60"
                    >
                        <slot name="row" :item="item" :index="index" />
                    </tr>

                    <!-- Empty State -->
                    <tr v-if="items.length === 0">
                        <td
                            :colspan="columns.length"
                            class="py-16 text-center text-gray-500 dark:text-[#a3a3a3]"
                        >
                            <slot name="empty">
                                <div
                                    class="flex flex-col items-center justify-center"
                                >
                                    <div
                                        class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-[#f4f3f0] text-gray-400 dark:bg-[#262626] dark:text-gray-500"
                                    >
                                        <Inbox class="h-6 w-6" />
                                    </div>
                                    <p
                                        class="text-sm font-bold text-gray-900 dark:text-[#f0efea]"
                                    >
                                        {{ emptyTitle }}
                                    </p>
                                    <p
                                        class="mt-1 max-w-xs text-xs text-gray-400 dark:text-[#a3a3a3]"
                                    >
                                        {{ emptyDescription }}
                                    </p>
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
            class="flex items-center justify-between border-t border-[#eceae5] px-6 py-3.5 text-xs font-medium text-[#5e5e5e] dark:border-[#2e2e2e] dark:text-[#a3a3a3]"
        >
            <div>
                <span
                    >{{ from ?? 1 }} sampai {{ to ?? items.length }} dari
                    {{ total }}</span
                >
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    :disabled="!prevUrl"
                    @click="prevUrl && $emit('page-change', prevUrl)"
                    aria-label="Sebelumnya"
                    class="flex h-10 w-10 cursor-pointer items-center justify-center rounded-xl border border-[#eceae5] bg-white text-[#191919] transition-all hover:bg-gray-50 active:scale-95 disabled:cursor-not-allowed disabled:opacity-30 dark:border-[#2e2e2e] dark:bg-[#262626] dark:text-[#f0efea] dark:hover:bg-[#333333]"
                >
                    <ChevronLeft class="h-4 w-4" />
                </button>

                <button
                    type="button"
                    :disabled="!nextUrl"
                    @click="nextUrl && $emit('page-change', nextUrl)"
                    aria-label="Berikutnya"
                    class="flex h-10 w-10 cursor-pointer items-center justify-center rounded-xl border border-[#eceae5] bg-white text-[#191919] transition-all hover:bg-gray-50 active:scale-95 disabled:cursor-not-allowed disabled:opacity-30 dark:border-[#2e2e2e] dark:bg-[#262626] dark:text-[#f0efea] dark:hover:bg-[#333333]"
                >
                    <ChevronRight class="h-4 w-4" />
                </button>
            </div>
        </div>
    </div>
</template>
