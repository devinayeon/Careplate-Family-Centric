<script setup lang="ts">
import { ref, computed } from "vue";
import { Link, usePage, router } from "@inertiajs/vue3";
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
        class="min-h-screen bg-[#f7f8f9] flex flex-col md:flex-row font-sans text-gray-800"
    >
        <!-- Desktop Sidebar -->
        <aside
            class="hidden md:flex md:w-64 flex-col bg-white border-r border-gray-100 min-h-screen p-5 shrink-0 justify-between"
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
                            class="text-xl font-bold tracking-tight text-gray-900"
                            >arePlate</span
                        >
                    </Link>
                    <div
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-gray-100/90 text-gray-600 rounded-full text-[11px] font-medium"
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
                                ? 'bg-[#e7f5ec] text-[#0f6b36] font-semibold'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                        "
                    >
                        <component
                            :is="item.icon"
                            class="w-4 h-4 shrink-0"
                            :class="
                                route().current(item.routeName)
                                    ? 'text-[#0f6b36]'
                                    : 'text-gray-400'
                            "
                        />
                        <span>{{ item.name }}</span>
                    </Link>
                </nav>
            </div>

            <!-- Bottom Logout -->
            <div class="pt-4 border-t border-gray-100">
                <button
                    @click="logout"
                    class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-red-50 hover:text-red-600 transition-all text-left"
                >
                    <LogOut
                        class="w-4 h-4 text-gray-400 group-hover:text-red-500"
                    />
                    <span>Keluar</span>
                </button>
            </div>
        </aside>

        <!-- Mobile Header -->
        <header
            class="md:hidden flex items-center justify-between bg-white px-4 py-3 border-b border-gray-100"
        >
            <div class="flex items-center gap-2">
                <img
                    src="/assets/logo.png"
                    alt="CarePlate"
                    class="h-7 w-auto"
                />
                <span class="font-bold text-gray-900">CarePlate Ahli Gizi</span>
            </div>
            <button
                @click="isMobileMenuOpen = !isMobileMenuOpen"
                class="p-2 text-gray-600 rounded-lg"
            >
                <Menu v-if="!isMobileMenuOpen" class="w-6 h-6" />
                <X v-else class="w-6 h-6" />
            </button>
        </header>

        <!-- Mobile Drawer -->
        <div
            v-if="isMobileMenuOpen"
            class="md:hidden bg-white border-b border-gray-200 px-4 py-3 space-y-1"
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
                        ? 'bg-[#e7f5ec] text-[#0f6b36]'
                        : 'text-gray-600'
                "
            >
                <component :is="item.icon" class="w-4 h-4" />
                <span>{{ item.name }}</span>
            </Link>
            <button
                @click="logout"
                class="w-full flex items-center gap-3 px-3 py-2 text-sm text-red-600 hover:bg-red-50 text-left"
            >
                <LogOut class="w-4 h-4" />
                <span>Keluar</span>
            </button>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Top Bar -->
            <header
                class="bg-white/70 backdrop-blur-md sticky top-0 z-20 border-b border-gray-100 px-6 py-3.5 flex items-center justify-between gap-4"
            >
                <!-- Search bar -->
                <div class="relative flex-1 max-w-md">
                    <Search
                        class="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2"
                    />
                    <input
                        type="text"
                        placeholder="Cari klien atau konsultasi"
                        class="w-full bg-[#f4f5f6] hover:bg-[#eceef0] focus:bg-white text-sm text-gray-800 placeholder-gray-400 pl-10 pr-14 py-2 rounded-xl border-none focus:ring-2 focus:ring-[#0f6b36]/30 transition-all outline-none"
                    />
                    <span
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] font-medium text-gray-400 bg-white px-1.5 py-0.5 rounded border border-gray-200"
                    >
                        Ctrl K
                    </span>
                </div>

                <!-- Right Side: Bell & Nutritionist Pill -->
                <div class="flex items-center gap-3 shrink-0">
                    <button
                        class="p-2 rounded-full hover:bg-gray-100 text-gray-500 transition-colors relative"
                    >
                        <Bell class="w-4 h-4" />
                    </button>

                    <!-- Nutritionist Profile Dropdown -->
                    <div class="relative">
                        <button
                            @click="isProfileOpen = !isProfileOpen"
                            class="flex items-center gap-2.5 p-1 rounded-full hover:bg-gray-50 transition-colors text-left"
                        >
                            <div
                                class="w-8 h-8 rounded-full bg-teal-100 text-teal-800 font-bold text-xs flex items-center justify-center shrink-0"
                            >
                                {{ userInitials }}
                            </div>
                            <div
                                class="hidden sm:block text-xs leading-tight pr-1"
                            >
                                <div class="font-semibold text-gray-900">
                                    {{ user?.name ?? "dr. Sari Wulandari" }}
                                </div>
                                <div class="text-[11px] text-gray-400">
                                    Ahli Gizi
                                </div>
                            </div>
                            <ChevronDown class="w-3.5 h-3.5 text-gray-400" />
                        </button>

                        <div
                            v-if="isProfileOpen"
                            @click.outside="isProfileOpen = false"
                            class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-30"
                        >
                            <button
                                @click="logout"
                                class="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 text-left"
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
