<script setup lang="ts">
import { ref, watch } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import UserLayout from "@/Layouts/UserLayout.vue";
import { Search, X, BookOpen, Clock, Calendar, ArrowRight } from "lucide-vue-next";

interface ArticleItem {
    id: number;
    judul: string;
    slug: string;
    ringkasan: string | null;
    kategori: string | null;
    foto_sampul_url: string | null;
    nama_penulis: string;
    waktu_baca: string;
    tanggal_terbit: string;
    views: number;
}

interface CategoryItem {
    id: number;
    nama: string;
    slug: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedArticles {
    data: ArticleItem[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
}

const props = defineProps<{
    featuredArticle: ArticleItem | null;
    articles: PaginatedArticles;
    categories: CategoryItem[];
    filters: {
        kategori: string;
        search: string;
    };
}>();

const currentCategory = ref(props.filters.kategori || "semua");
const searchQuery = ref(props.filters.search || "");
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const applyFilter = (category: string) => {
    currentCategory.value = category;
    fetchArticles();
};

const handleSearchInput = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetchArticles();
    }, 350);
};

const clearSearch = () => {
    searchQuery.value = "";
    fetchArticles();
};

const resetAllFilters = () => {
    currentCategory.value = "semua";
    searchQuery.value = "";
    fetchArticles();
};

const fetchArticles = () => {
    router.get(
        route("user.articles"),
        {
            kategori: currentCategory.value !== "semua" ? currentCategory.value : undefined,
            search: searchQuery.value.trim() ? searchQuery.value.trim() : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

// Fallback image helper
const getPlaceholderImage = (index: number) => {
    const placeholders = [
        "https://images.unsplash.com/photo-1540420773420-3366772f4999?w=800&auto=format&fit=crop&q=80",
        "https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=800&auto=format&fit=crop&q=80",
        "https://images.unsplash.com/photo-1498837167922-ddd27525d352?w=800&auto=format&fit=crop&q=80",
        "https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=800&auto=format&fit=crop&q=80",
    ];
    return placeholders[index % placeholders.length];
};
</script>

<template>
    <UserLayout title="Artikel">
        <Head title="Artikel Edukasi Gizi - CarePlate" />

        <div class="space-y-8 pb-12">
            <!-- 1. Header Hero Banner (Figma 18:3621) -->
            <div
                class="relative overflow-hidden rounded-3xl bg-[#fff2eb] dark:bg-[#261f1c] border border-orange-100/70 dark:border-[#3d2c26] p-6 sm:p-8 md:p-10 transition-colors shadow-xs"
            >
                <!-- Subtle decorative background circle -->
                <div
                    class="absolute -right-16 -top-16 w-64 h-64 rounded-full bg-orange-200/30 dark:bg-orange-500/5 blur-2xl pointer-events-none"
                ></div>

                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="max-w-xl">
                        <h1
                            class="font-display text-3xl sm:text-4xl md:text-5xl font-extrabold tracking-tight text-[#191919] dark:text-[#f0efea]"
                        >
                            Artikel
                        </h1>
                        <p class="mt-2 text-sm sm:text-base text-gray-600 dark:text-[#a3a3a3] leading-relaxed">
                            Bacaan singkat dan panduan praktis seputar pola makan seimbang, resep ramah keluarga, serta edukasi gizi harian.
                        </p>
                    </div>

                    <!-- 3D Bowl / Salad Illustration (Figma 55:10665) -->
                    <div class="shrink-0 flex items-center justify-center md:pr-4">
                        <div class="relative w-28 h-28 sm:w-32 sm:h-32 flex items-center justify-center">
                            <!-- Stylized SVG Salad Bowl that matches Figma aesthetic -->
                            <svg viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full drop-shadow-md">
                                <circle cx="60" cy="60" r="54" fill="#FFE3D4" fill-opacity="0.5" class="dark:fill-[#3a261c]" />
                                <!-- Greens inside bowl -->
                                <ellipse cx="60" cy="50" rx="36" ry="24" fill="#3fcb7a" />
                                <circle cx="48" cy="42" r="10" fill="#2eb367" />
                                <circle cx="72" cy="44" r="9" fill="#1b8849" />
                                <circle cx="60" cy="38" r="11" fill="#3fcb7a" />
                                <!-- Tomato slice -->
                                <circle cx="44" cy="48" r="6" fill="#eb4416" />
                                <circle cx="75" cy="48" r="5" fill="#eb4416" />
                                <!-- Egg / protein slice -->
                                <ellipse cx="60" cy="48" rx="8" ry="6" fill="#FFFDF5" />
                                <circle cx="60" cy="48" r="3.5" fill="#FFC933" />
                                <!-- Terracotta / Coral Ceramic Bowl -->
                                <path
                                    d="M20 54C20 76.0914 37.9086 94 60 94C82.0914 94 100 76.0914 100 54H20Z"
                                    fill="#E55934"
                                />
                                <!-- Bowl Rim Highlight -->
                                <ellipse cx="60" cy="54" rx="40" ry="8" fill="#F06F4D" />
                                <!-- Bowl Foot Base -->
                                <path
                                    d="M48 94C48 97.3137 53.3726 100 60 100C66.6274 100 72 97.3137 72 94H48Z"
                                    fill="#C73F1C"
                                />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Category Tabs & Search Bar Toolbar (Figma 18:3625) -->
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-gray-200 dark:border-[#2e2e2e] pb-3">
                <!-- Categories Bar -->
                <div class="flex items-center gap-2 overflow-x-auto no-scrollbar scroll-smooth py-1 -mb-3">
                    <button
                        type="button"
                        @click="applyFilter('semua')"
                        class="px-4 py-2.5 text-sm whitespace-nowrap transition-all border-b-2 font-medium cursor-pointer"
                        :class="
                            currentCategory === 'semua'
                                ? 'border-[#0b8043] dark:border-[#3fcb7a] text-[#0b8043] dark:text-[#3fcb7a] font-bold'
                                : 'border-transparent text-gray-500 dark:text-[#a3a3a3] hover:text-gray-900 dark:hover:text-[#f0efea]'
                        "
                    >
                        Semua
                    </button>
                    <button
                        v-for="cat in categories"
                        :key="cat.id"
                        type="button"
                        @click="applyFilter(cat.nama)"
                        class="px-4 py-2.5 text-sm whitespace-nowrap transition-all border-b-2 font-medium cursor-pointer"
                        :class="
                            currentCategory === cat.nama
                                ? 'border-[#0b8043] dark:border-[#3fcb7a] text-[#0b8043] dark:text-[#3fcb7a] font-bold'
                                : 'border-transparent text-gray-500 dark:text-[#a3a3a3] hover:text-gray-900 dark:hover:text-[#f0efea]'
                        "
                    >
                        {{ cat.nama }}
                    </button>
                </div>

                <!-- Integrated Search Bar -->
                <div class="relative w-full lg:w-72 shrink-0">
                    <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-[#737373]" />
                    <input
                        v-model="searchQuery"
                        @input="handleSearchInput"
                        type="text"
                        placeholder="Cari artikel gizi..."
                        class="w-full pl-10 pr-9 py-2 text-sm rounded-xl border border-gray-200 dark:border-[#2e2e2e] bg-white dark:bg-[#1c1c1c] text-gray-900 dark:text-[#f0efea] placeholder-gray-400 dark:placeholder-[#737373] focus:outline-none focus:border-[#0b8043] dark:focus:border-[#3fcb7a] focus:ring-2 focus:ring-[#0b8043]/15 transition shadow-2xs"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        @click="clearSearch"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600 dark:hover:text-[#f0efea]"
                        title="Hapus pencarian"
                    >
                        <X class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>

            <!-- 3. Featured Article (Figma 18:3644) -->
            <div
                v-if="featuredArticle"
                class="bg-white dark:bg-[#1c1c1c] border border-gray-100 dark:border-[#2e2e2e] rounded-3xl overflow-hidden shadow-xs hover:shadow-md transition-all group"
            >
                <Link
                    :href="route('user.articles.show', featuredArticle.slug)"
                    class="grid grid-cols-1 lg:grid-cols-12 min-h-[340px]"
                >
                    <!-- Left Photo Column (Figma 18:3645) -->
                    <div class="lg:col-span-6 xl:col-span-6 relative aspect-16/10 lg:aspect-auto overflow-hidden bg-gray-100 dark:bg-[#262626]">
                        <img
                            :src="featuredArticle.foto_sampul_url || getPlaceholderImage(0)"
                            :alt="featuredArticle.judul"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                            loading="lazy"
                        />
                        <div
                            class="absolute top-4 left-4 lg:hidden px-3 py-1 bg-black/60 backdrop-blur-md rounded-full text-xs font-semibold text-white"
                        >
                            {{ featuredArticle.kategori || "Gizi Keluarga" }}
                        </div>
                    </div>

                    <!-- Right Content Column (Figma 18:3646) -->
                    <div class="lg:col-span-6 xl:col-span-6 p-6 sm:p-8 md:p-10 flex flex-col justify-between">
                        <div>
                            <span
                                class="hidden lg:inline-block text-xs font-bold text-[#0b8043] dark:text-[#3fcb7a] uppercase tracking-wider mb-3"
                            >
                                {{ featuredArticle.kategori || "Gizi Keluarga" }}
                            </span>

                            <h2
                                class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-[#f0efea] group-hover:text-[#0b8043] dark:group-hover:text-[#3fcb7a] transition-colors leading-tight mb-4"
                            >
                                {{ featuredArticle.judul }}
                            </h2>

                            <p
                                v-if="featuredArticle.ringkasan"
                                class="text-sm sm:text-base text-gray-600 dark:text-[#a3a3a3] line-clamp-3 leading-relaxed mb-6"
                            >
                                {{ featuredArticle.ringkasan }}
                            </p>
                        </div>

                        <!-- Meta Row -->
                        <div class="pt-4 border-t border-gray-100 dark:border-[#262626] flex items-center justify-between text-xs text-gray-400 dark:text-[#737373]">
                            <div class="flex items-center gap-2">
                                <span>{{ featuredArticle.waktu_baca }}</span>
                                <span>•</span>
                                <span>{{ featuredArticle.tanggal_terbit }}</span>
                            </div>
                            <span class="inline-flex items-center gap-1 font-semibold text-[#0b8043] dark:text-[#3fcb7a] group-hover:translate-x-1 transition-transform">
                                Baca selengkapnya
                                <ArrowRight class="w-3.5 h-3.5" />
                            </span>
                        </div>
                    </div>
                </Link>
            </div>

            <!-- 4. Grid of Previous / Other Articles (Figma 18:3651) -->
            <div v-if="articles.data.length > 0" class="space-y-6">
                <div v-if="featuredArticle" class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-[#f0efea]">
                        Artikel Lainnya
                    </h3>
                    <span class="text-xs text-gray-400 dark:text-[#737373]">
                        Total {{ articles.total }} artikel
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <article
                        v-for="(item, idx) in articles.data"
                        :key="item.id"
                        class="bg-white dark:bg-[#1c1c1c] border border-gray-100 dark:border-[#2e2e2e] rounded-3xl overflow-hidden hover:shadow-md transition-all flex flex-col group"
                    >
                        <Link :href="route('user.articles.show', item.slug)" class="flex flex-col h-full">
                            <!-- Card Cover Photo -->
                            <div class="aspect-16/10 overflow-hidden bg-gray-100 dark:bg-[#262626] relative">
                                <img
                                    :src="item.foto_sampul_url || getPlaceholderImage(idx + 1)"
                                    :alt="item.judul"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                    loading="lazy"
                                />
                            </div>

                            <!-- Card Body -->
                            <div class="p-5 flex-1 flex flex-col justify-between">
                                <div>
                                    <span
                                        class="text-xs font-semibold text-[#0b8043] dark:text-[#3fcb7a] block mb-2"
                                    >
                                        {{ item.kategori || "Gizi Edukasi" }}
                                    </span>

                                    <h4
                                        class="text-base font-bold text-gray-900 dark:text-[#f0efea] group-hover:text-[#0b8043] dark:group-hover:text-[#3fcb7a] transition-colors line-clamp-2 leading-snug mb-2"
                                    >
                                        {{ item.judul }}
                                    </h4>

                                    <p
                                        v-if="item.ringkasan"
                                        class="text-xs text-gray-500 dark:text-[#a3a3a3] line-clamp-2 leading-relaxed"
                                    >
                                        {{ item.ringkasan }}
                                    </p>
                                </div>

                                <!-- Card Meta -->
                                <div class="mt-4 pt-3 border-t border-gray-50 dark:border-[#262626] flex items-center justify-between text-xs text-gray-400 dark:text-[#737373]">
                                    <span>{{ item.waktu_baca }}</span>
                                    <span>{{ item.tanggal_terbit }}</span>
                                </div>
                            </div>
                        </Link>
                    </article>
                </div>

                <!-- 5. Pagination Bar -->
                <div
                    v-if="articles.links.length > 3"
                    class="flex items-center justify-center gap-1.5 pt-6"
                >
                    <template v-for="(link, index) in articles.links" :key="index">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="min-w-9 h-9 px-3 flex items-center justify-center rounded-xl text-xs font-semibold transition"
                            :class="
                                link.active
                                    ? 'bg-[#0b8043] dark:bg-[#3fcb7a] text-white dark:text-[#121212] shadow-sm'
                                    : 'bg-white dark:bg-[#1c1c1c] text-gray-600 dark:text-[#a3a3a3] hover:bg-gray-100 dark:hover:bg-[#262626] border border-gray-200 dark:border-[#2e2e2e]'
                            "
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="min-w-9 h-9 px-3 flex items-center justify-center rounded-xl text-xs text-gray-300 dark:text-gray-600 cursor-not-allowed border border-gray-100 dark:border-[#262626]"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>

            <!-- Empty State when no articles exist -->
            <div
                v-else-if="!featuredArticle"
                class="py-16 text-center bg-white dark:bg-[#1c1c1c] border border-gray-100 dark:border-[#2e2e2e] rounded-3xl p-8"
            >
                <div class="w-14 h-14 rounded-full bg-emerald-50 dark:bg-[#17321f] text-[#0b8043] dark:text-[#3fcb7a] mx-auto flex items-center justify-center mb-4">
                    <BookOpen class="w-7 h-7" />
                </div>
                <h3 class="text-base font-bold text-gray-900 dark:text-[#f0efea] mb-1">
                    Tidak ada artikel ditemukan
                </h3>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-[#a3a3a3] max-w-md mx-auto mb-5">
                    {{
                        searchQuery
                            ? `Tidak ada artikel yang cocok dengan kata kunci "${searchQuery}".`
                            : 'Belum ada artikel yang diterbitkan untuk kategori ini.'
                    }}
                </p>
                <button
                    type="button"
                    @click="resetAllFilters"
                    class="px-5 py-2.5 rounded-xl bg-[#0b8043] dark:bg-[#3fcb7a] text-white dark:text-[#121212] text-xs font-semibold hover:bg-[#096836] dark:hover:bg-[#34aa66] transition cursor-pointer"
                >
                    Reset Filter Pencarian
                </button>
            </div>
        </div>
    </UserLayout>
</template>

<style scoped>
/* Sembunyikan scrollbar horizontal untuk category tab bar */
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
