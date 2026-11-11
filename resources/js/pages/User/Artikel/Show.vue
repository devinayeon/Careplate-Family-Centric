<script setup lang="ts">
import { computed } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import UserLayout from "@/Layouts/UserLayout.vue";
import {
    ArrowLeft,
    Clock,
    Calendar,
    Eye,
    ScanLine,
    ArrowRight,
    Share2,
    CheckCircle2,
} from "lucide-vue-next";

interface ArticleDetail {
    id: number;
    judul: string;
    slug: string;
    ringkasan: string | null;
    konten: string;
    kategori: string | null;
    foto_sampul_url: string | null;
    nama_penulis: string;
    waktu_baca: string;
    tanggal_terbit: string;
    views: number;
}

interface ArticleRecommendation {
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

const props = defineProps<{
    article: ArticleDetail;
    relatedArticles: ArticleRecommendation[];
}>();

const authorInitials = computed(() => {
    if (!props.article.nama_penulis) return "TR";
    return props.article.nama_penulis
        .split(" ")
        .map((n) => n[0])
        .slice(0, 2)
        .join("")
        .toUpperCase();
});

const defaultCover =
    "https://images.unsplash.com/photo-1540420773420-3366772f4999?w=1200&auto=format&fit=crop&q=80";

const handleShare = async () => {
    if (navigator.share) {
        try {
            await navigator.share({
                title: props.article.judul,
                text: props.article.ringkasan || props.article.judul,
                url: window.location.href,
            });
        } catch {
            // User cancelled or share failed
        }
    } else {
        navigator.clipboard.writeText(window.location.href);
        alert("Tautan artikel berhasil disalin ke clipboard!");
    }
};
</script>

<template>
    <UserLayout :title="article.judul">
        <Head :title="`${article.judul} - CarePlate`">
            <meta name="description" :content="article.ringkasan || article.judul" />
        </Head>

        <div class="max-w-3xl mx-auto py-4 sm:py-6 space-y-8 pb-16">
            <!-- 1. Back Navigation (Figma 18:3761) -->
            <div class="flex items-center justify-between">
                <Link
                    :href="route('user.articles')"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-gray-600 dark:text-[#a3a3a3] hover:text-[#0b8043] dark:hover:text-[#3fcb7a] transition-colors group cursor-pointer"
                >
                    <ArrowLeft class="w-4 h-4 group-hover:-translate-x-1 transition-transform" />
                    <span>Artikel</span>
                </Link>

                <button
                    type="button"
                    @click="handleShare"
                    class="p-2 rounded-xl text-gray-500 dark:text-[#a3a3a3] hover:bg-gray-100 dark:hover:bg-[#262626] hover:text-gray-900 dark:hover:text-[#f0efea] transition cursor-pointer"
                    title="Bagikan artikel ini"
                >
                    <Share2 class="w-4 h-4" />
                </button>
            </div>

            <!-- 2. Header: Category & Big Title (Figma 18:3762, 18:3763) -->
            <div class="space-y-3">
                <div class="inline-block">
                    <span
                        class="text-xs sm:text-sm font-bold text-[#0b8043] dark:text-[#3fcb7a] uppercase tracking-wider"
                    >
                        {{ article.kategori || "Gizi Edukasi" }}
                    </span>
                </div>

                <h1
                    class="text-2xl sm:text-4xl md:text-5xl font-extrabold tracking-tight text-gray-900 dark:text-[#f0efea] leading-tight"
                >
                    {{ article.judul }}
                </h1>
            </div>

            <!-- 3. Author Profile & Reading Meta (Figma 18:3769) -->
            <div class="flex items-center gap-3.5 pb-2">
                <div
                    class="w-10 h-10 rounded-full bg-emerald-100 dark:bg-[#17321f] text-[#0b8043] dark:text-[#3fcb7a] font-bold text-xs flex items-center justify-center shrink-0 border border-emerald-200/60 dark:border-[#2e2e2e]"
                >
                    {{ authorInitials }}
                </div>
                <div>
                    <div class="text-sm font-semibold text-gray-900 dark:text-[#f0efea]">
                        {{ article.nama_penulis }}
                    </div>
                    <div class="text-xs text-gray-400 dark:text-[#737373] flex items-center gap-2 mt-0.5">
                        <span>{{ article.waktu_baca }}</span>
                        <span>•</span>
                        <span>{{ article.tanggal_terbit }}</span>
                        <span>•</span>
                        <span>{{ article.views }} pembaca</span>
                    </div>
                </div>
            </div>

            <!-- 4. Featured Cover Photo Banner (Figma 18:3770) -->
            <div class="relative overflow-hidden rounded-3xl bg-gray-100 dark:bg-[#262626] border border-gray-100 dark:border-[#2e2e2e] shadow-xs">
                <img
                    :src="article.foto_sampul_url || defaultCover"
                    :alt="article.judul"
                    class="w-full aspect-16/9 sm:max-h-[440px] object-cover"
                />
            </div>

            <!-- Excerpt Lead (if present) -->
            <p
                v-if="article.ringkasan"
                class="text-base sm:text-lg text-gray-600 dark:text-[#a3a3a3] font-medium leading-relaxed italic border-l-4 border-[#0b8043] dark:border-[#3fcb7a] pl-4 py-1"
            >
                {{ article.ringkasan }}
            </p>

            <!-- 5. Full TipTap Article Content (Figma 18:3771) -->
            <div
                class="article-body font-sans text-gray-800 dark:text-[#d4d4d4] text-base sm:text-lg leading-relaxed space-y-4"
                v-html="article.konten"
            ></div>

            <!-- 6. Call to Action Banner (Figma 18:3793) -->
            <div
                class="bg-[#fff2eb] dark:bg-[#261f1c] border border-orange-100 dark:border-[#3d2c26] rounded-2xl p-4 sm:p-5 flex flex-col sm:flex-row items-center justify-between gap-4 transition-colors shadow-2xs mt-10"
            >
                <div class="flex items-center gap-3.5 text-center sm:text-left">
                    <div class="w-10 h-10 rounded-xl bg-orange-100/80 dark:bg-[#3a261c] text-[#eb4416] dark:text-[#ff7a4d] flex items-center justify-center shrink-0">
                        <ScanLine class="w-5 h-5" />
                    </div>
                    <p class="text-sm sm:text-base font-semibold text-gray-900 dark:text-[#f0efea]">
                        Ragu dengan camilan kemasan di kantin? Pindai labelnya dulu.
                    </p>
                </div>

                <Link
                    :href="route('user.scan')"
                    class="shrink-0 px-6 py-2.5 rounded-full border-2 border-gray-900 dark:border-[#f0efea] text-gray-900 dark:text-[#f0efea] hover:bg-gray-900 hover:text-white dark:hover:bg-[#f0efea] dark:hover:text-[#121212] text-xs sm:text-sm font-bold transition-all shadow-xs cursor-pointer text-center w-full sm:w-auto"
                >
                    Pindai Label
                </Link>
            </div>

            <!-- 7. Related Articles Recommendation Section -->
            <div v-if="relatedArticles.length > 0" class="pt-8 border-t border-gray-100 dark:border-[#2e2e2e] space-y-5">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-[#f0efea]">
                        Rekomendasi Artikel Lainnya
                    </h3>
                    <Link
                        :href="route('user.articles')"
                        class="text-xs font-semibold text-[#0b8043] dark:text-[#3fcb7a] hover:underline"
                    >
                        Lihat Semua
                    </Link>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <article
                        v-for="rel in relatedArticles"
                        :key="rel.id"
                        class="bg-white dark:bg-[#1c1c1c] border border-gray-100 dark:border-[#2e2e2e] rounded-2xl overflow-hidden hover:shadow-md transition-all flex flex-col group"
                    >
                        <Link :href="route('user.articles.show', rel.slug)" class="flex flex-col h-full">
                            <div class="aspect-16/10 overflow-hidden bg-gray-100 dark:bg-[#262626]">
                                <img
                                    :src="rel.foto_sampul_url || defaultCover"
                                    :alt="rel.judul"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                    loading="lazy"
                                />
                            </div>
                            <div class="p-4 flex-1 flex flex-col justify-between">
                                <div>
                                    <span class="text-[11px] font-semibold text-[#0b8043] dark:text-[#3fcb7a] block mb-1">
                                        {{ rel.kategori || "Gizi Edukasi" }}
                                    </span>
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-[#f0efea] group-hover:text-[#0b8043] dark:group-hover:text-[#3fcb7a] transition-colors line-clamp-2 leading-snug">
                                        {{ rel.judul }}
                                    </h4>
                                </div>
                                <div class="mt-3 text-[11px] text-gray-400 dark:text-[#737373]">
                                    {{ rel.waktu_baca }}
                                </div>
                            </div>
                        </Link>
                    </article>
                </div>
            </div>
        </div>
    </UserLayout>
</template>

<style>
/* Styling typography rendering untuk konten TipTap artikel */
.article-body h1 {
    font-size: 1.875rem;
    line-height: 2.25rem;
    font-weight: 800;
    margin-top: 2rem;
    margin-bottom: 1rem;
    color: #191919;
}

.article-body h2 {
    font-size: 1.5rem;
    line-height: 2rem;
    font-weight: 700;
    margin-top: 1.75rem;
    margin-bottom: 0.75rem;
    color: #191919;
}

.article-body h3 {
    font-size: 1.25rem;
    line-height: 1.75rem;
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 0.5rem;
    color: #191919;
}

.article-body p {
    margin: 1rem 0;
    line-height: 1.8;
}

.article-body blockquote {
    border-left: 4px solid #0b8043;
    padding-left: 1.25rem;
    margin: 1.5rem 0;
    font-style: italic;
    color: #4b5563;
}

.article-body ul {
    list-style-type: disc;
    padding-left: 1.5rem;
    margin: 1rem 0;
}

.article-body ol {
    list-style-type: decimal;
    padding-left: 1.5rem;
    margin: 1rem 0;
}

.article-body li {
    margin: 0.5rem 0;
}

.article-body hr {
    border: none;
    border-top: 1px solid #e5e7eb;
    margin: 2rem 0;
}

.article-body a {
    color: #0b8043;
    text-decoration: underline;
    text-underline-offset: 3px;
    font-weight: 500;
}

.article-body a:hover {
    color: #096836;
}

.article-body img {
    border-radius: 1.5rem;
    max-width: 100%;
    height: auto;
    margin: 2rem auto;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.article-body iframe {
    border-radius: 1.5rem;
    max-width: 100%;
    margin: 2rem auto;
    aspect-ratio: 16 / 9;
}

/* Dark mode typography */
.dark .article-body h1,
.dark .article-body h2,
.dark .article-body h3,
.dark .article-body strong {
    color: #f0efea !important;
}

.dark .article-body p,
.dark .article-body li {
    color: #d4d4d4 !important;
}

.dark .article-body blockquote {
    border-left-color: #3fcb7a !important;
    color: #a3a3a3 !important;
}

.dark .article-body hr {
    border-top-color: #2e2e2e !important;
}

.dark .article-body a {
    color: #3fcb7a !important;
}

.dark .article-body a:hover {
    color: #34aa66 !important;
}
</style>
