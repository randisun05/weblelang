<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { dateTime } from '@/lib/format';

defineProps({ auctions: Object });
</script>

<template>
    <Head title="Sesi Lelang" />
    <AdminLayout title="Sesi Lelang">
        <div class="mb-4 flex justify-end"><Link :href="route('admin.auctions.create')" class="btn-primary">+ Sesi baru</Link></div>
        <div v-if="auctions.data.length" class="card overflow-x-auto">
            <table class="tbl">
                <thead><tr><th>Kode</th><th>Judul</th><th>Jadwal</th><th>Lot</th><th>Terjual</th><th>Bid</th><th>Status</th><th></th></tr></thead>
                <tbody class="divide-y divide-stone-100">
                    <tr v-for="a in auctions.data" :key="a.id">
                        <td class="font-mono text-xs">{{ a.code }}</td>
                        <td class="font-medium text-ink">{{ a.title }}<br /><StatusBadge :status="a.method" /></td>
                        <td class="text-xs">{{ dateTime(a.starts_at) }}<br />s/d {{ dateTime(a.ends_at) }}</td>
                        <td>{{ a.lots_count }}</td><td>{{ a.sold_count }}</td><td>{{ a.bids_total }}</td>
                        <td><StatusBadge :status="a.status" /></td>
                        <td class="text-right"><Link :href="route('admin.auctions.show', a.id)" class="link">Kelola</Link></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="Belum ada sesi lelang" icon="🔨" />
        <Pagination :links="auctions.links" />
    </AdminLayout>
</template>
