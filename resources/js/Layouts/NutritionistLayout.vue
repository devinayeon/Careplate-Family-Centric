<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/Common/ThemeToggle.vue';
import {
    LayoutDashboard,
    Stethoscope,
    LogOut,
    Search,
    Bell,
    ChevronDown,
    Menu,
    X,
} from 'lucide-vue-next';

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
        name: 'Dashboard',
        routeName: 'nutritionist.dashboard',
        icon: LayoutDashboard,
    },
    {
        name: 'Konsultasi',
        routeName: 'nutritionist.consultations',
        icon: Stethoscope,
    },
];

const userInitials = computed(() => {
    if (!user.value?.name) return 'AG';
    return user.value.name
        .replace('dr. ', '')
        .split(' ')
        .map((n: string) => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
});

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <div
        class="flex min-h-screen flex-col bg-[#f7f8f9] font-sans text-gray-800 transition-colors duration-200 md:flex-row dark:bg-[#121212] dark:text-[#f0efea]"
    >
        <!-- Desktop Sidebar -->
        <aside
            class="hidden min-h-screen shrink-0 flex-col justify-between border-r border-gray-100 bg-white p-5 transition-colors md:flex md:w-64 dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
        >
            <div>
                <!-- Logo & Ahli Gizi Role Badge -->
                <div class="mb-6 px-2">
                    <Link
                        :href="route('nutritionist.dashboard')"
                        class="mb-2.5 flex items-center"
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
                        class="inline-flex items-center gap-1.5 rounded-full bg-gray-100/90 px-2.5 py-1 text-[11px] font-medium text-gray-600 dark:bg-[#262626] dark:text-[#a3a3a3]"
                    >
                        <span
                            class="h-1.5 w-1.5 rounded-full bg-teal-500"
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
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all"
                        :class="
                            route().current(item.routeName)
                                ? 'bg-teal-50 font-semibold text-teal-800 dark:bg-teal-950/60 dark:text-teal-300'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-[#a3a3a3] dark:hover:bg-[#262626] dark:hover:text-[#f0efea]'
                        "
                    >
                        <component
                            :is="item.icon"
                            class="h-4 w-4 shrink-0"
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
            <div class="border-t border-gray-100 pt-4 dark:border-[#2e2e2e]">
                <button
                    @click="logout"
                    class="flex w-full cursor-pointer items-center gap-3 rounded-xl px-3.5 py-2.5 text-left text-sm font-medium text-gray-600 transition-all hover:bg-red-50 hover:text-red-600 dark:text-[#a3a3a3] dark:hover:bg-red-950/40 dark:hover:text-red-400"
                >
                    <LogOut
                        class="h-4 w-4 text-gray-400 group-hover:text-red-500 dark:text-gray-500"
                    />
                    <span>Keluar</span>
                </button>
            </div>
        </aside>

        <!-- Mobile Header -->
        <header
            class="flex items-center justify-between border-b border-gray-100 bg-white px-4 py-3 transition-colors md:hidden dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
        >
            <div class="flex items-center gap-2">
                <img
                    src="/assets/logo.png"
                    alt="CarePlate"
                    class="h-7 w-auto"
                />
                <span class="font-bold text-gray-900 dark:text-[#f0efea]"
                    >CarePlate Ahli Gizi</span
                >
            </div>
            <div class="flex items-center gap-2">
                <ThemeToggle />
                <button
                    @click="isMobileMenuOpen = !isMobileMenuOpen"
                    class="rounded-lg p-2 text-gray-600 dark:text-gray-300"
                >
                    <Menu v-if="!isMobileMenuOpen" class="h-6 w-6" />
                    <X v-else class="h-6 w-6" />
                </button>
            </div>
        </header>

        <!-- Mobile Drawer -->
        <div
            v-if="isMobileMenuOpen"
            class="space-y-1 border-b border-gray-200 bg-white px-4 py-3 md:hidden dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
        >
            <Link
                v-for="item in navItems"
                :key="item.name"
                :href="
                    route().has(item.routeName) ? route(item.routeName) : '#'
                "
                @click="isMobileMenuOpen = false"
                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium"
                :class="
                    route().current(item.routeName)
                        ? 'bg-teal-50 text-teal-800 dark:bg-teal-950/60 dark:text-teal-300'
                        : 'text-gray-600 dark:text-gray-300'
                "
            >
                <component :is="item.icon" class="h-4 w-4" />
                <span>{{ item.name }}</span>
            </Link>
            <button
                @click="logout"
                class="flex w-full cursor-pointer items-center gap-3 px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
            >
                <LogOut class="h-4 w-4" />
                <span>Keluar</span>
            </button>
        </div>

        <!-- Main Content Area -->
        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Top Bar -->
            <header
                class="sticky top-0 z-20 flex items-center justify-between gap-4 border-b border-gray-100 bg-white/70 px-6 py-3.5 backdrop-blur-md transition-colors dark:border-[#2e2e2e] dark:bg-[#1c1c1c]/80"
            >
                <!-- Search bar -->
                <div class="relative max-w-md flex-1">
                    <Search
                        class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500"
                    />
                    <input
                        type="text"
                        placeholder="Cari klien atau konsultasi"
                        class="w-full rounded-xl border border-transparent bg-[#f4f5f6] py-2 pr-14 pl-10 text-sm text-gray-800 placeholder-gray-400 transition-all outline-none hover:bg-[#eceef0] focus:bg-white focus:ring-2 focus:ring-teal-600/30 dark:border-[#2e2e2e] dark:bg-[#262626] dark:text-[#f0efea] dark:placeholder-gray-500 dark:hover:bg-[#2e2e2e] dark:focus:bg-[#1c1c1c]"
                    />
                    <span
                        class="absolute top-1/2 right-3 -translate-y-1/2 rounded border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-medium text-gray-400 dark:border-[#2e2e2e] dark:bg-[#1c1c1c] dark:text-gray-500"
                    >
                        Ctrl K
                    </span>
                </div>

                <!-- Right Side: ThemeToggle, Bell & Nutritionist Pill -->
                <div class="flex shrink-0 items-center gap-3">
                    <ThemeToggle />

                    <button
                        class="relative cursor-pointer rounded-xl p-2 text-gray-500 transition-colors hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-[#262626]"
                        title="Notifikasi"
                    >
                        <Bell class="h-4 w-4" />
                    </button>

                    <!-- Nutritionist Profile Dropdown -->
                    <div class="relative">
                        <button
                            @click="isProfileOpen = !isProfileOpen"
                            class="flex cursor-pointer items-center gap-2.5 rounded-full p-1 text-left transition-colors hover:bg-gray-50 dark:hover:bg-[#262626]"
                        >
                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-teal-100 text-xs font-bold text-teal-800 dark:bg-teal-950 dark:text-teal-300"
                            >
                                {{ userInitials }}
                            </div>
                            <div
                                class="hidden pr-1 text-xs leading-tight sm:block"
                            >
                                <div
                                    class="font-semibold text-gray-900 dark:text-[#f0efea]"
                                >
                                    {{ user?.name ?? 'dr. Sari Wulandari' }}
                                </div>
                                <div
                                    class="text-[11px] text-gray-400 dark:text-gray-500"
                                >
                                    Ahli Gizi
                                </div>
                            </div>
                            <ChevronDown
                                class="h-3.5 w-3.5 text-gray-400 dark:text-gray-500"
                            />
                        </button>

                        <div
                            v-if="isProfileOpen"
                            @click.outside="isProfileOpen = false"
                            class="absolute right-0 z-30 mt-2 w-48 rounded-xl border border-gray-100 bg-white py-1 shadow-lg dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
                        >
                            <button
                                @click="logout"
                                class="flex w-full cursor-pointer items-center gap-2 px-4 py-2 text-left text-sm text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
                            >
                                <LogOut class="h-4 w-4" />
                                <span>Keluar</span>
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Slot Content -->
            <main
                class="mx-auto w-full flex-1"
                :class="fullWidth ? 'p-4 md:p-6' : 'max-w-7xl p-6 md:p-8'"
            >
                <slot />
            </main>
        </div>
    </div>
</template>
