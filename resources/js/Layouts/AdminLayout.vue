<script setup lang="ts">
import { ref, computed } from "vue";
import { Link, usePage, router } from "@inertiajs/vue3";
import ThemeToggle from "@/Components/Common/ThemeToggle.vue";
import {
    LayoutDashboard,
    Users,
    BookOpen,
    UtensilsCrossed,
    CheckCircle2,
    LogOut,
    Search,
    Bell,
    ChevronDown,
    Menu,
    X,
    User as UserIcon,
} from "lucide-vue-next";

defineProps<{
    title?: string;
}>();

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);
const isProfileOpen = ref(false);
const isMobileMenuOpen = ref(false);

const pendingVerificationCount = computed(
    () => (page.props as any).pendingVerificationCount ?? 0,
);

const navItems = computed(() => [
    { name: "Dashboard", routeName: "admin.dashboard", icon: LayoutDashboard },
    { name: "Pengguna", routeName: "admin.users", icon: Users },
    { name: "Artikel", routeName: "admin.articles", icon: BookOpen },
    { name: "Data Makanan", routeName: "admin.foods", icon: UtensilsCrossed },
    {
        name: "Verifikasi",
        routeName: "admin.verifications",
        icon: CheckCircle2,
        badge:
            pendingVerificationCount.value > 0
                ? pendingVerificationCount.value
                : null,
    },
]);

const userInitials = computed(() => {
    if (!user.value?.name) return "AD";
    return user.value.name
        .split(" ")
        .map((n: string) => n[0])
        .slice(0, 2)
        .join("")
        .toUpperCase();
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
                <!-- Logo & Admin Role Badge -->
                <div class="px-2 mb-6">
                    <Link
                        :href="route('admin.dashboard')"
                        class="flex items-center mb-2.5"
                    >
                        <img
                            src="/assets/logo.png"
                            alt="CarePlate"
                            class="h-8 w-auto object-contain"
                        />
                        <span
                            class="text-xl font-bold tracking-tight text-gray-900 dark:text-[#f0efea]"
                            >arePlate</span
                        >
                    </Link>
                    <div
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-gray-100/90 dark:bg-[#262626] text-gray-600 dark:text-[#a3a3a3] rounded-full text-[11px] font-medium"
                    >
                        <span
                            class="w-1.5 h-1.5 rounded-full bg-emerald-500"
                        ></span>
                        <span>Admin CarePlate</span>
                    </div>
                </div>

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
                        class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all"
                        :class="
                            route().current(item.routeName)
                                ? 'bg-[#e7f5ec] dark:bg-[#17321f] text-[#0f6b36] dark:text-[#3fcb7a] font-semibold'
                                : 'text-gray-600 dark:text-[#a3a3a3] hover:bg-gray-50 dark:hover:bg-[#262626] hover:text-gray-900 dark:hover:text-[#f0efea]'
                        "
                    >
                        <div class="flex items-center gap-3">
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
                        </div>
                        <span
                            v-if="item.badge"
                            class="px-2 py-0.5 text-[11px] font-bold rounded-full"
                            :class="
                                route().current(item.routeName)
                                    ? 'bg-[#0f6b36] text-white dark:bg-[#3fcb7a] dark:text-[#0e1a13]'
                                    : 'bg-gray-200 dark:bg-[#262626] text-gray-700 dark:text-gray-300'
                            "
                        >
                            {{ item.badge }}
                        </span>
                    </Link>
                </nav>
            </div>

            <!-- Bottom Logout -->
            <div class="pt-4 border-t border-gray-100 dark:border-[#2e2e2e]">
                <button
                    @click="logout"
                    class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium text-gray-600 dark:text-[#a3a3a3] hover:bg-red-50 dark:hover:bg-red-950/40 hover:text-red-600 dark:hover:text-red-400 transition-all text-left cursor-pointer"
                >
                    <LogOut
                        class="w-4 h-4 text-gray-400 dark:text-gray-500 group-hover:text-red-500"
                    />
                    <span>Keluar</span>
                </button>
            </div>
        </aside>

        <!-- Mobile Header -->
        <header
            class="md:hidden flex items-center justify-between bg-white dark:bg-[#1c1c1c] px-4 py-3 border-b border-gray-100 dark:border-[#2e2e2e] transition-colors"
        >
            <div class="flex items-center">
                <img
                    src="/assets/logo.png"
                    alt="CarePlate"
                    class="h-7 w-auto"
                />
                <span class="font-bold text-gray-900 dark:text-[#f0efea]"
                    >arePlate</span
                >
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
                class="flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium"
                :class="
                    route().current(item.routeName)
                        ? 'bg-[#e7f5ec] dark:bg-[#17321f] text-[#0f6b36] dark:text-[#3fcb7a]'
                        : 'text-gray-600 dark:text-gray-300'
                "
            >
                <div class="flex items-center gap-3">
                    <component :is="item.icon" class="w-4 h-4" />
                    <span>{{ item.name }}</span>
                </div>
                <span
                    v-if="item.badge"
                    class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-gray-200 dark:bg-[#262626] text-gray-700 dark:text-gray-300"
                >
                    {{ item.badge }}
                </span>
            </Link>

            <Link
                :href="route('admin.profile.edit')"
                @click="isMobileMenuOpen = false"
                class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium"
                :class="
                    route().current('admin.profile.edit')
                        ? 'bg-[#e7f5ec] dark:bg-[#17321f] text-[#0f6b36] dark:text-[#3fcb7a]'
                        : 'text-gray-600 dark:text-gray-300'
                "
            >
                <UserIcon class="w-4 h-4" />
                <span>Profil Saya</span>
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
                class="bg-white/70 dark:bg-[#1c1c1c]/80 backdrop-blur-md sticky top-0 z-20 border-b border-gray-100 dark:border-[#2e2e2e] px-6 py-3.5 flex items-center justify-end gap-4 transition-colors"
            >
                <!-- Right Side: Theme Toggle, Bell & Admin Dropdown -->
                <div class="flex items-center gap-3 shrink-0">
                    <!-- Dark Mode Toggle Button -->
                    <ThemeToggle />

                    <button
                        class="p-2 rounded-xl hover:bg-gray-100 dark:hover:bg-[#262626] text-gray-500 dark:text-gray-400 transition-colors relative cursor-pointer"
                        title="Notifikasi"
                    >
                        <Bell class="w-4 h-4" />
                    </button>

                    <!-- Admin Profile Dropdown -->
                    <div class="relative">
                        <button
                            @click="isProfileOpen = !isProfileOpen"
                            class="flex items-center gap-2.5 p-1 rounded-full hover:bg-gray-50 dark:hover:bg-[#262626] transition-colors text-left cursor-pointer"
                        >
                            <div
                                class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-800 dark:bg-[#17321f] dark:text-[#3fcb7a] font-bold text-xs flex items-center justify-center shrink-0"
                            >
                                {{ userInitials }}
                            </div>
                            <div
                                class="hidden sm:block text-xs leading-tight pr-1"
                            >
                                <div
                                    class="font-semibold text-gray-900 dark:text-[#f0efea]"
                                >
                                    {{ user?.name ?? "Admin CarePlate" }}
                                </div>
                                <div
                                    class="text-[11px] text-gray-400 dark:text-gray-500"
                                >
                                    {{ user?.email ?? "admin@careplate.id" }}
                                </div>
                            </div>
                            <ChevronDown
                                class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500"
                            />
                        </button>

                        <div
                            v-if="isProfileOpen"
                            @click.outside="isProfileOpen = false"
                            class="absolute right-0 mt-2 w-48 bg-white dark:bg-[#1c1c1c] rounded-xl shadow-lg border border-gray-100 dark:border-[#2e2e2e] py-1 z-30"
                        >
                            <Link
                                :href="route('admin.profile.edit')"
                                @click="isProfileOpen = false"
                                class="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-[#f0efea] hover:bg-gray-50 dark:hover:bg-[#262626] text-left transition-colors"
                            >
                                <UserIcon
                                    class="w-4 h-4 text-gray-400 dark:text-gray-500"
                                />
                                <span>Profil Saya</span>
                            </Link>

                            <div
                                class="my-1 border-t border-gray-100 dark:border-[#2e2e2e]"
                            ></div>

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
