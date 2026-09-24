<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Field from '@/Components/Field.vue';

const props = defineProps({ consignor: Object, defaultCommission: Number });
const c = props.consignor;

const form = useForm({
    name: c?.name ?? '', phone: c?.phone ?? '', email: c?.email ?? '', address: c?.address ?? '', nik: '',
    bank_name: c?.bank_name ?? '', bank_account: '', bank_holder: c?.bank_holder ?? '',
    commission_rate: c?.commission_rate ?? props.defaultCommission, notes: c?.notes ?? '',
});

const submit = () => (c ? form.put(route('admin.consignors.update', c.id)) : form.post(route('admin.consignors.store')));
</script>

<template>
    <Head :title="c ? 'Ubah Penitip' : 'Penitip Baru'" />
    <AdminLayout :title="c ? `Ubah Penitip: ${c.name}` : 'Penitip Baru'">
        <form class="card max-w-3xl space-y-6 p-6" @submit.prevent="submit">
            <div class="grid gap-4 md:grid-cols-2">
                <Field label="Nama lengkap" :error="form.errors.name" required><input v-model="form.name" class="input" /></Field>
                <Field label="Nomor HP" :error="form.errors.phone" required><input v-model="form.phone" class="input" /></Field>
                <Field label="Email" :error="form.errors.email" hint="Dipakai juga untuk mencegah penitip menawar barangnya sendiri.">
                    <input v-model="form.email" type="email" class="input" />
                </Field>
                <Field label="NIK" :error="form.errors.nik" :hint="c?.has_nik ? 'Tersimpan (terenkripsi). Kosongkan jika tidak diubah.' : 'Disimpan terenkripsi.'">
                    <input v-model="form.nik" inputmode="numeric" maxlength="16" class="input font-mono" />
                </Field>
                <Field class="md:col-span-2" label="Alamat" :error="form.errors.address"><textarea v-model="form.address" rows="2" class="input" /></Field>
            </div>
            <div class="border-t border-stone-100 pt-6">
                <h3 class="mb-3 font-semibold text-ink">Rekening pencairan hasil lelang</h3>
                <div class="grid gap-4 md:grid-cols-3">
                    <Field label="Bank" :error="form.errors.bank_name"><input v-model="form.bank_name" class="input" placeholder="BCA" /></Field>
                    <Field label="No. rekening" :error="form.errors.bank_account" :hint="c?.bank_account_masked ? `Tersimpan: ${c.bank_account_masked}. Kosongkan jika tidak diubah.` : 'Disimpan terenkripsi.'">
                        <input v-model="form.bank_account" inputmode="numeric" class="input font-mono" />
                    </Field>
                    <Field label="Atas nama" :error="form.errors.bank_holder"><input v-model="form.bank_holder" class="input" /></Field>
                </div>
            </div>
            <div class="grid gap-4 border-t border-stone-100 pt-6 md:grid-cols-3">
                <Field label="Komisi (%)" :error="form.errors.commission_rate" hint="Dipotong dari harga palu." required>
                    <input v-model="form.commission_rate" type="number" step="0.5" min="0" max="50" class="input" />
                </Field>
                <Field class="md:col-span-2" label="Catatan perjanjian" :error="form.errors.notes"><textarea v-model="form.notes" rows="2" class="input" /></Field>
            </div>
            <div class="flex gap-2">
                <button class="btn-primary" :disabled="form.processing">Simpan</button>
                <Link :href="c ? route('admin.consignors.show', c.id) : route('admin.consignors.index')" class="btn-outline">Batal</Link>
            </div>
        </form>
    </AdminLayout>
</template>
