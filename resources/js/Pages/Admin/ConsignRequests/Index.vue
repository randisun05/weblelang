<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { dateTime, money } from '@/lib/format';

defineProps({ requests: Object, status: String, statuses: Array, counts: Object });
</script>

<template>
    <Head title="Pengajuan Titip" />
    <AdminLayout title="Pengajuan Titip Barang (online)">
        <div class="mb-4 flex flex-wrap gap-2">
            <Link :href="route('admin.consign-requests.index')" class="rounded-full px-3 py-1.5 text-sm" :class="!status ? 'bg-ink text-white' : 'bg-white ring-1 ring-stone-200'">
                Perlu diproses <span class="opacity-60">{{ (counts.new ?? 0) + (counts.reviewing ?? 0) }}</span>
            </Link>
            <Link v-for="s in statuses" :key="s.value" :href="route('admin.consign-requests.index', { status: s.value })" class="rounded-full px-3 py-1.5 text-sm"
                :class="status === s.value ? 'bg-ink text-white' : 'bg-white ring-1 ring-stone-200'">
                {{ s.label }} <span class="opacity-60">{{ counts[s.value] ?? 0 }}</span>
            </Link>
            <a :href="route('consign.create')" target="_blank" class="link ml-auto self-center text-sm">Lihat form publik ↗</a>
        </div>
        <div v-if="requests.data.length" class="card overflow-x-auto">
            <table class="tbl">
                <thead><tr><th>Kode</th><th>Barang</th><th>Pengaju</th><th>Harapan harga</th><th>Masuk</th><th>Status</th><th></th></tr></thead>
                <tbody class="divide-y divide-stone-100">
                    <tr v-for="r in requests.data" :key="r.id">
                        <td class="font-mono text-xs">{{ r.code }}</td>
                        <td><span class="font-medium text-ink">{{ r.title }}</span><br /><span class="text-xs text-stone-500">{{ r.category || 'Kategori belum dipilih' }} · {{ r.photos_count }} foto</span></td>
                        <td>{{ r.name }}<br /><span class="text-xs text-stone-500">{{ r.phone }} · {{ r.city }}</span></td>
                        <td>{{ r.expected_price ? money(r.expected_price) : '-' }}</td>
                        <td class="text-xs">{{ dateTime(r.created_at) }}</td>
                        <td><StatusBadge :status="r.status" /></td>
                        <td class="text-right"><Link :href="route('admin.consign-requests.show', r.id)" class="link">Tinjau</Link></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="Tidak ada pengajuan" icon="📮" />
        <Pagination :links="requests.links" />
    </AdminLayout>
</template>
