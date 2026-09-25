<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { dateTime, esc, money } from '@/lib/format';

const props = defineProps({ settlements: Object, filters: Object, statuses: Array, payoutEnabled: Boolean });
const status = ref(props.filters.status ?? '');
const filter = () => router.get(route('admin.settlements.index'), { status: status.value }, { preserveState: true, replace: true });

const paying = ref(null);
const form = useForm({ proof: null });
const transfer = async (s) => {
    const { isConfirmed } = await Swal.fire({
        title: 'Transfer otomatis via gateway?',
        html: `${money(s.net_amount)} ke <b>${esc(s.consignor.bank_name)} ${esc(s.consignor.bank_account)}</b><br>a.n. ${esc(s.consignor.bank_holder)}`,
        icon: 'question', showCancelButton: true, confirmButtonText: 'Kirim transfer', cancelButtonText: 'Batal', confirmButtonColor: '#16a34a',
    });
    if (isConfirmed) router.post(route('admin.settlements.payout', s.id), {}, { preserveScroll: true });
};
const pay = () => form.post(route('admin.settlements.paid', paying.value.id), { forceFormData: true, preserveScroll: true, onSuccess: () => { paying.value = null; form.reset(); } });
</script>

<template>
    <Head title="Settlement" />
    <AdminLayout title="Settlement ke Penitip">
        <div class="mb-4">
            <select v-model="status" class="input max-w-52" @change="filter">
                <option value="">Semua status</option>
                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
        </div>
        <div v-if="settlements.data.length" class="card overflow-x-auto">
            <table class="tbl">
                <thead><tr><th>No.</th><th>Penitip & rekening</th><th>Barang</th><th>Harga palu</th><th>Komisi</th><th>Ditransfer</th><th>Status</th><th></th></tr></thead>
                <tbody class="divide-y divide-stone-100">
                    <tr v-for="s in settlements.data" :key="s.id">
                        <td class="font-mono text-xs">{{ s.number }}</td>
                        <td><Link :href="route('admin.consignors.show', s.consignor.id)" class="link">{{ s.consignor.name }}</Link><br />
                            <span class="font-mono text-xs text-stone-500">{{ s.consignor.bank_name }} {{ s.consignor.bank_account }} a.n. {{ s.consignor.bank_holder }}</span></td>
                        <td>{{ s.title }}</td>
                        <td>{{ money(s.hammer_price) }}</td>
                        <td class="text-xs">{{ money(s.commission) }}<br />({{ s.commission_rate }}%)</td>
                        <td class="font-bold">{{ money(s.net_amount) }}</td>
                        <td>
                            <StatusBadge :status="s.status" /><span v-if="s.paid_at" class="block text-xs text-stone-500">{{ dateTime(s.paid_at) }}</span>
                            <span v-if="s.payout" class="mt-1 block text-xs">Gateway: <StatusBadge :status="s.payout.status" /></span>
                            <span v-if="s.payout?.failure_reason" class="block max-w-40 text-xs text-red-600">{{ s.payout.failure_reason }}</span>
                        </td>
                        <td class="space-y-1 text-right whitespace-nowrap">
                            <template v-if="s.status.value === 'pending' && !['pending', 'processing'].includes(s.payout?.status.value)">
                                <button v-if="payoutEnabled" class="btn-primary btn-sm block w-full" @click="transfer(s)">{{ s.payout?.status.value === 'failed' ? 'Coba transfer lagi' : 'Transfer via gateway' }}</button>
                                <button class="btn-outline btn-sm block w-full" @click="paying = s">Tandai ditransfer manual</button>
                            </template>
                            <a v-else-if="s.has_proof" :href="route('admin.settlements.proof', s.id)" target="_blank" class="link text-xs">Bukti</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="Belum ada settlement" description="Settlement dibuat otomatis saat invoice pemenang lunas." icon="💸" />
        <Pagination :links="settlements.links" />

        <div v-if="paying" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="paying = null">
            <form class="card w-full max-w-md space-y-4 p-6" @submit.prevent="pay">
                <h3 class="text-lg font-bold text-ink">Transfer ke {{ paying.consignor.name }}</h3>
                <p class="rounded-xl bg-stone-50 p-3 text-sm">{{ paying.consignor.bank_name }} <b class="font-mono">{{ paying.consignor.bank_account }}</b> a.n. {{ paying.consignor.bank_holder }}<br />Nominal: <b>{{ money(paying.net_amount) }}</b></p>
                <input type="file" accept="image/*" class="block text-sm" @input="form.proof = $event.target.files[0]" />
                <p v-if="form.errors.proof" class="text-xs text-red-600">{{ form.errors.proof }}</p>
                <div class="flex gap-2">
                    <button class="btn-primary" :disabled="!form.proof || form.processing">Simpan bukti</button>
                    <button type="button" class="btn-outline" @click="paying = null">Batal</button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
