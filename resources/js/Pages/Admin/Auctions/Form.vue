<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Field from '@/Components/Field.vue';
import MoneyInput from '@/Components/MoneyInput.vue';

const props = defineProps({ auction: Object, defaults: Object });
const a = props.auction;

const form = useForm({
    title: a?.title ?? '',
    description: a?.description ?? '',
    starts_at: a?.starts_at ?? '',
    ends_at: a?.ends_at ?? '',
    deposit_amount: a?.deposit_amount ?? 0,
    buyer_premium_rate: a?.buyer_premium_rate ?? props.defaults.buyer_premium_rate,
    anti_snipe_minutes: a?.anti_snipe_minutes ?? props.defaults.anti_snipe_minutes,
    extend_minutes: a?.extend_minutes ?? props.defaults.extend_minutes,
    stagger_seconds: a?.stagger_seconds ?? 0,
});

const submit = () => (a ? form.put(route('admin.auctions.update', a.id)) : form.post(route('admin.auctions.store')));
</script>

<template>
    <Head :title="a ? 'Ubah Sesi' : 'Sesi Baru'" />
    <AdminLayout :title="a ? `Ubah Sesi: ${a.title}` : 'Sesi Lelang Baru'">
        <form class="card max-w-3xl space-y-6 p-6" @submit.prevent="submit">
            <Field label="Judul sesi" :error="form.errors.title" required><input v-model="form.title" class="input" placeholder="Lelang Mingguan Elektronik" /></Field>
            <Field label="Deskripsi / ketentuan khusus" :error="form.errors.description"><textarea v-model="form.description" rows="4" class="input" /></Field>
            <div class="grid gap-4 md:grid-cols-2">
                <Field label="Mulai" :error="form.errors.starts_at" required><input v-model="form.starts_at" type="datetime-local" class="input" /></Field>
                <Field label="Selesai" :error="form.errors.ends_at" required><input v-model="form.ends_at" type="datetime-local" class="input" /></Field>
            </div>
            <div class="grid gap-4 border-t border-stone-100 pt-6 md:grid-cols-2">
                <Field label="Uang jaminan" :error="form.errors.deposit_amount" hint="0 = tanpa jaminan. Jika diisi, peserta wajib mendaftar & disetujui.">
                    <MoneyInput v-model="form.deposit_amount" />
                </Field>
                <Field label="Premi pembeli (%)" :error="form.errors.buyer_premium_rate" hint="Ditambahkan ke harga palu pada invoice pemenang.">
                    <input v-model="form.buyer_premium_rate" type="number" step="0.5" min="0" max="30" class="input" />
                </Field>
            </div>
            <div class="grid gap-4 border-t border-stone-100 pt-6 md:grid-cols-3">
                <Field label="Anti-sniping (menit)" :error="form.errors.anti_snipe_minutes" hint="Bid di menit terakhir ini memperpanjang waktu. 0 = mati.">
                    <input v-model="form.anti_snipe_minutes" type="number" min="0" max="60" class="input" />
                </Field>
                <Field label="Perpanjangan (menit)" :error="form.errors.extend_minutes"><input v-model="form.extend_minutes" type="number" min="0" max="60" class="input" /></Field>
                <Field label="Jeda tutup antar lot (detik)" :error="form.errors.stagger_seconds" hint="Lot tutup bergiliran. 0 = serentak.">
                    <input v-model="form.stagger_seconds" type="number" min="0" max="600" class="input" />
                </Field>
            </div>
            <div class="flex gap-2">
                <button class="btn-primary" :disabled="form.processing">Simpan</button>
                <Link :href="a ? route('admin.auctions.show', a.id) : route('admin.auctions.index')" class="btn-outline">Batal</Link>
            </div>
        </form>
    </AdminLayout>
</template>
