<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { dateTime, money } from '@/lib/format';

const props = defineProps({ invoices: Object, filters: Object, statuses: Array });
const f = reactive({ q: props.filters.q ?? '', status: props.filters.status ?? '' });
let t;
watch(f, () => { clearTimeout(t); t = setTimeout(() => router.get(route('admin.invoices.index'), f, { preserveState: true, replace: true }), 300); });
</script>

<template>
    <Head title="Invoice" />
    <AdminLayout title="Invoice Pemenang">
        <div class="mb-4 flex flex-wrap gap-2">
            <input v-model="f.q" class="input max-w-xs" placeholder="Cari nomor invoice…" />
            <select v-model="f.status" class="input max-w-52">
                <option value="">Semua status</option>
                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
        </div>
        <div v-if="invoices.data.length" class="card overflow-x-auto">
            <table class="tbl">
                <thead><tr><th>No.</th><th>Pemenang</th><th>Barang</th><th>Total</th><th>Jatuh tempo</th><th>Status</th><th></th></tr></thead>
                <tbody class="divide-y divide-stone-100">
                    <tr v-for="i in invoices.data" :key="i.id">
                        <td class="font-mono text-xs">{{ i.number }}</td>
                        <td>{{ i.user }}</td>
                        <td>{{ i.title }}</td>
                        <td class="font-semibold">{{ money(i.total) }}</td>
                        <td class="text-xs" :class="i.overdue ? 'font-bold text-red-600' : ''">{{ dateTime(i.due_at) }}<span v-if="i.overdue"> (lewat)</span></td>
                        <td>
                            <StatusBadge :status="i.status" />
                            <span v-if="i.has_proof && i.status.value === 'unpaid'" class="ml-1 text-xs text-sky-700">bukti masuk</span>
                            <span v-if="i.delivered" class="ml-1 text-xs text-emerald-700">diserahkan</span>
                        </td>
                        <td class="text-right"><Link :href="route('admin.invoices.show', i.id)" class="link">Kelola</Link></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="Belum ada invoice" icon="🧾" />
        <Pagination :links="invoices.links" />
    </AdminLayout>
</template>
