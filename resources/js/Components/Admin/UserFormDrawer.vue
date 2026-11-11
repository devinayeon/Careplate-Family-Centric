<script setup lang="ts">
import { ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { X, Copy, Check, RefreshCw } from 'lucide-vue-next'
import PillButton from '@/Components/UI/PillButton.vue'
import InputError from '@/Components/InputError.vue'

export interface UserFormData {
  id?: number | null
  name: string
  email: string
  role: 'customer' | 'ahli_gizi' | 'admin'
  spesialisasi?: string | null
  nomor_str?: string | null
  jam_layanan?: string | null
  password?: string
}

const props = defineProps<{
  show: boolean
  userToEdit?: UserFormData | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'success'): void
}>()

const copied = ref(false)

const form = useForm({
  name: '',
  email: '',
  role: 'customer' as 'customer' | 'ahli_gizi' | 'admin',
  spesialisasi: 'Gizi Keluarga',
  nomor_str: '',
  jam_layanan: 'Senin sampai Sabtu, 08.00 sampai 12.00',
  password: '',
})

// Fungsi pembersih form mutlak ke kondisi kosong semula
const resetToCleanState = () => {
  form.name = ''
  form.email = ''
  form.role = 'customer'
  form.spesialisasi = 'Gizi Keluarga'
  form.nomor_str = ''
  form.jam_layanan = 'Senin sampai Sabtu, 08.00 sampai 12.00'
  form.password = ''
  form.clearErrors()
  copied.value = false
}

// Generate random strong password
const generatePassword = () => {
  const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%'
  let pass = ''
  for (let i = 0; i < 12; i++) {
    pass += chars.charAt(Math.floor(Math.random() * chars.length))
  }
  form.password = pass
}

// Copy password to clipboard
const copyPassword = async () => {
  if (!form.password) return
  try {
    await navigator.clipboard.writeText(form.password)
    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 2000)
  } catch (err) {
    console.error('Failed to copy password', err)
  }
}

// Pantau perubahan visibilitas drawer dan data yang diedit
watch(
  () => [props.show, props.userToEdit] as const,
  ([isOpen, userToEdit]) => {
    if (isOpen) {
      if (userToEdit) {
        // Mode Edit: Isi form dengan data yang dipilih
        form.name = userToEdit.name ?? ''
        form.email = userToEdit.email ?? ''
        form.role = userToEdit.role ?? 'customer'
        form.spesialisasi = userToEdit.spesialisasi || 'Gizi Keluarga'
        form.nomor_str = userToEdit.nomor_str || ''
        form.jam_layanan = userToEdit.jam_layanan || 'Senin sampai Sabtu, 08.00 sampai 12.00'
        form.password = ''
      } else {
        // Mode Tambah Baru: Bersihkan seluruh field tanpa sisa
        resetToCleanState()
        generatePassword()
      }
      form.clearErrors()
    } else {
      // Ketika drawer ditutup, bersihkan juga form agar tidak membekas
      resetToCleanState()
    }
  },
  { immediate: true }
)

const handleClose = () => {
  resetToCleanState()
  emit('close')
}

const submit = () => {
  if (props.userToEdit && props.userToEdit.id) {
    form.put(route('admin.users.update', props.userToEdit.id), {
      preserveScroll: true,
      onSuccess: () => {
        resetToCleanState()
        emit('success')
        emit('close')
      },
    })
  } else {
    form.post(route('admin.users.store'), {
      preserveScroll: true,
      onSuccess: () => {
        resetToCleanState()
        emit('success')
        emit('close')
      },
    })
  }
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 overflow-hidden">
    <!-- Scrim Backdrop -->
    <div
      class="fixed inset-0 bg-[#1e2321]/50 backdrop-blur-2xs transition-opacity"
      @click="handleClose"
    ></div>

    <!-- Slide-over Drawer Panel -->
    <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
      <div class="w-screen max-w-md sm:max-w-[560px] bg-white dark:bg-[#1c1c1c] text-[#191919] dark:text-[#f0efea] shadow-2xl flex flex-col justify-between overflow-y-auto border-l dark:border-[#2e2e2e]">
        <!-- Drawer Header -->
        <div class="px-7 pt-6 pb-4 border-b border-[#eceae5] dark:border-[#2e2e2e] flex items-center justify-between">
          <h2 class="text-2xl sm:text-3xl font-extrabold text-[#191919] dark:text-[#f0efea] tracking-tight">
            {{ userToEdit ? 'Ubah pengguna' : 'Tambah pengguna' }}
          </h2>
          <button
            type="button"
            @click="handleClose"
            class="w-10 h-10 rounded-xl hover:bg-gray-100 dark:hover:bg-[#262626] flex items-center justify-center text-gray-500 dark:text-[#a3a3a3] transition-colors"
          >
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Form Body -->
        <form @submit.prevent="submit" id="userForm" class="p-7 space-y-5 flex-1">
          <!-- Role Segmented Control -->
          <div>
            <label class="block text-sm font-bold text-[#191919] dark:text-[#f0efea] mb-2">
              Peran
            </label>
            <div class="border-[1.5px] border-[#191919] dark:border-[#3d3d3d] rounded-xl flex overflow-hidden p-0.5 bg-[#f4f3f0] dark:bg-[#262626]">
              <button
                type="button"
                @click="form.role = 'customer'"
                class="flex-1 py-2 text-xs sm:text-sm font-bold rounded-lg transition-all text-center"
                :class="form.role === 'customer' ? 'bg-[#191919] text-white dark:bg-[#3fcb7a] dark:text-[#121212] shadow-2xs' : 'text-[#191919] dark:text-[#a3a3a3] hover:bg-white/60 dark:hover:bg-[#1c1c1c]'"
              >
                Customer
              </button>
              <button
                type="button"
                @click="form.role = 'ahli_gizi'"
                class="flex-1 py-2 text-xs sm:text-sm font-bold rounded-lg transition-all text-center border-l border-r border-[#191919]/20 dark:border-[#3d3d3d]"
                :class="form.role === 'ahli_gizi' ? 'bg-[#191919] text-white dark:bg-[#3fcb7a] dark:text-[#121212] shadow-2xs' : 'text-[#191919] dark:text-[#a3a3a3] hover:bg-white/60 dark:hover:bg-[#1c1c1c]'"
              >
                Ahli Gizi
              </button>
              <button
                type="button"
                @click="form.role = 'admin'"
                class="flex-1 py-2 text-xs sm:text-sm font-bold rounded-lg transition-all text-center"
                :class="form.role === 'admin' ? 'bg-[#191919] text-white dark:bg-[#3fcb7a] dark:text-[#121212] shadow-2xs' : 'text-[#191919] dark:text-[#a3a3a3] hover:bg-white/60 dark:hover:bg-[#1c1c1c]'"
              >
                Admin
              </button>
            </div>
            <InputError :message="form.errors.role" class="mt-1" />
          </div>

          <!-- Nama Lengkap -->
          <div>
            <label class="block text-sm font-bold text-[#191919] dark:text-[#f0efea] mb-1.5">
              Nama lengkap
            </label>
            <input
              v-model="form.name"
              type="text"
              placeholder="Contoh: Rina Santoso"
              required
              class="w-full bg-[#f4f3f0] dark:bg-[#262626] hover:bg-[#eceae5] dark:hover:bg-[#2e2e2e] focus:bg-white dark:focus:bg-[#1c1c1c] border-2 border-transparent focus:border-[#191919] dark:focus:border-[#3fcb7a] rounded-xl h-12 px-4 text-sm text-[#191919] dark:text-[#f0efea] placeholder-gray-400 dark:placeholder-[#737373] transition-all outline-hidden"
            />
            <InputError :message="form.errors.name" class="mt-1" />
          </div>

          <!-- Email -->
          <div>
            <label class="block text-sm font-bold text-[#191919] dark:text-[#f0efea] mb-1.5">
              Email
            </label>
            <input
              v-model="form.email"
              type="email"
              placeholder="nama@email.com"
              required
              class="w-full bg-[#f4f3f0] dark:bg-[#262626] hover:bg-[#eceae5] dark:hover:bg-[#2e2e2e] focus:bg-white dark:focus:bg-[#1c1c1c] border-2 border-transparent focus:border-[#191919] dark:focus:border-[#3fcb7a] rounded-xl h-12 px-4 text-sm text-[#191919] dark:text-[#f0efea] placeholder-gray-400 dark:placeholder-[#737373] transition-all outline-hidden"
            />
            <InputError :message="form.errors.email" class="mt-1" />
          </div>

          <!-- Conditional Fields for Ahli Gizi -->
          <div v-if="form.role === 'ahli_gizi'" class="space-y-5 pt-2 border-t border-[#eceae5] dark:border-[#2e2e2e]">
            <!-- Spesialisasi -->
            <div>
              <label class="block text-sm font-bold text-[#191919] dark:text-[#f0efea] mb-1.5">
                Spesialisasi
              </label>
              <input
                v-model="form.spesialisasi"
                type="text"
                placeholder="Contoh: Gizi Keluarga, Gizi Anak"
                class="w-full bg-[#f4f3f0] dark:bg-[#262626] hover:bg-[#eceae5] dark:hover:bg-[#2e2e2e] focus:bg-white dark:focus:bg-[#1c1c1c] border-2 border-[#191919] dark:border-[#3d3d3d] focus:border-[#0b8043] dark:focus:border-[#3fcb7a] rounded-xl h-12 px-4 text-sm text-[#191919] dark:text-[#f0efea] transition-all outline-hidden font-medium"
              />
              <InputError :message="form.errors.spesialisasi" class="mt-1" />
            </div>

            <!-- Nomor STR -->
            <div>
              <label class="block text-sm font-bold text-[#191919] dark:text-[#f0efea] mb-1.5">
                Nomor STR
              </label>
              <input
                v-model="form.nomor_str"
                type="text"
                placeholder="1234 5678 9012 3456"
                class="w-full bg-[#f4f3f0] dark:bg-[#262626] hover:bg-[#eceae5] dark:hover:bg-[#2e2e2e] focus:bg-white dark:focus:bg-[#1c1c1c] border-2 border-transparent focus:border-[#191919] dark:focus:border-[#3fcb7a] rounded-xl h-12 px-4 text-sm text-[#191919] dark:text-[#f0efea] placeholder-gray-400 dark:placeholder-[#737373] transition-all outline-hidden font-mono"
              />
              <InputError :message="form.errors.nomor_str" class="mt-1" />
            </div>

            <!-- Jam Layanan -->
            <div>
              <label class="block text-sm font-bold text-[#191919] dark:text-[#f0efea] mb-1.5">
                Jam layanan
              </label>
              <input
                v-model="form.jam_layanan"
                type="text"
                placeholder="Senin sampai Sabtu, 08.00 sampai 12.00"
                class="w-full bg-[#f4f3f0] dark:bg-[#262626] hover:bg-[#eceae5] dark:hover:bg-[#2e2e2e] focus:bg-white dark:focus:bg-[#1c1c1c] border-2 border-transparent focus:border-[#191919] dark:focus:border-[#3fcb7a] rounded-xl h-12 px-4 text-sm text-[#191919] dark:text-[#f0efea] placeholder-gray-400 dark:placeholder-[#737373] transition-all outline-hidden"
              />
              <InputError :message="form.errors.jam_layanan" class="mt-1" />
            </div>
          </div>

          <!-- Password / Kata Sandi Sementara -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="text-sm font-bold text-[#191919] dark:text-[#f0efea]">
                {{ userToEdit ? 'Kata sandi baru (kosongkan jika tidak diubah)' : 'Kata sandi sementara' }}
              </label>
              <button
                type="button"
                @click="generatePassword"
                class="inline-flex items-center gap-1 text-xs font-semibold text-[#0b8043] dark:text-[#3fcb7a] hover:underline"
              >
                <RefreshCw class="w-3 h-3" />
                <span>Acak Sandi</span>
              </button>
            </div>

            <div class="bg-[#f4f3f0] dark:bg-[#262626] rounded-xl h-12 px-4 flex items-center justify-between gap-3 border-2 border-transparent focus-within:border-[#191919] dark:focus-within:border-[#3fcb7a] focus-within:bg-white dark:focus-within:bg-[#1c1c1c]">
              <input
                v-model="form.password"
                type="text"
                :placeholder="userToEdit ? '••••••••' : 'Buat kata sandi'"
                :required="!userToEdit"
                class="w-full bg-transparent border-none text-sm text-[#191919] dark:text-[#f0efea] placeholder-gray-400 dark:placeholder-[#737373] font-mono outline-hidden p-0"
              />
              <button
                v-if="form.password"
                type="button"
                @click="copyPassword"
                class="inline-flex items-center gap-1 text-xs font-bold text-[#086637] dark:text-[#3fcb7a] hover:bg-emerald-50 dark:hover:bg-[#17321f] px-2.5 py-1 rounded-md transition-colors shrink-0"
              >
                <Check v-if="copied" class="w-3.5 h-3.5 text-emerald-600 dark:text-[#3fcb7a]" />
                <Copy v-else class="w-3.5 h-3.5 text-[#086637] dark:text-[#3fcb7a]" />
                <span>{{ copied ? 'Tersalin' : 'Salin' }}</span>
              </button>
            </div>
            <p class="text-xs text-[#5e5e5e] dark:text-[#a3a3a3] mt-1.5">
              Ditampilkan sekali. Pengguna menggantinya saat pertama masuk.
            </p>
            <InputError :message="form.errors.password" class="mt-1" />
          </div>
        </form>

        <!-- Drawer Footer -->
        <div class="px-7 py-4 border-t border-[#eceae5] dark:border-[#2e2e2e] bg-[#faf9f7] dark:bg-[#1c1c1c] flex items-center justify-end gap-3">
          <PillButton
            variant="outline"
            type="button"
            @click="handleClose"
          >
            Batal
          </PillButton>
          <PillButton
            variant="primary"
            type="submit"
            form="userForm"
            :loading="form.processing"
          >
            {{ userToEdit ? 'Perbarui Pengguna' : 'Simpan Pengguna' }}
          </PillButton>
        </div>
      </div>
    </div>
  </div>
</template>
