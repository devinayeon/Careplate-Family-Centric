<script setup lang="ts">
import { ref, computed } from "vue";
import { Link, usePage, router } from "@inertiajs/vue3";
import ThemeToggle from "@/Components/Common/ThemeToggle.vue";
import {
    Home,
    Calendar,
    ScanLine,
    MessageCircle,
    Calculator,
    UtensilsCrossed,
    BookOpen,
    Stethoscope,
    Users,
    UserCircle,
    Search,
    Bell,
    Flame,
    ChevronDown,
    LogOut,
    Menu,
    X,
} from "lucide-vue-next";

defineProps<{
    title?: string;
}>();

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);
const isProfileOpen = ref(false);
const isMobileMenuOpen = ref(false);

const navItems = [
    { name: "Beranda", routeName: "user.dashboard", icon: Home },
    { name: "Catatan Makanan", routeName: "user.meals", icon: Calendar },
    { name: "Pindai Kemasan", routeName: "user.scan", icon: ScanLine },
    { name: "Chat Gizi", routeName: "user.chat_gizi", icon: MessageCircle },
    { name: "Kalkulator", routeName: "user.calculator", icon: Calculator },
    { name: "Data Makanan", routeName: "user.foods", icon: UtensilsCrossed },
    { name: "Artikel", routeName: "user.articles", icon: BookOpen },
    { name: "Konsultasi", routeName: "user.consultation", icon: Stethoscope },
    { name: "Keluarga", routeName: "user.family", icon: Users },
];

const userInitials = computed(() => {
    if (!user.value?.name) return "U";
    return user.value.name
        .split(" ")
        .map((n: string) => n[0])
        .slice(0, 2)
        .join("")
        .toUpperCase();
});

const familyStatus = computed(() => {
    return user.value?.keluarga?.nama_keluarga ?? "Belum ada grup";
});

const logout = () => {
    router.post(route("logout"));
};
</script>

<template>
    <div
        class="min-h-screen bg-[#f7f8f9] dark:bg-[#121212] flex flex-col md:flex-row font-sans text-gray-800 dark:text-[#f0efea] transition-colors duration-200"
    >
        <!-- Desktop Sidebar -->
        <aside
            class="hidden md:flex md:w-64 flex-col bg-white dark:bg-[#1c1c1c] border-r border-gray-100 dark:border-[#2e2e2e] h-screen sticky top-0 p-5 shrink-0 justify-between overflow-y-auto custom-scrollbar transition-colors z-30"
        >
            <div>
                <!-- Logo -->
                <Link
                    :href="route('user.dashboard')"
                    class="flex items-center px-2 mb-6"
                >
                    <img
                        src="/assets/logo.png"
                        alt="CarePlate"
                        class="h-8 w-auto object-contain"
                    />
                    <span class="text-xl font-bold tracking-tight text-gray-900 dark:text-[#f0efea]"
                        >arePlate</span
                    >
                </Link>

                <!-- Navigation Menu -->
                <nav class="space-y-1">
                    <Link
                        v-for="item in navItems"
                        :key="item.name"
                        :href="
                            route().has(item.routeName)
                                ? route(item.routeName)
                                : '#'
                        "
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all"
                        :class="
                            route().current(item.routeName)
                                ? 'bg-[#e7f5ec] dark:bg-[#17321f] text-[#0f6b36] dark:text-[#3fcb7a] font-semibold'
                                : 'text-gray-600 dark:text-[#a3a3a3] hover:bg-gray-50 dark:hover:bg-[#262626] hover:text-gray-900 dark:hover:text-[#f0efea]'
                        "
                    >
                        <component
                            :is="item.icon"
                            class="w-4 h-4 shrink-0"
                            :class="
                                route().current(item.routeName)
                                    ? 'text-[#0f6b36] dark:text-[#3fcb7a]'
                                    : 'text-gray-400 dark:text-gray-500'
                            "
                        />
                        <span>{{ item.name }}</span>
                    </Link>
                </nav>
            </div>

            <!-- Bottom Profile Link -->
            <div class="pt-4 border-t border-gray-100 dark:border-[#2e2e2e]">
                <Link
                    :href="route('profile.edit')"
                    class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium text-gray-600 dark:text-[#a3a3a3] hover:bg-gray-50 dark:hover:bg-[#262626] hover:text-gray-900 dark:hover:text-[#f0efea] transition-all"
                >
                    <UserCircle class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                    <span>Profil</span>
                </Link>
            </div>
        </aside>

        <!-- Mobile Header -->
        <header
            class="md:hidden flex items-center justify-between bg-white dark:bg-[#1c1c1c] px-4 py-3 border-b border-gray-100 dark:border-[#2e2e2e] transition-colors"
        >
            <div class="flex items-center gap-2">
                <img
                    src="/assets/logo.png"
                    alt="CarePlate"
                    class="h-7 w-auto"
                />
                <span class="font-bold text-gray-900 dark:text-[#f0efea]">CarePlate</span>
            </div>
            <div class="flex items-center gap-2">
                <ThemeToggle />
                <button
                    @click="isMobileMenuOpen = !isMobileMenuOpen"
                    class="p-2 text-gray-600 dark:text-gray-300 rounded-lg"
                >
                    <Menu v-if="!isMobileMenuOpen" class="w-6 h-6" />
                    <X v-else class="w-6 h-6" />
                </button>
            </div>
        </header>

        <!-- Mobile Drawer -->
        <div
            v-if="isMobileMenuOpen"
            class="md:hidden bg-white dark:bg-[#1c1c1c] border-b border-gray-200 dark:border-[#2e2e2e] px-4 py-3 space-y-1"
        >
            <Link
                v-for="item in navItems"
                :key="item.name"
                :href="
                    route().has(item.routeName) ? route(item.routeName) : '#'
                "
                @click="isMobileMenuOpen = false"
                class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium"
                :class="
                    route().current(item.routeName)
                        ? 'bg-[#e7f5ec] dark:bg-[#17321f] text-[#0f6b36] dark:text-[#3fcb7a]'
                        : 'text-gray-600 dark:text-gray-300'
                "
            >
                <component :is="item.icon" class="w-4 h-4" />
                <span>{{ item.name }}</span>
            </Link>
            <Link
                :href="route('profile.edit')"
                @click="isMobileMenuOpen = false"
                class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-300"
            >
                <UserCircle class="w-4 h-4" />
                <span>Pengaturan Profil</span>
            </Link>
            <button
                @click="logout"
                class="w-full flex items-center gap-3 px-3 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40 text-left"
            >
                <LogOut class="w-4 h-4" />
                <span>Keluar</span>
            </button>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Top Bar -->
            <header
                class="bg-white/70 dark:bg-[#1c1c1c]/80 backdrop-blur-md sticky top-0 z-20 border-b border-gray-100 dark:border-[#2e2e2e] px-6 py-3.5 flex items-center justify-between gap-4 transition-colors"
            >
                <!-- Search bar -->
                <div class="relative flex-1 max-w-md">
                    <Search
                        class="w-4 h-4 text-gray-400 dark:text-gray-500 absolute left-3.5 top-1/2 -translate-y-1/2"
                    />
                    <input
                        type="text"
                        placeholder="Cari makanan, artikel, atau ahli gizi"
                        class="w-full bg-[#f4f5f6] dark:bg-[#262626] hover:bg-[#eceef0] dark:hover:bg-[#2e2e2e] focus:bg-white dark:focus:bg-[#1c1c1c] text-sm text-gray-800 dark:text-[#f0efea] placeholder-gray-400 dark:placeholder-gray-500 pl-10 pr-14 py-2 rounded-xl border border-transparent dark:border-[#2e2e2e] focus:ring-2 focus:ring-[#0f6b36]/30 transition-all outline-none"
                    />
                    <span
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] font-medium text-gray-400 dark:text-gray-500 bg-white dark:bg-[#1c1c1c] px-1.5 py-0.5 rounded border border-gray-200 dark:border-[#2e2e2e]"
                    >
                        Ctrl K
                    </span>
                </div>

                <!-- Right Side: Streak, Bell, ThemeToggle, User Pill -->
                <div class="flex items-center gap-3 shrink-0">
                    <!-- Streak Indicator -->
                    <div
                        class="flex items-center gap-1.5 px-3 py-1.5 bg-[#fff3ea] dark:bg-[#3a1e14] text-[#e35614] dark:text-[#ff7a4d] rounded-full text-xs font-semibold"
                    >
                        <Flame class="w-3.5 h-3.5 fill-current" />
                        <span>0 hari</span>
                    </div>

                    <!-- Theme Toggle -->
                    <ThemeToggle />

                    <!-- Notification Bell -->
                    <button
                        class="p-2 rounded-xl hover:bg-gray-100 dark:hover:bg-[#262626] text-gray-500 dark:text-gray-400 transition-colors relative cursor-pointer"
                        title="Notifikasi"
                    >
                        <Bell class="w-4 h-4" />
                    </button>

                    <!-- User Info Dropdown -->
                    <div class="relative">
                        <button
                            @click="isProfileOpen = !isProfileOpen"
                            class="flex items-center gap-2.5 p-1 rounded-full hover:bg-gray-50 dark:hover:bg-[#262626] transition-colors text-left cursor-pointer"
                        >
                            <div
                                class="w-8 h-8 rounded-full bg-[#e7f5ec] dark:bg-[#17321f] text-[#0f6b36] dark:text-[#3fcb7a] font-bold text-xs flex items-center justify-center shrink-0"
                            >
                                {{ userInitials }}
                            </div>
                            <div
                                class="hidden sm:block text-xs leading-tight pr-1"
                            >
                                <div class="font-semibold text-gray-900 dark:text-[#f0efea]">
                                    {{ user?.name ?? "Pengguna" }}
                                </div>
                                <div class="text-[11px] text-gray-400 dark:text-gray-500">
                                    {{ familyStatus }}
                                </div>
                            </div>
                            <ChevronDown class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500" />
                        </button>

                        <!-- Dropdown Menu -->
                        <div
                            v-if="isProfileOpen"
                            @click.outside="isProfileOpen = false"
                            class="absolute right-0 mt-2 w-48 bg-white dark:bg-[#1c1c1c] rounded-xl shadow-lg border border-gray-100 dark:border-[#2e2e2e] py-1 z-30"
                        >
                            <Link
                                :href="route('profile.edit')"
                                class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-[#f0efea] hover:bg-gray-50 dark:hover:bg-[#262626] transition-colors"
                            >
                                <UserCircle class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                                <span>Pengaturan Profil</span>
                            </Link>
                            <button
                                @click="logout"
                                class="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40 text-left transition-colors cursor-pointer"
                            >
                                <LogOut class="w-4 h-4" />
                                <span>Keluar</span>
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Slot Content -->
            <main class="flex-1 p-6 md:p-8 max-w-7xl w-full mx-auto">
                <slot />
            </main>
        </div>
    </div>
</template>
