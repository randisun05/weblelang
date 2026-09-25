<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({ consignors: Object, filters: Object });
const q = ref(props.filters.q ?? '');
let t;
watch(q, (v) => { clearTimeout(t); t = setTimeout(() => router.get(route('admin.consignors.index'), { q: v }, { preserveState: true, replace: true }), 350); });
</script>

<template>
    <Head title="Penitip" />
    <AdminLayout title="Penitip Barang">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <input v-model="q" class="input max-w-xs" placeholder="Cari nama, kode, HP…" />
            <Link :href="route('admin.consignors.create')" class="btn-primary">+ Penitip baru</Link>
        </div>
        <div v-if="consignors.data.length" class="card overflow-x-auto">
            <table class="tbl">
                <thead><tr><th>Kode</th><th>Nama</th><th>Kontak</th><th>Komisi</th><th>Barang</th><th></th></tr></thead>
                <tbody class="divide-y divide-stone-100">
                    <tr v-for="c in consignors.data" :key="c.id">
                        <td class="font-mono text-xs">{{ c.code }}</td>
                        <td class="font-medium text-ink">{{ c.name }}</td>
                        <td>{{ c.phone }}<br /><span class="text-xs text-stone-500">{{ c.email }}</span></td>
                        <td>{{ c.commission_rate }}%</td>
                        <td>{{ c.items_count }}</td>
                        <td class="text-right"><Link :href="route('admin.consignors.show', c.id)" class="link">Detail</Link></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="Belum ada penitip" icon="🤝" />
        <Pagination :links="consignors.links" />
    </AdminLayout>
</template>
