<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { dateTime } from '@/lib/format';

const props = defineProps({ bidders: Object, filters: Object, kycStatuses: Array });
const f = reactive({ q: props.filters.q ?? '', kyc: props.filters.kyc ?? '' });
let t;
watch(f, () => { clearTimeout(t); t = setTimeout(() => router.get(route('admin.bidders.index'), f, { preserveState: true, replace: true }), 300); });
</script>

<template>
    <Head title="Peserta" />
    <AdminLayout title="Peserta & Verifikasi KYC">
        <div class="mb-4 flex flex-wrap gap-2">
            <input v-model="f.q" class="input max-w-xs" placeholder="Cari nama / email…" />
            <select v-model="f.kyc" class="input max-w-52">
                <option value="">Semua status KYC</option>
                <option v-for="s in kycStatuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
        </div>
        <div v-if="bidders.data.length" class="card overflow-x-auto">
            <table class="tbl">
                <thead><tr><th>Peserta</th><th>HP</th><th>Bid</th><th>KYC</th><th>Terdaftar</th><th></th></tr></thead>
                <tbody class="divide-y divide-stone-100">
                    <tr v-for="b in bidders.data" :key="b.id">
                        <td><span class="font-medium text-ink">{{ b.name }}</span> <span v-if="b.is_blocked" class="text-xs text-red-600">(diblokir)</span><br /><span class="text-xs text-stone-500">{{ b.email }}</span></td>
                        <td>{{ b.phone }}</td>
                        <td>{{ b.bids_count }}</td>
                        <td><StatusBadge :status="b.kyc" /></td>
                        <td class="text-xs">{{ dateTime(b.created_at) }}</td>
                        <td class="text-right"><Link :href="route('admin.bidders.show', b.id)" class="link">{{ b.kyc.value === 'pending' ? 'Verifikasi' : 'Detail' }}</Link></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="Tidak ada peserta" icon="🪪" />
        <Pagination :links="bidders.links" />
    </AdminLayout>
</template>
