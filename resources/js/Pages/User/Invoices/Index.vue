<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AccountLayout from '@/Layouts/AccountLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { dateTime, money } from '@/lib/format';

defineProps({ invoices: Object });
</script>

<template>
    <Head title="Invoice" />
    <AccountLayout title="Invoice Saya">
        <div v-if="invoices.data.length" class="card overflow-x-auto">
            <table class="tbl">
                <thead><tr><th>No.</th><th>Barang</th><th>Total</th><th>Jatuh tempo</th><th>Status</th><th></th></tr></thead>
                <tbody class="divide-y divide-stone-100">
                    <tr v-for="inv in invoices.data" :key="inv.id">
                        <td class="font-mono text-xs">{{ inv.number }}</td>
                        <td>{{ inv.title }}</td>
                        <td class="font-semibold">{{ money(inv.total) }}</td>
                        <td>{{ dateTime(inv.due_at) }}</td>
                        <td><StatusBadge :status="inv.status" /></td>
                        <td class="text-right"><Link :href="route('user.invoices.show', inv.id)" class="link">Detail →</Link></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="Belum ada invoice" description="Invoice muncul otomatis saat Anda memenangkan lot." icon="🧾" />
        <Pagination :links="invoices.links" />
    </AccountLayout>
</template>
