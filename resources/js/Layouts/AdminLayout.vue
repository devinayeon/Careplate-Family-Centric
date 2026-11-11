<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/Common/ThemeToggle.vue';
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
} from 'lucide-vue-next';

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
    { name: 'Dashboard', routeName: 'admin.dashboard', icon: LayoutDashboard },
    { name: 'Pengguna', routeName: 'admin.users', icon: Users },
    { name: 'Artikel', routeName: 'admin.articles', icon: BookOpen },
    { name: 'Data Makanan', routeName: 'admin.foods', icon: UtensilsCrossed },
    {
        name: 'Verifikasi',
        routeName: 'admin.verifications',
        icon: CheckCircle2,
        badge:
            pendingVerificationCount.value > 0
                ? pendingVerificationCount.value
                : null,
    },
]);

const userInitials = computed(() => {
    if (!user.value?.name) return 'AD';
    return user.value.name
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
            class="hidden md:flex md:w-64 flex-col bg-white dark:bg-[#1c1c1c] border-r border-gray-100 dark:border-[#2e2e2e] h-screen sticky top-0 p-5 shrink-0 justify-between overflow-y-auto custom-scrollbar transition-colors z-30"
        >
            <div>
                <!-- Logo & Admin Role Badge -->
                <div class="mb-6 px-2">
                    <Link
                        :href="route('admin.dashboard')"
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
                            class="h-1.5 w-1.5 rounded-full bg-emerald-500"
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
                        class="flex items-center justify-between rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all"
                        :class="
                            route().current(item.routeName)
                                ? 'bg-[#e7f5ec] font-semibold text-[#0f6b36] dark:bg-[#17321f] dark:text-[#3fcb7a]'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-[#a3a3a3] dark:hover:bg-[#262626] dark:hover:text-[#f0efea]'
                        "
                    >
                        <div class="flex items-center gap-3">
                            <component
                                :is="item.icon"
                                class="h-4 w-4 shrink-0"
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
                            class="rounded-full px-2 py-0.5 text-[11px] font-bold"
                            :class="
                                route().current(item.routeName)
                                    ? 'bg-[#0f6b36] text-white dark:bg-[#3fcb7a] dark:text-[#0e1a13]'
                                    : 'bg-gray-200 text-gray-700 dark:bg-[#262626] dark:text-gray-300'
                            "
                        >
                            {{ item.badge }}
                        </span>
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
                class="flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium"
                :class="
                    route().current(item.routeName)
                        ? 'bg-[#e7f5ec] text-[#0f6b36] dark:bg-[#17321f] dark:text-[#3fcb7a]'
                        : 'text-gray-600 dark:text-gray-300'
                "
            >
                <div class="flex items-center gap-3">
                    <component :is="item.icon" class="h-4 w-4" />
                    <span>{{ item.name }}</span>
                </div>
                <span
                    v-if="item.badge"
                    class="rounded-full bg-gray-200 px-2 py-0.5 text-[11px] font-bold text-gray-700 dark:bg-[#262626] dark:text-gray-300"
                >
                    {{ item.badge }}
                </span>
            </Link>

            <Link
                :href="route('admin.profile.edit')"
                @click="isMobileMenuOpen = false"
                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium"
                :class="
                    route().current('admin.profile.edit')
                        ? 'bg-[#e7f5ec] text-[#0f6b36] dark:bg-[#17321f] dark:text-[#3fcb7a]'
                        : 'text-gray-600 dark:text-gray-300'
                "
            >
                <UserIcon class="h-4 w-4" />
                <span>Profil Saya</span>
            </Link>

            <button
                @click="logout"
                class="flex w-full items-center gap-3 px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
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
                        placeholder="Cari pengguna, artikel, atau makanan"
                        class="w-full rounded-xl border border-transparent bg-[#f4f5f6] py-2 pr-14 pl-10 text-sm text-gray-800 placeholder-gray-400 transition-all outline-none hover:bg-[#eceef0] focus:bg-white focus:ring-2 focus:ring-[#0f6b36]/30 dark:border-[#2e2e2e] dark:bg-[#262626] dark:text-[#f0efea] dark:placeholder-gray-500 dark:hover:bg-[#2e2e2e] dark:focus:bg-[#1c1c1c]"
                    />
                    <span
                        class="absolute top-1/2 right-3 -translate-y-1/2 rounded border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-medium text-gray-400 dark:border-[#2e2e2e] dark:bg-[#1c1c1c] dark:text-gray-500"
                    >
                        Ctrl K
                    </span>
                </div>

                <!-- Right Side: Theme Toggle, Bell & Admin Dropdown -->
                <div class="flex shrink-0 items-center gap-3">
                    <!-- Dark Mode Toggle Button -->
                    <ThemeToggle />

                    <button
                        class="relative cursor-pointer rounded-xl p-2 text-gray-500 transition-colors hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-[#262626]"
                        title="Notifikasi"
                    >
                        <Bell class="h-4 w-4" />
                    </button>

                    <!-- Admin Profile Dropdown -->
                    <div class="relative">
                        <button
                            @click="isProfileOpen = !isProfileOpen"
                            class="flex cursor-pointer items-center gap-2.5 rounded-full p-1 text-left transition-colors hover:bg-gray-50 dark:hover:bg-[#262626]"
                        >
                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800 dark:bg-[#17321f] dark:text-[#3fcb7a]"
                            >
                                {{ userInitials }}
                            </div>
                            <div
                                class="hidden pr-1 text-xs leading-tight sm:block"
                            >
                                <div
                                    class="font-semibold text-gray-900 dark:text-[#f0efea]"
                                >
                                    {{ user?.name ?? 'Admin CarePlate' }}
                                </div>
                                <div
                                    class="text-[11px] text-gray-400 dark:text-gray-500"
                                >
                                    {{ user?.email ?? 'admin@careplate.id' }}
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
                            <Link
                                :href="route('admin.profile.edit')"
                                @click="isProfileOpen = false"
                                class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-[#f0efea] dark:hover:bg-[#262626]"
                            >
                                <UserIcon
                                    class="h-4 w-4 text-gray-400 dark:text-gray-500"
                                />
                                <span>Profil Saya</span>
                            </Link>

                            <div
                                class="my-1 border-t border-gray-100 dark:border-[#2e2e2e]"
                            ></div>

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
            <main class="mx-auto w-full max-w-7xl flex-1 p-6 md:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
