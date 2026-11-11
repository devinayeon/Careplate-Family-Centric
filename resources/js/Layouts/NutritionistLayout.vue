<script setup lang="ts">
import { ref, computed } from "vue";
import { Link, usePage, router } from "@inertiajs/vue3";
import ThemeToggle from "@/Components/Common/ThemeToggle.vue";
import {
    LayoutDashboard,
    Stethoscope,
    LogOut,
    Search,
    Bell,
    ChevronDown,
    Menu,
    X,
} from "lucide-vue-next";

defineProps<{
    title?: string;
    fullWidth?: boolean;
}>();

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);
const isProfileOpen = ref(false);
const isMobileMenuOpen = ref(false);

const navItems = [
    {
        name: "Dashboard",
        routeName: "nutritionist.dashboard",
        icon: LayoutDashboard,
    },
    {
        name: "Konsultasi",
        routeName: "nutritionist.consultations",
        icon: Stethoscope,
    },
];

const userInitials = computed(() => {
    if (!user.value?.name) return "AG";
    return user.value.name
        .replace("dr. ", "")
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
            class="hidden md:flex md:w-64 flex-col bg-white dark:bg-[#1c1c1c] border-r border-gray-100 dark:border-[#2e2e2e] min-h-screen p-5 shrink-0 justify-between transition-colors"
        >
            <div>
                <!-- Logo & Ahli Gizi Role Badge -->
                <div class="px-2 mb-6">
                    <Link
                        :href="route('nutritionist.dashboard')"
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
                            class="w-1.5 h-1.5 rounded-full bg-teal-500"
                        ></span>
                        <span>Ahli Gizi</span>
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
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all"
                        :class="
                            route().current(item.routeName)
                                ? 'bg-teal-50 dark:bg-teal-950/60 text-teal-800 dark:text-teal-300 font-semibold'
                                : 'text-gray-600 dark:text-[#a3a3a3] hover:bg-gray-50 dark:hover:bg-[#262626] hover:text-gray-900 dark:hover:text-[#f0efea]'
                        "
                    >
                        <component
                            :is="item.icon"
                            class="w-4 h-4 shrink-0"
                            :class="
                                route().current(item.routeName)
                                    ? 'text-teal-600 dark:text-teal-400'
                                    : 'text-gray-400 dark:text-gray-500'
                            "
                        />
                        <span>{{ item.name }}</span>
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
            <div class="flex items-center gap-2">
                <img
                    src="/assets/logo.png"
                    alt="CarePlate"
                    class="h-7 w-auto"
                />
                <span class="font-bold text-gray-900 dark:text-[#f0efea]">CarePlate Ahli Gizi</span>
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
                        ? 'bg-teal-50 dark:bg-teal-950/60 text-teal-800 dark:text-teal-300'
                        : 'text-gray-600 dark:text-gray-300'
                "
            >
                <component :is="item.icon" class="w-4 h-4" />
                <span>{{ item.name }}</span>
            </Link>
            <button
                @click="logout"
                class="w-full flex items-center gap-3 px-3 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40 text-left cursor-pointer"
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
                        placeholder="Cari klien atau konsultasi"
                        class="w-full bg-[#f4f5f6] dark:bg-[#262626] hover:bg-[#eceef0] dark:hover:bg-[#2e2e2e] focus:bg-white dark:focus:bg-[#1c1c1c] text-sm text-gray-800 dark:text-[#f0efea] placeholder-gray-400 dark:placeholder-gray-500 pl-10 pr-14 py-2 rounded-xl border border-transparent dark:border-[#2e2e2e] focus:ring-2 focus:ring-teal-600/30 transition-all outline-none"
                    />
                    <span
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] font-medium text-gray-400 dark:text-gray-500 bg-white dark:bg-[#1c1c1c] px-1.5 py-0.5 rounded border border-gray-200 dark:border-[#2e2e2e]"
                    >
                        Ctrl K
                    </span>
                </div>

                <!-- Right Side: ThemeToggle, Bell & Nutritionist Pill -->
                <div class="flex items-center gap-3 shrink-0">
                    <ThemeToggle />

                    <button
                        class="p-2 rounded-xl hover:bg-gray-100 dark:hover:bg-[#262626] text-gray-500 dark:text-gray-400 transition-colors relative cursor-pointer"
                        title="Notifikasi"
                    >
                        <Bell class="w-4 h-4" />
                    </button>

                    <!-- Nutritionist Profile Dropdown -->
                    <div class="relative">
                        <button
                            @click="isProfileOpen = !isProfileOpen"
                            class="flex items-center gap-2.5 p-1 rounded-full hover:bg-gray-50 dark:hover:bg-[#262626] transition-colors text-left cursor-pointer"
                        >
                            <div
                                class="w-8 h-8 rounded-full bg-teal-100 dark:bg-teal-950 text-teal-800 dark:text-teal-300 font-bold text-xs flex items-center justify-center shrink-0"
                            >
                                {{ userInitials }}
                            </div>
                            <div
                                class="hidden sm:block text-xs leading-tight pr-1"
                            >
                                <div class="font-semibold text-gray-900 dark:text-[#f0efea]">
                                    {{ user?.name ?? "dr. Sari Wulandari" }}
                                </div>
                                <div class="text-[11px] text-gray-400 dark:text-gray-500">
                                    Ahli Gizi
                                </div>
                            </div>
                            <ChevronDown class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500" />
                        </button>

                        <div
                            v-if="isProfileOpen"
                            @click.outside="isProfileOpen = false"
                            class="absolute right-0 mt-2 w-48 bg-white dark:bg-[#1c1c1c] rounded-xl shadow-lg border border-gray-100 dark:border-[#2e2e2e] py-1 z-30"
                        >
                            <button
                                @click="logout"
                                class="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40 text-left cursor-pointer transition-colors"
                            >
                                <LogOut class="w-4 h-4" />
                                <span>Keluar</span>
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Slot Content -->
            <main
                class="flex-1 w-full mx-auto"
                :class="fullWidth ? 'p-4 md:p-6' : 'p-6 md:p-8 max-w-7xl'"
            >
                <slot />
            </main>
        </div>
    </div>
</template>
