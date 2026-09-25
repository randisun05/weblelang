<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import LotImage from '@/Components/LotImage.vue';
import { money } from '@/lib/format';

const props = defineProps({ items: Object, filters: Object, statuses: Array, categories: Array, counts: Object });
const f = reactive({ q: props.filters.q ?? '', status: props.filters.status ?? '', category: props.filters.category ?? '' });
let t;
watch(f, () => { clearTimeout(t); t = setTimeout(() => router.get(route('admin.items.index'), f, { preserveState: true, replace: true }), 300); });
</script>

<template>
    <Head title="Barang Titipan" />
    <AdminLayout title="Barang Titipan">
        <!-- Alur kerja: klik status untuk filter -->
        <div class="mb-4 flex gap-2 overflow-x-auto pb-1">
            <button class="shrink-0 rounded-full px-3 py-1.5 text-sm" :class="!f.status ? 'bg-ink text-white' : 'bg-white ring-1 ring-stone-200'" @click="f.status = ''">Semua</button>
            <button v-for="s in statuses" :key="s.value" class="shrink-0 rounded-full px-3 py-1.5 text-sm"
                :class="f.status === s.value ? 'bg-ink text-white' : 'bg-white ring-1 ring-stone-200'" @click="f.status = s.value">
                {{ s.label }} <span class="ml-1 opacity-60">{{ counts[s.value] ?? 0 }}</span>
            </button>
        </div>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap gap-2">
                <input v-model="f.q" class="input max-w-xs" placeholder="Cari judul / kode…" />
                <select v-model="f.category" class="input max-w-52">
                    <option value="">Semua kategori</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>
            <Link :href="route('admin.items.create')" class="btn-primary">+ Terima barang</Link>
        </div>

        <div v-if="items.data.length" class="card overflow-x-auto">
            <table class="tbl">
                <thead><tr><th></th><th>Barang</th><th>Penitip</th><th>Harga limit</th><th>Status</th><th></th></tr></thead>
                <tbody class="divide-y divide-stone-100">
                    <tr v-for="i in items.data" :key="i.id">
                        <td class="w-16"><div class="h-12 w-16 overflow-hidden rounded-lg"><LotImage :src="i.image" class="text-2xl" /></div></td>
                        <td><span class="font-mono text-xs text-stone-500">{{ i.code }}</span><br /><span class="font-medium text-ink">{{ i.title }}</span><br /><span class="text-xs text-stone-500">{{ i.category }}</span></td>
                        <td>{{ i.consignor }}</td>
                        <td>{{ money(i.reserve_price) }}</td>
                        <td><StatusBadge :status="i.status" /></td>
                        <td class="text-right"><Link :href="route('admin.items.show', i.id)" class="link">Detail</Link></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="Tidak ada barang" icon="📦" />
        <Pagination :links="items.links" />
    </AdminLayout>
</template>
