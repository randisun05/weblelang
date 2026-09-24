<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import StatCard from '@/Components/StatCard.vue';
import { dateTime, money } from '@/lib/format';

const props = defineProps({ consignor: Object, items: Array, settlements: Array, totals: Object });
const destroy = () => confirm('Hapus penitip ini?') && router.delete(route('admin.consignors.destroy', props.consignor.id));
</script>

<template>
    <Head :title="consignor.name" />
    <AdminLayout :title="`${consignor.code} — ${consignor.name}`">
        <div class="grid gap-6 lg:grid-cols-[340px_1fr]">
            <section class="card h-fit p-6 text-sm">
                <dl class="space-y-3">
                    <div><dt class="text-stone-500">Kontak</dt><dd class="font-medium">{{ consignor.phone }}<br />{{ consignor.email }}</dd></div>
                    <div><dt class="text-stone-500">Alamat</dt><dd>{{ consignor.address || '-' }}</dd></div>
                    <div><dt class="text-stone-500">Rekening</dt><dd>{{ consignor.bank_name }} {{ consignor.bank_account }}<br />a.n. {{ consignor.bank_holder }}</dd></div>
                    <div><dt class="text-stone-500">Komisi default</dt><dd class="font-medium">{{ consignor.commission_rate }}%</dd></div>
                    <div v-if="consignor.notes"><dt class="text-stone-500">Catatan</dt><dd class="whitespace-pre-line">{{ consignor.notes }}</dd></div>
                </dl>
                <div class="mt-6 flex flex-wrap gap-2">
                    <Link :href="route('admin.consignors.edit', consignor.id)" class="btn-outline btn-sm">Ubah</Link>
                    <Link :href="route('admin.items.create', { consignor: consignor.id })" class="btn-primary btn-sm">+ Terima barang</Link>
                    <button v-if="!items.length" class="btn-danger btn-sm" @click="destroy">Hapus</button>
                </div>
            </section>
            <div class="space-y-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <StatCard label="Sudah ditransfer" :value="money(totals.net_paid)" tone="green" />
                    <StatCard label="Menunggu transfer" :value="money(totals.net_pending)" tone="amber" />
                </div>
                <section class="card overflow-x-auto">
                    <h2 class="px-4 pt-4 font-semibold text-ink">Barang titipan ({{ items.length }})</h2>
                    <table class="tbl mt-2">
                        <thead><tr><th>Kode</th><th>Barang</th><th>Limit</th><th>Status</th></tr></thead>
                        <tbody class="divide-y divide-stone-100">
                            <tr v-for="i in items" :key="i.id">
                                <td class="font-mono text-xs">{{ i.code }}</td>
                                <td><Link :href="route('admin.items.show', i.id)" class="link">{{ i.title }}</Link></td>
                                <td>{{ money(i.reserve_price) }}</td>
                                <td><StatusBadge :status="i.status" /></td>
                            </tr>
                        </tbody>
                    </table>
                </section>
                <section v-if="settlements.length" class="card overflow-x-auto">
                    <h2 class="px-4 pt-4 font-semibold text-ink">Settlement</h2>
                    <table class="tbl mt-2">
                        <thead><tr><th>No.</th><th>Harga palu</th><th>Komisi</th><th>Diterima</th><th>Status</th></tr></thead>
                        <tbody class="divide-y divide-stone-100">
                            <tr v-for="s in settlements" :key="s.id">
                                <td class="font-mono text-xs">{{ s.number }}</td>
                                <td>{{ money(s.hammer_price) }}</td>
                                <td>{{ money(s.commission) }}</td>
                                <td class="font-semibold">{{ money(s.net_amount) }}</td>
                                <td><StatusBadge :status="s.status" /><span v-if="s.paid_at" class="block text-xs text-stone-500">{{ dateTime(s.paid_at) }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
