<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ChipFilter from '@/Components/UI/ChipFilter.vue'
import SearchBar from '@/Components/UI/SearchBar.vue'
import RoleBadge from '@/Components/UI/RoleBadge.vue'
import PillButton from '@/Components/UI/PillButton.vue'
import DataTable, { type TableColumn } from '@/Components/UI/DataTable.vue'
import UserFormDrawer, { type UserFormData } from '@/Components/Admin/UserFormDrawer.vue'
import { UserPlus, Pencil, Trash2 } from 'lucide-vue-next'

export interface UserItem {
  id: number
  name: string
  email: string
  role: 'customer' | 'ahli_gizi' | 'admin'
  role_label: string
  status_keluarga: string
  terdaftar: string
  initials: string
  ahli_gizi?: {
    spesialisasi?: string | null
    nomor_str?: string | null
    jam_layanan?: string | null
  } | null
}

const props = defineProps<{
  users: {
    data: UserItem[]
    total: number
    from: number | null
    to: number | null
    prev_page_url: string | null
    next_page_url: string | null
  }
  filters: {
    search: string
    role: string
  }
  counts: {
    semua: number
    customer: number
    ahli_gizi: number
    admin: number
  }
}>()

const activeRoleFilter = ref(props.filters.role || 'semua')
const searchQuery = ref(props.filters.search || '')

const showDrawer = ref(false)
const userBeingEdited = ref<UserFormData | null>(null)

// Table column definitions matching Figma
const tableColumns: TableColumn[] = [
  { key: 'nama', label: 'Nama', width: '280px' },
  { key: 'email', label: 'Email', width: '250px' },
  { key: 'peran', label: 'Peran', width: '130px' },
  { key: 'status_keluarga', label: 'Status keluarga', width: '170px' },
  { key: 'terdaftar', label: 'Terdaftar', width: '120px' },
  { key: 'actions', label: '', width: '96px', align: 'right' },
]

// Filter handling
const applyFilter = (roleKey: string) => {
  activeRoleFilter.value = roleKey
  router.get(
    route('admin.users'),
    {
      role: roleKey,
      search: searchQuery.value || undefined,
    },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    }
  )
}

const handleSearch = () => {
  router.get(
    route('admin.users'),
    {
      role: activeRoleFilter.value,
      search: searchQuery.value || undefined,
    },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    }
  )
}

const handlePageChange = (url: string) => {
  router.visit(url, {
    preserveState: true,
    preserveScroll: true,
  })
}

// Modal Drawer Trigger
const openCreateDrawer = () => {
  userBeingEdited.value = null
  showDrawer.value = true
}

const closeDrawer = () => {
  showDrawer.value = false
  userBeingEdited.value = null
}

const openEditDrawer = (item: UserItem) => {
  userBeingEdited.value = {
    id: item.id,
    name: item.name,
    email: item.email,
    role: item.role,
    spesialisasi: item.ahli_gizi?.spesialisasi,
    nomor_str: item.ahli_gizi?.nomor_str,
    jam_layanan: item.ahli_gizi?.jam_layanan,
  }
  showDrawer.value = true
}

// Delete user confirmation
const deleteUser = (item: UserItem) => {
  if (confirm(`Apakah Anda yakin ingin menghapus pengguna "${item.name}"?`)) {
    router.delete(route('admin.users.destroy', item.id), {
      preserveScroll: true,
    })
  }
}
</script>

<template>
  <Head title="Manajemen Pengguna - Admin" />

  <AdminLayout>
    <div class="space-y-6 max-w-6xl">
      <!-- 1. Page Header (Title + Tambah Pengguna Button) -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-3xl sm:text-4xl font-extrabold text-[#191919] dark:text-[#f0efea] tracking-tight">
            Pengguna
          </h1>
        </div>

        <PillButton
          variant="primary"
          @click="openCreateDrawer"
          class="self-start sm:self-center"
        >
          <UserPlus class="w-4 h-4" />
          <span>Tambah Pengguna</span>
        </PillButton>
      </div>

      <!-- 2. Filter Bar: Chips & Search Bar -->
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <!-- Chip Filters -->
        <div class="flex items-center gap-2 overflow-x-auto pb-1 lg:pb-0 scrollbar-none">
          <ChipFilter
            label="Semua"
            :active="activeRoleFilter === 'semua'"
            @click="applyFilter('semua')"
          />
          <ChipFilter
            label="Customer"
            :active="activeRoleFilter === 'customer'"
            @click="applyFilter('customer')"
          />
          <ChipFilter
            label="Ahli Gizi"
            :active="activeRoleFilter === 'ahli_gizi'"
            @click="applyFilter('ahli_gizi')"
          />
          <ChipFilter
            label="Admin"
            :active="activeRoleFilter === 'admin'"
            @click="applyFilter('admin')"
          />
        </div>

        <!-- Search Bar -->
        <div class="w-full lg:w-auto">
          <SearchBar
            v-model="searchQuery"
            placeholder="Cari nama atau email"
            @search="handleSearch"
            @clear="handleSearch"
          />
        </div>
      </div>

      <!-- 3. Data Table -->
      <DataTable
        :columns="tableColumns"
        :items="users.data"
        :total="users.total"
        :from="users.from"
        :to="users.to"
        :prev-url="users.prev_page_url"
        :next-url="users.next_page_url"
        empty-title="Tidak ada pengguna"
        empty-description="Coba ubah kata kunci pencarian atau filter peran pengguna."
        @page-change="handlePageChange"
      >
        <template #row="{ item }: { item: UserItem }">
          <!-- Col 1: Nama & Avatar -->
          <td class="px-6 py-3.5">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-full bg-[#e4f6ea] dark:bg-[#17321f] text-[#0b7a46] dark:text-[#3fcb7a] font-bold text-xs flex items-center justify-center shrink-0">
                {{ item.initials }}
              </div>
              <span class="font-bold text-[#191919] dark:text-[#f0efea] text-sm whitespace-nowrap">
                {{ item.name }}
              </span>
            </div>
          </td>

          <!-- Col 2: Email -->
          <td class="px-6 py-3.5 text-sm text-[#191919] dark:text-[#f0efea] font-medium whitespace-nowrap">
            {{ item.email }}
          </td>

          <!-- Col 3: Peran Badge -->
          <td class="px-6 py-3.5 whitespace-nowrap">
            <RoleBadge :role="item.role" :label="item.role_label" />
          </td>

          <!-- Col 4: Status Keluarga -->
          <td class="px-6 py-3.5 text-sm text-[#191919] dark:text-[#f0efea] font-medium whitespace-nowrap">
            {{ item.status_keluarga }}
          </td>

          <!-- Col 5: Terdaftar -->
          <td class="px-6 py-3.5 text-sm text-[#191919] dark:text-[#f0efea] font-medium whitespace-nowrap">
            {{ item.terdaftar }}
          </td>

          <!-- Col 6: Action Buttons (Ubah & Hapus) -->
          <td class="px-6 py-3.5 text-right whitespace-nowrap">
            <div class="flex items-center justify-end gap-1.5">
              <!-- Tombol Ubah -->
              <button
                type="button"
                @click="openEditDrawer(item)"
                aria-label="Ubah"
                class="w-9 h-9 rounded-xl hover:bg-gray-100 dark:hover:bg-[#262626] flex items-center justify-center text-[#191919] dark:text-[#f0efea] transition-colors cursor-pointer"
              >
                <Pencil class="w-4 h-4 text-gray-700 dark:text-gray-300" />
              </button>

              <!-- Tombol Hapus -->
              <button
                type="button"
                @click="deleteUser(item)"
                aria-label="Hapus"
                class="w-9 h-9 rounded-xl hover:bg-red-50 dark:hover:bg-red-950/40 flex items-center justify-center text-gray-400 dark:text-gray-500 hover:text-red-600 dark:hover:text-red-400 transition-colors cursor-pointer"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </div>
          </td>
        </template>
      </DataTable>
    </div>

    <!-- 4. Slide-over Drawer for Add/Edit User -->
    <UserFormDrawer
      :show="showDrawer"
      :user-to-edit="userBeingEdited"
      @close="closeDrawer"
      @success="closeDrawer"
    />
  </AdminLayout>
</template>
