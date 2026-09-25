<script setup>
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import Field from '@/Components/Field.vue';
import { money, num } from '@/lib/format';

const props = defineProps({ filters: Object, summary: Object });
const f = reactive({ ...props.filters });

const apply = () => router.get(route('admin.reports.index'), f, { preserveState: true, replace: true });
const preset = (kind) => {
    const now = new Date();
    const iso = (d) => d.toLocaleDateString('en-CA');
    if (kind === 'month') { f.from = iso(new Date(now.getFullYear(), now.getMonth(), 1)); f.to = iso(now); }
    if (kind === 'last') { f.from = iso(new Date(now.getFullYear(), now.getMonth() - 1, 1)); f.to = iso(new Date(now.getFullYear(), now.getMonth(), 0)); }
    if (kind === 'year') { f.from = iso(new Date(now.getFullYear(), 0, 1)); f.to = iso(now); }
    apply();
};
const sellRate = () => (props.summary.lots_closed ? Math.round((props.summary.lots_sold / props.summary.lots_closed) * 100) : 0);
</script>

<template>
    <Head title="Laporan" />
    <AdminLayout title="Laporan & Ekspor">
        <form class="card mb-6 flex flex-wrap items-end gap-3 p-5" @submit.prevent="apply">
            <Field label="Dari"><input v-model="f.from" type="date" class="input" /></Field>
            <Field label="Sampai"><input v-model="f.to" type="date" class="input" /></Field>
            <button class="btn-dark">Terapkan</button>
            <div class="flex gap-1 text-sm">
                <button type="button" class="btn-outline btn-sm" @click="preset('month')">Bulan ini</button>
                <button type="button" class="btn-outline btn-sm" @click="preset('last')">Bulan lalu</button>
                <button type="button" class="btn-outline btn-sm" @click="preset('year')">Tahun ini</button>
            </div>
            <div class="ml-auto flex gap-2">
                <a :href="route('admin.reports.sales', filters)" class="btn-primary">⬇ Excel penjualan</a>
                <a :href="route('admin.reports.settlements', filters)" class="btn-outline">⬇ Excel settlement</a>
            </div>
        </form>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Nilai terjual (harga palu, lunas)" :value="money(summary.gmv)" tone="green" />
            <StatCard label="Pendapatan komisi penitip" :value="money(summary.commission)" />
            <StatCard label="Premi pembeli + biaya admin" :value="money(summary.buyer_premium + summary.admin_fee)" />
            <StatCard label="Tingkat laku" :value="`${sellRate()}%`" :hint="`${num(summary.lots_sold)} dari ${num(summary.lots_closed)} lot ditutup`" />
            <StatCard label="Invoice terbit" :value="num(summary.invoices)" :hint="`${num(summary.invoices_paid)} lunas · ${num(summary.invoices_cancelled)} batal`" />
            <StatCard label="Settlement ditransfer" :value="money(summary.settlement_paid)" tone="green" />
            <StatCard label="Settlement menunggu" :value="money(summary.settlement_pending)" tone="amber" />
        </div>
        <p class="mt-4 text-xs text-stone-500">Invoice & settlement dihitung dari tanggal terbit; lot dari tanggal penutupan. File Excel siap dipakai untuk rekap keuangan dan daftar transfer ke penitip.</p>
    </AdminLayout>
</template>
