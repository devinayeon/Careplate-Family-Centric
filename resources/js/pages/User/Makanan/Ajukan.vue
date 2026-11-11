<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { AlertTriangle, CheckCircle2, ImagePlus, Utensils, X } from 'lucide-vue-next'
import { categoryIcon, energyLooksConsistent } from '@/lib/foodUi'

const props = defineProps<{ categories: Array<{ id: number; kategori: string }> }>()

const TOTAL = 6
const step = ref(1)
const fromScan = ref(false)
const photoUrl = ref<string | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)

const form = useForm({
    nama: '', kategori_makanan_id: '' as number | string, kuantitas: '100', satuan: 'g', energi: '', protein: '', karbohidrat: '', lemak: '',
    gula: '', natrium: '', sumber: '', foto_makanan: null as File | null,
})

// The scan screen leaves a draft in this tab; it is read once and removed.
onMounted(() => {
    if (new URLSearchParams(location.search).get('dari') !== 'pindai') return
    try {
        const draft = JSON.parse(sessionStorage.getItem('careplate.scanDraft') ?? 'null')
        sessionStorage.removeItem('careplate.scanDraft')
        if (!draft) return
        for (const key of ['kuantitas', 'satuan', 'energi', 'protein', 'karbohidrat', 'lemak', 'gula', 'natrium'] as const) {
            if (draft[key] !== null && draft[key] !== undefined) form[key] = String(draft[key])
        }
        form.sumber = 'Label kemasan (hasil pindai)'
        fromScan.value = true
    } catch { /* an unreadable draft just means an empty form */ }
})
onBeforeUnmount(() => { if (photoUrl.value) URL.revokeObjectURL(photoUrl.value) })

// v-model on a type=number input yields a number, not a string: accept both.
const num = (v: string | number | null) => {
    const text = String(v ?? '').trim()

    return text === '' || Number.isNaN(Number(text)) ? null : Number(text)
}
const category = computed(() => props.categories.find((c) => c.id === Number(form.kategori_makanan_id)))
const consistent = computed(() => {
    const [e, p, k, l] = [num(form.energi), num(form.protein), num(form.karbohidrat), num(form.lemak)]

    return e === null || p === null || k === null || l === null ? null : energyLooksConsistent(e, p, k, l)
})

const stepOk = computed(() => {
    switch (step.value) {
        case 1: return form.nama.trim() !== '' && category.value !== undefined
        case 2: return (num(form.kuantitas) ?? 0) > 0
        case 3: return [form.energi, form.protein, form.karbohidrat, form.lemak].every((v) => (num(v) ?? -1) >= 0)
        case 5: return form.sumber.trim() !== ''
        default: return true
    }
})

const bubble = computed(() => [
    '', fromScan.value ? 'Hasil pindai sudah terisi. Periksa nama dan kategorinya.' : 'Apa nama makanannya, dan termasuk kategori apa?',
    'Nilai gizi dihitung per takaran saji. Berapa takarannya?', 'Salin angka dari label atau sumber terpercaya, per takaran saji.',
    'Foto makanan membantu Admin memeriksa. Boleh dilewati.', 'Dari mana angka ini? Tulis sumbernya.', 'Periksa lagi, lalu kirim ke Admin.',
][step.value])

function pickPhoto(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null
    if (photoUrl.value) URL.revokeObjectURL(photoUrl.value)
    form.foto_makanan = file
    photoUrl.value = file ? URL.createObjectURL(file) : null
}

const FIELD_STEP: Record<string, number> = { nama: 1, kategori_makanan_id: 1, kuantitas: 2, satuan: 2, energi: 3, protein: 3, karbohidrat: 3, lemak: 3, gula: 3, natrium: 3, foto_makanan: 4, sumber: 5 }

function submit() {
    form.post(route('user.foods.store'), {
        forceFormData: true,
        onError: (errors) => { step.value = Math.min(...Object.keys(errors).map((k) => FIELD_STEP[k] ?? TOTAL)) },
    })
}

const field = 'h-14 w-full rounded-2xl border-2 border-[#191919] bg-white px-4 text-xl font-semibold text-[#191919] focus:outline-none focus:ring-2 focus:ring-[#0b8043]/30'
const small = 'h-12 w-full rounded-xl border-0 bg-[#f4f3f0] px-4 pr-14 text-base font-semibold text-[#191919] focus:ring-2 focus:ring-[#0b8043]/30'
</script>

<template>
    <Head title="Ajukan Data Makanan" />
    <div class="flex min-h-screen flex-col bg-white text-[#191919]">
        <header class="flex items-center gap-4 px-6 py-5">
            <Link :href="route('user.foods')" class="inline-flex items-center gap-1 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-[#191919]"><X class="h-4 w-4" aria-hidden="true" /> Keluar</Link>
            <div class="h-2 flex-1 overflow-hidden rounded-full bg-[#f4f3f0]" role="progressbar" aria-label="Kemajuan pengajuan" aria-valuemin="1" :aria-valuemax="TOTAL" :aria-valuenow="step"><div class="h-full rounded-full bg-[#0b8043] transition-all" :style="{ width: `${(step / TOTAL) * 100}%` }" /></div>
            <span class="text-sm font-semibold">Langkah {{ step }} dari {{ TOTAL }}</span>
        </header>

        <main class="mx-auto grid w-full max-w-5xl flex-1 gap-10 px-6 py-8 lg:grid-cols-[1fr_20rem]">
            <form class="flex flex-col gap-6" @submit.prevent="step === TOTAL ? submit() : (stepOk && step++)">
                <div class="flex items-start gap-4">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-[#e4f6ea]"><Utensils class="h-6 w-6 text-[#0b8043]" aria-hidden="true" /></span>
                    <p class="rounded-2xl border-2 border-[#eceae5] px-5 py-3 text-lg" role="status">{{ bubble }}</p>
                </div>

                <template v-if="step === 1">
                    <div><label for="f-nama" class="mb-1.5 block text-sm font-semibold">Nama makanan</label><div class="relative"><input id="f-nama" v-model="form.nama" maxlength="100" :class="field" autofocus /><span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm font-semibold text-[#5e5e5e]">{{ form.nama.length }}/100</span></div><p v-if="form.errors.nama" class="mt-1 text-sm text-[#b42318]">{{ form.errors.nama }}</p></div>
                    <fieldset><legend class="mb-1.5 text-sm font-semibold">Kategori</legend>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <label v-for="c in categories" :key="c.id" class="flex cursor-pointer flex-col items-center gap-2 rounded-2xl border-2 px-2 py-4 text-center text-sm font-semibold has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-[#191919]" :class="Number(form.kategori_makanan_id) === c.id ? 'border-[#0b8043] bg-[#e4f6ea]' : 'border-[#eceae5] hover:bg-[#f4f3f0]'">
                                <input v-model="form.kategori_makanan_id" type="radio" name="kategori" :value="c.id" class="sr-only" />
                                <component :is="categoryIcon(c.kategori)" class="h-7 w-7 text-[#0b8043]" aria-hidden="true" />{{ c.kategori }}
                            </label>
                        </div>
                        <p v-if="form.errors.kategori_makanan_id" class="mt-1 text-sm text-[#b42318]">{{ form.errors.kategori_makanan_id }}</p>
                    </fieldset>
                </template>

                <template v-else-if="step === 2">
                    <div><label for="f-qty" class="mb-1.5 block text-sm font-semibold">Takaran saji</label><div class="relative"><input id="f-qty" v-model="form.kuantitas" type="number" inputmode="decimal" min="0" step="any" :class="field" /><span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm font-semibold text-[#5e5e5e]">{{ form.satuan }}</span></div><p v-if="form.errors.kuantitas" class="mt-1 text-sm text-[#b42318]">{{ form.errors.kuantitas }}</p></div>
                    <fieldset><legend class="mb-1.5 text-sm font-semibold">Satuan</legend>
                        <div class="inline-flex overflow-hidden rounded-xl border-2 border-[#191919]">
                            <label v-for="o in [{ v: 'g', t: 'gram (g)' }, { v: 'ml', t: 'mililiter (ml)' }]" :key="o.v" class="cursor-pointer px-5 py-2.5 text-sm font-semibold has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-[#191919]" :class="form.satuan === o.v ? 'bg-[#191919] text-white' : 'bg-white'"><input v-model="form.satuan" type="radio" name="satuan" :value="o.v" class="sr-only" />{{ o.t }}</label>
                        </div>
                    </fieldset>
                    <p class="text-sm text-[#5e5e5e]">Contoh: 1 porsi lauk sekitar 50 sampai 100 g, 1 gelas minuman sekitar 250 ml.</p>
                </template>

                <template v-else-if="step === 3">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div v-for="f in [{ k: 'energi', t: 'Energi', u: 'kkal' }, { k: 'protein', t: 'Protein', u: 'g' }, { k: 'karbohidrat', t: 'Karbohidrat', u: 'g' }, { k: 'lemak', t: 'Lemak', u: 'g' }, { k: 'gula', t: 'Gula (boleh kosong)', u: 'g' }, { k: 'natrium', t: 'Natrium (boleh kosong)', u: 'mg' }] as const" :key="f.k">
                            <label :for="`f-${f.k}`" class="mb-1.5 block text-sm font-semibold">{{ f.t }}</label>
                            <div class="relative"><input :id="`f-${f.k}`" v-model="form[f.k]" type="number" inputmode="decimal" min="0" step="any" :class="small" /><span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm text-[#5e5e5e]">{{ f.u }}</span></div>
                            <p v-if="form.errors[f.k]" class="mt-1 text-sm text-[#b42318]">{{ form.errors[f.k] }}</p>
                        </div>
                    </div>
                    <p v-if="consistent === true" class="flex items-center gap-2 rounded-xl bg-[#e4f6ea] px-4 py-3 text-sm font-semibold text-[#0b7a46]" role="status"><CheckCircle2 class="h-4 w-4" aria-hidden="true" />Energi cocok dengan protein, karbohidrat, dan lemak yang kamu isi.</p>
                    <p v-else-if="consistent === false" class="flex items-center gap-2 rounded-xl bg-[#fff4cc] px-4 py-3 text-sm font-semibold text-[#7a4b00]" role="status"><AlertTriangle class="h-4 w-4" aria-hidden="true" />Energi tidak cocok dengan protein, karbohidrat, dan lemak. Periksa lagi angkanya.</p>
                </template>

                <template v-else-if="step === 4">
                    <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" aria-label="Pilih foto makanan" @change="pickPhoto" />
                    <button type="button" class="flex h-48 w-full flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-[#d6d3cc] text-sm font-semibold focus-visible:outline-2 focus-visible:outline-[#191919]" @click="fileInput?.click()"><img v-if="photoUrl" :src="photoUrl" alt="Foto yang dipilih" class="h-full w-full rounded-2xl object-contain" /><template v-else><ImagePlus class="h-8 w-8 text-[#0b8043]" aria-hidden="true" />Pilih foto (JPEG, PNG atau WebP, maks. 3 MB)</template></button>
                    <p v-if="form.errors.foto_makanan" class="text-sm text-[#b42318]">{{ form.errors.foto_makanan }}</p>
                </template>

                <template v-else-if="step === 5">
                    <div><label for="f-sumber" class="mb-1.5 block text-sm font-semibold">Sumber angka gizi</label><input id="f-sumber" v-model="form.sumber" maxlength="150" placeholder="Contoh: label kemasan, TKPI 2017" :class="field" /><p v-if="form.errors.sumber" class="mt-1 text-sm text-[#b42318]">{{ form.errors.sumber }}</p></div>
                </template>

                <dl v-else class="divide-y divide-[#eceae5] rounded-2xl border-2 border-[#eceae5] px-5 text-base">
                    <div v-for="r in [['Nama', form.nama], ['Kategori', category?.kategori], ['Takaran saji', `${form.kuantitas} ${form.satuan}`], ['Energi', `${form.energi} kkal`], ['Protein / Karbohidrat / Lemak', `${form.protein} / ${form.karbohidrat} / ${form.lemak} g`], ['Gula / Natrium', `${form.gula || 'tidak diisi'} g / ${form.natrium || 'tidak diisi'} mg`], ['Sumber', form.sumber]]" :key="r[0]" class="flex justify-between gap-4 py-3"><dt class="text-[#5e5e5e]">{{ r[0] }}</dt><dd class="text-right font-semibold">{{ r[1] }}</dd></div>
                </dl>

                <div class="mt-auto flex justify-end gap-3 border-t border-[#eceae5] pt-5">
                    <button v-if="step > 1" type="button" class="h-11 rounded-full border-2 border-[#191919] px-6 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919]" @click="step--">Kembali</button>
                    <button type="submit" :disabled="!stepOk || form.processing" class="h-11 rounded-full bg-[#0b8043] px-6 text-sm font-semibold text-white hover:bg-[#086637] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919] disabled:cursor-not-allowed disabled:opacity-50">{{ step === TOTAL ? (form.processing ? 'Mengirim…' : 'Kirim ke Admin') : 'Lanjut' }}</button>
                </div>
            </form>

            <aside class="h-fit rounded-3xl border border-[#eceae5] p-6" aria-label="Pratinjau">
                <h2 class="font-display text-lg font-bold">Pratinjau</h2>
                <div class="mt-4 flex h-36 items-center justify-center overflow-hidden rounded-2xl bg-[#f4f3f0]"><img v-if="photoUrl" :src="photoUrl" alt="" class="h-full w-full object-cover" /><component :is="category ? categoryIcon(category.kategori) : Utensils" v-else class="h-10 w-10 text-[#0b8043]" aria-hidden="true" /></div>
                <p class="mt-4 font-display text-xl font-bold">{{ form.nama || 'Nama makanan' }}</p>
                <p class="mt-1 text-sm text-[#5e5e5e]">{{ category?.kategori ?? 'Kategori' }} · {{ form.kuantitas }} {{ form.satuan }}<template v-if="form.energi"><br />{{ form.energi }} kkal<template v-if="form.protein"> · {{ form.protein }} g protein</template></template></p>
                <span class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-[#fff4cc] px-3 py-1 text-xs font-semibold text-[#7a4b00]"><AlertTriangle class="h-3 w-3" aria-hidden="true" />{{ fromScan ? 'Draf dari pindai' : 'Menunggu verifikasi' }}</span>
                <p v-if="step >= 3" class="mt-3 text-sm text-[#5e5e5e]">Admin memeriksa pengajuan sebelum muncul di Data Makanan dan Chat Gizi.</p>
            </aside>
        </main>
    </div>
</template>
