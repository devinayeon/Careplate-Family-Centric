<script setup lang="ts">
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { CheckCircle2, Plus, Search, Utensils } from 'lucide-vue-next'
import UserLayout from '@/Layouts/UserLayout.vue'
import { categoryIcon, idNumber } from '@/lib/foodUi'

interface FoodCard { id: number; nama: string; energi: number; protein: number; takaran_saji: string; foto_makanan_url: string | null }
interface Paginated { data: FoodCard[]; links: Array<{ url: string | null; label: string; active: boolean }>; total: number }

const props = defineProps<{
    foods: Paginated
    categories: Array<{ id: number; kategori: string; total: number }>
    filters: { search: string; kategori: number | null }
}>()

const search = ref(props.filters.search)
let timer: ReturnType<typeof setTimeout> | undefined

function go(next: { search?: string; kategori?: number | null }) {
    const query = { search: next.search ?? search.value, kategori: next.kategori === undefined ? props.filters.kategori : next.kategori }
    router.get(route('user.foods'), { ...(query.search ? { search: query.search } : {}), ...(query.kategori ? { kategori: query.kategori } : {}) }, { preserveScroll: true, preserveState: true, replace: true })
}

watch(search, (value) => {
    clearTimeout(timer)
    timer = setTimeout(() => go({ search: value }), 300)
})
</script>

<template>
    <Head title="Data Makanan" />
    <UserLayout>
        <div class="flex flex-col gap-6">
            <header class="flex flex-wrap items-center justify-between gap-4 rounded-3xl bg-[#e4f6ea] dark:bg-[#17321f] px-7 py-6">
                <div>
                    <h1 class="font-display text-3xl font-extrabold text-[#191919] dark:text-[#f0efea]">Data Makanan</h1>
                    <p class="mt-1 text-base text-[#5e5e5e] dark:text-[#a3a3a3]">Nilai gizi makanan rumahan Indonesia, diverifikasi Admin.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <Link :href="route('user.foods.mine')" class="text-sm font-semibold text-[#0b8043] dark:text-[#3fcb7a] underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-[#191919]">Pengajuan saya</Link>
                    <Link :href="route('user.foods.create')" class="inline-flex h-11 items-center gap-2 rounded-full border-2 border-[#191919] dark:border-[#f0efea] bg-white dark:bg-[#1c1c1c] px-5 text-sm font-semibold text-[#191919] dark:text-[#f0efea] hover:bg-gray-50 dark:hover:bg-[#262626] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919]">
                        <Plus class="h-4 w-4" aria-hidden="true" /> Ajukan Data Makanan
                    </Link>
                </div>
            </header>

            <nav aria-label="Kategori makanan" class="flex gap-3 overflow-x-auto pb-1">
                <button v-for="c in categories" :key="c.id" type="button" class="flex w-28 shrink-0 flex-col items-center gap-2 rounded-2xl border-2 px-3 py-3 text-sm font-semibold text-[#191919] dark:text-[#f0efea] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919]" :class="filters.kategori === c.id ? 'border-[#191919] dark:border-[#f0efea] bg-[#e4f6ea] dark:bg-[#17321f]' : 'border-transparent bg-white dark:bg-[#1c1c1c] hover:bg-[#f4f3f0] dark:hover:bg-[#262626]'" :aria-pressed="filters.kategori === c.id" @click="go({ kategori: filters.kategori === c.id ? null : c.id })">
                    <component :is="categoryIcon(c.kategori)" class="h-7 w-7 text-[#0b8043] dark:text-[#3fcb7a]" aria-hidden="true" />
                    <span class="text-center leading-tight">{{ c.kategori }}</span>
                    <span class="text-xs font-normal text-[#5e5e5e] dark:text-[#a3a3a3]">{{ c.total }} makanan</span>
                </button>
            </nav>

            <div class="relative max-w-md">
                <label for="food-search" class="sr-only">Cari makanan</label>
                <Search class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#5e5e5e] dark:text-[#a3a3a3]" aria-hidden="true" />
                <input id="food-search" v-model="search" type="search" placeholder="Cari nama makanan" class="h-11 w-full rounded-full border-0 bg-white dark:bg-[#1c1c1c] pl-11 pr-4 text-base text-[#191919] dark:text-[#f0efea] placeholder:text-[#5e5e5e] focus:ring-2 focus:ring-[#0b8043]/30" />
            </div>

            <section v-if="foods.data.length" aria-label="Daftar makanan">
                <p class="mb-3 text-sm font-semibold text-[#0b8043] dark:text-[#3fcb7a]">{{ foods.total }} makanan</p>
                <ul class="grid grid-cols-2 gap-5 md:grid-cols-3 xl:grid-cols-4">
                    <li v-for="f in foods.data" :key="f.id">
                        <Link :href="route('user.foods.show', f.id)" class="group block focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#191919]">
                            <div class="flex aspect-[4/3] items-center justify-center overflow-hidden rounded-2xl bg-[#f4f3f0] dark:bg-[#262626]">
                                <img v-if="f.foto_makanan_url" :src="f.foto_makanan_url" :alt="`Foto ${f.nama}`" class="h-full w-full object-cover" loading="lazy" />
                                <Utensils v-else class="h-10 w-10 text-[#d6d3cc] dark:text-[#3d3d3d]" aria-hidden="true" />
                            </div>
                            <h2 class="mt-3 font-display text-lg font-bold text-[#191919] dark:text-[#f0efea] group-hover:underline">{{ f.nama }}</h2>
                            <p class="text-sm text-[#5e5e5e] dark:text-[#a3a3a3]">{{ idNumber.format(f.energi) }} kkal · {{ idNumber.format(f.protein) }} g protein · {{ f.takaran_saji }}</p>
                            <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-[#e6f6ec] dark:bg-[#17321f] px-2.5 py-1 text-xs font-semibold text-[#0b7a46] dark:text-[#3fcb7a]"><CheckCircle2 class="h-3 w-3" aria-hidden="true" />Terverifikasi</span>
                        </Link>
                    </li>
                </ul>
                <nav v-if="foods.links.length > 3" class="mt-6 flex flex-wrap gap-2" aria-label="Halaman">
                    <template v-for="(l, i) in foods.links" :key="i">
                        <Link v-if="l.url" :href="l.url" preserve-scroll class="rounded-full px-4 py-2 text-sm font-semibold" :class="l.active ? 'bg-[#191919] text-white dark:bg-[#f0efea] dark:text-[#121212]' : 'bg-white dark:bg-[#1c1c1c] text-[#191919] dark:text-[#f0efea] hover:bg-[#f4f3f0] dark:hover:bg-[#262626]'" :aria-current="l.active ? 'page' : undefined"><span v-html="l.label" /></Link>
                    </template>
                </nav>
            </section>

            <section v-else class="flex flex-col items-center gap-3 rounded-3xl bg-white dark:bg-[#1c1c1c] px-6 py-16 text-center">
                <Utensils class="h-10 w-10 text-[#d6d3cc] dark:text-[#3d3d3d]" aria-hidden="true" />
                <h2 class="font-display text-xl font-bold text-[#191919] dark:text-[#f0efea]">Makanan tidak ditemukan</h2>
                <p class="max-w-sm text-sm text-[#5e5e5e] dark:text-[#a3a3a3]">Coba kata lain, atau ajukan makanannya supaya Admin bisa memeriksa dan menambahkannya.</p>
                <Link :href="route('user.foods.create')" class="mt-2 inline-flex h-11 items-center gap-2 rounded-full bg-[#0b8043] px-6 text-sm font-semibold text-white hover:bg-[#086637] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919]"><Plus class="h-4 w-4" aria-hidden="true" /> Ajukan Data Makanan</Link>
            </section>
        </div>
    </UserLayout>
</template>
