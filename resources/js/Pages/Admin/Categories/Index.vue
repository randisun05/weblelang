<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Field from '@/Components/Field.vue';

defineProps({ categories: Array });

const editing = ref(null);
const blank = () => ({ name: '', icon: '', attribute_schema: [] });
const form = useForm(blank());

const edit = (cat) => {
    editing.value = cat.id;
    form.defaults({ name: cat.name, icon: cat.icon ?? '', attribute_schema: JSON.parse(JSON.stringify(cat.attribute_schema ?? [])) });
    form.reset();
};
const reset = () => { editing.value = null; form.defaults(blank()); form.reset(); form.clearErrors(); };
const addField = () => form.attribute_schema.push({ key: '', label: '', type: 'text', options: [], required: false });
const submit = () => {
    const opts = { preserveScroll: true, onSuccess: reset };
    editing.value ? form.put(route('admin.categories.update', editing.value), opts) : form.post(route('admin.categories.store'), opts);
};
const destroy = (cat) => confirm(`Hapus kategori ${cat.name}?`) && router.delete(route('admin.categories.destroy', cat.id), { preserveScroll: true });
const slugKey = (f) => { if (!f.key) f.key = f.label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, ''); };
</script>

<template>
    <Head title="Kategori" />
    <AdminLayout title="Kategori & Atribut Barang">
        <div class="grid gap-6 lg:grid-cols-[1fr_480px]">
            <section class="card overflow-x-auto">
                <table class="tbl">
                    <thead><tr><th>Kategori</th><th>Atribut</th><th>Barang</th><th></th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        <tr v-for="cat in categories" :key="cat.id">
                            <td class="font-medium text-ink">{{ cat.icon }} {{ cat.name }}</td>
                            <td class="text-xs text-stone-500">{{ (cat.attribute_schema ?? []).map((f) => f.label).join(', ') || '-' }}</td>
                            <td>{{ cat.items_count }}</td>
                            <td class="space-x-3 text-right whitespace-nowrap">
                                <button class="link" @click="edit(cat)">Ubah</button>
                                <button v-if="!cat.items_count" class="text-red-600 hover:underline" @click="destroy(cat)">Hapus</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <form class="card h-fit space-y-4 p-6" @submit.prevent="submit">
                <h2 class="font-semibold text-ink">{{ editing ? 'Ubah kategori' : 'Kategori baru' }}</h2>
                <div class="grid grid-cols-[1fr_90px] gap-3">
                    <Field label="Nama" :error="form.errors.name" required><input v-model="form.name" class="input" /></Field>
                    <Field label="Ikon"><input v-model="form.icon" class="input text-center" placeholder="📦" maxlength="4" /></Field>
                </div>
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <p class="label mb-0">Atribut dinamis</p>
                        <button type="button" class="btn-outline btn-sm" @click="addField">+ Field</button>
                    </div>
                    <p class="mb-3 text-xs text-stone-500">Field tambahan saat input barang, mis. Merk, Tahun, Nopol. Membuat platform bisa dipakai untuk jenis barang apa pun.</p>
                    <div v-for="(f, i) in form.attribute_schema" :key="i" class="mb-3 space-y-2 rounded-xl bg-stone-50 p-3">
                        <div class="grid grid-cols-2 gap-2">
                            <input v-model="f.label" class="input" placeholder="Label" @blur="slugKey(f)" />
                            <input v-model="f.key" class="input font-mono text-xs" placeholder="key" />
                        </div>
                        <div class="flex items-center gap-2">
                            <select v-model="f.type" class="input">
                                <option value="text">Teks</option><option value="number">Angka</option>
                                <option value="textarea">Paragraf</option><option value="select">Pilihan</option>
                            </select>
                            <label class="flex shrink-0 items-center gap-1 text-xs"><input v-model="f.required" type="checkbox" class="rounded" /> Wajib</label>
                            <button type="button" class="shrink-0 text-red-600" @click="form.attribute_schema.splice(i, 1)">✕</button>
                        </div>
                        <input v-if="f.type === 'select'" :value="(f.options ?? []).join(', ')" class="input" placeholder="Opsi, pisahkan dengan koma"
                            @change="f.options = $event.target.value.split(',').map((s) => s.trim()).filter(Boolean)" />
                        <p v-for="(msg, k) in form.errors" v-show="k.startsWith(`attribute_schema.${i}.`)" :key="k" class="text-xs text-red-600">{{ msg }}</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button class="btn-primary" :disabled="form.processing">Simpan</button>
                    <button v-if="editing" type="button" class="btn-outline" @click="reset">Batal</button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
