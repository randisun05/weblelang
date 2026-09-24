<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Field from '@/Components/Field.vue';
import MoneyInput from '@/Components/MoneyInput.vue';

const props = defineProps({ item: Object, consignors: Array, categories: Array, conditions: Array, preselectConsignor: Number });
const it = props.item;

const form = useForm({
    consignor_id: it?.consignor_id ?? props.preselectConsignor ?? '',
    category_id: it?.category_id ?? '',
    title: it?.title ?? '',
    description: it?.description ?? '',
    condition: it?.condition ?? 'bekas_baik',
    specs: { ...(it?.specs ?? {}) },
    estimate_low: it?.estimate_low ?? null,
    estimate_high: it?.estimate_high ?? null,
    reserve_price: it?.reserve_price ?? null,
    commission_rate: it?.commission_rate ?? '',
    storage_location: it?.storage_location ?? '',
    received_at: it?.received_at ?? new Date().toISOString().slice(0, 10),
    inspection_notes: it?.inspection_notes ?? '',
    images: [],
    ...(it ? { _method: 'put' } : {}),
});

const schema = computed(() => props.categories.find((c) => c.id === Number(form.category_id))?.attribute_schema ?? []);
const consignor = computed(() => props.consignors.find((c) => c.id === Number(form.consignor_id)));
const previews = computed(() => form.images.map((f) => URL.createObjectURL(f)));

const submit = () => form.post(it ? route('admin.items.update', it.id) : route('admin.items.store'), { forceFormData: true });
const removeImage = (img) => confirm('Hapus foto ini?') && router.delete(route('admin.items.images.destroy', [it.id, img.id]), { preserveScroll: true });
</script>

<template>
    <Head :title="it ? 'Ubah Barang' : 'Terima Barang'" />
    <AdminLayout :title="it ? `Ubah ${it.code}` : 'Terima Barang Titipan'">
        <form class="grid gap-6 xl:grid-cols-[1fr_400px]" @submit.prevent="submit">
            <div class="space-y-6">
                <section class="card space-y-4 p-6">
                    <h2 class="font-semibold text-ink">Informasi barang</h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <Field label="Penitip" :error="form.errors.consignor_id" required>
                            <select v-model="form.consignor_id" class="input">
                                <option value="" disabled>Pilih penitip</option>
                                <option v-for="c in consignors" :key="c.id" :value="c.id">{{ c.code }} — {{ c.name }}</option>
                            </select>
                        </Field>
                        <Field label="Kategori" :error="form.errors.category_id" required>
                            <select v-model="form.category_id" class="input">
                                <option value="" disabled>Pilih kategori</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </Field>
                    </div>
                    <Field label="Judul barang" :error="form.errors.title" required><input v-model="form.title" class="input" placeholder="mis. Kamera Sony A7 III + Lensa 28-70" /></Field>
                    <Field label="Deskripsi" :error="form.errors.description"><textarea v-model="form.description" rows="5" class="input" /></Field>
                    <Field label="Kondisi" :error="form.errors.condition" required>
                        <div class="flex flex-wrap gap-2">
                            <label v-for="c in conditions" :key="c.value" class="cursor-pointer rounded-full px-3 py-1.5 text-sm ring-1"
                                :class="form.condition === c.value ? 'bg-ink text-white ring-ink' : 'ring-stone-300'">
                                <input v-model="form.condition" type="radio" :value="c.value" class="hidden" />{{ c.label }}
                            </label>
                        </div>
                    </Field>
                </section>

                <section v-if="schema.length" class="card space-y-4 p-6">
                    <h2 class="font-semibold text-ink">Spesifikasi kategori</h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <Field v-for="f in schema" :key="f.key" :label="f.label" :required="f.required" :error="form.errors[`specs.${f.key}`]"
                            :class="f.type === 'textarea' ? 'md:col-span-2' : ''">
                            <select v-if="f.type === 'select'" v-model="form.specs[f.key]" class="input">
                                <option value="">-</option>
                                <option v-for="o in f.options" :key="o" :value="o">{{ o }}</option>
                            </select>
                            <textarea v-else-if="f.type === 'textarea'" v-model="form.specs[f.key]" rows="3" class="input" />
                            <input v-else v-model="form.specs[f.key]" :type="f.type === 'number' ? 'number' : 'text'" class="input" />
                        </Field>
                    </div>
                </section>

                <section class="card space-y-4 p-6">
                    <h2 class="font-semibold text-ink">Foto</h2>
                    <div v-if="it?.images?.length" class="flex flex-wrap gap-3">
                        <div v-for="img in it.images" :key="img.id" class="relative h-24 w-32 overflow-hidden rounded-xl">
                            <img :src="img.url" class="h-full w-full object-cover" alt="" />
                            <button type="button" class="absolute top-1 right-1 rounded-full bg-black/60 px-2 text-xs text-white" @click="removeImage(img)">✕</button>
                        </div>
                    </div>
                    <input type="file" accept="image/*" multiple class="block text-sm" @input="form.images = Array.from($event.target.files)" />
                    <div v-if="previews.length" class="flex flex-wrap gap-3">
                        <img v-for="(p, i) in previews" :key="i" :src="p" class="h-24 w-32 rounded-xl object-cover" alt="" />
                    </div>
                    <p class="text-xs text-stone-500">Maks. 12 foto, 8 MB/foto. Foto otomatis dikompres ke WebP dan metadata (lokasi GPS) dihapus.</p>
                    <p v-for="(msg, k) in form.errors" v-show="k.startsWith('images')" :key="k" class="text-xs text-red-600">{{ msg }}</p>
                </section>
            </div>

            <div class="space-y-6">
                <section class="card space-y-4 p-6">
                    <h2 class="font-semibold text-ink">Harga & komisi</h2>
                    <Field label="Harga limit (reserve)" :error="form.errors.reserve_price" hint="Harga minimum yang disepakati penitip. Tidak ditampilkan ke peserta." required>
                        <MoneyInput v-model="form.reserve_price" />
                    </Field>
                    <div class="grid grid-cols-2 gap-3">
                        <Field label="Estimasi bawah" :error="form.errors.estimate_low"><MoneyInput v-model="form.estimate_low" /></Field>
                        <Field label="Estimasi atas" :error="form.errors.estimate_high"><MoneyInput v-model="form.estimate_high" /></Field>
                    </div>
                    <Field label="Komisi khusus (%)" :error="form.errors.commission_rate"
                        :hint="`Kosongkan untuk memakai komisi penitip${consignor ? ` (${consignor.commission_rate}%)` : ''}.`">
                        <input v-model="form.commission_rate" type="number" step="0.5" min="0" max="50" class="input" />
                    </Field>
                </section>
                <section class="card space-y-4 p-6">
                    <h2 class="font-semibold text-ink">Gudang & inspeksi</h2>
                    <Field label="Tanggal diterima" :error="form.errors.received_at"><input v-model="form.received_at" type="date" class="input" /></Field>
                    <Field label="Lokasi penyimpanan" :error="form.errors.storage_location"><input v-model="form.storage_location" class="input" placeholder="Rak A-3" /></Field>
                    <Field label="Catatan inspeksi" :error="form.errors.inspection_notes"><textarea v-model="form.inspection_notes" rows="3" class="input" placeholder="Lecet halus di sisi kiri, baterai 87%…" /></Field>
                </section>
                <div class="flex gap-2">
                    <button class="btn-primary flex-1" :disabled="form.processing">{{ it ? 'Simpan perubahan' : 'Simpan barang' }}</button>
                    <Link :href="it ? route('admin.items.show', it.id) : route('admin.items.index')" class="btn-outline">Batal</Link>
                </div>
            </div>
        </form>
    </AdminLayout>
</template>
