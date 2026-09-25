<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { dateTime, money } from '@/lib/format';

defineProps({ tab: String, rows: Object, gateways: Object });
</script>

<template>
    <Head title="Transaksi Gateway" />
    <AdminLayout title="Transaksi Payment Gateway">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <div class="flex gap-1 rounded-xl bg-stone-200/60 p-1 text-sm font-medium">
                <Link :href="route('admin.transactions.index')" class="rounded-lg px-4 py-1.5" :class="tab === 'payments' ? 'bg-white shadow-sm' : ''">💳 Uang masuk</Link>
                <Link :href="route('admin.transactions.index', { tab: 'payouts' })" class="rounded-lg px-4 py-1.5" :class="tab === 'payouts' ? 'bg-white shadow-sm' : ''">🏦 Uang keluar</Link>
            </div>
            <p class="text-xs text-stone-500">
                Gateway pembayaran: <b>{{ gateways.payment || 'nonaktif' }}</b> · payout: <b>{{ gateways.payout || 'manual' }}</b>
            </p>
        </div>
        <div v-if="rows.data.length" class="card overflow-x-auto">
            <table class="tbl">
                <thead><tr><th>Referensi</th><th>Waktu</th><th>Untuk</th><th>{{ tab === 'payments' ? 'Pembayar' : 'Rekening tujuan' }}</th><th>Nominal</th><th>{{ tab === 'payments' ? 'Metode' : 'Diminta oleh' }}</th><th>Status</th></tr></thead>
                <tbody class="divide-y divide-stone-100">
                    <tr v-for="r in rows.data" :key="r.reference">
                        <td><span class="font-mono text-xs">{{ r.reference }}</span><br /><span class="text-xs text-stone-500">{{ r.gateway }} {{ r.provider_ref }}</span></td>
                        <td class="text-xs whitespace-nowrap">{{ dateTime(r.at) }}</td>
                        <td>{{ r.subject }}</td>
                        <td class="text-xs">{{ r.party }}</td>
                        <td class="font-semibold">{{ money(r.amount) }}</td>
                        <td class="text-xs">{{ r.method || '-' }}</td>
                        <td><StatusBadge :status="r.status" /><span v-if="r.failure_reason" class="block max-w-48 text-xs text-red-600">{{ r.failure_reason }}</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="Belum ada transaksi" icon="💳" />
        <Pagination :links="rows.links" />
    </AdminLayout>
</template>
