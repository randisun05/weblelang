<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import LotImage from '@/Components/LotImage.vue';
import StatCard from '@/Components/StatCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { dateTime, money } from '@/lib/format';

const props = defineProps({ consignor: Object, items: Array, settlements: Array, expiresAt: String });
const sum = (rows, status) => rows.filter((r) => r.status.value === status).reduce((t, r) => t + r.net_amount, 0);
</script>

<template>
    <Head title="Portal Penitip" />
    <PublicLayout>
        <div class="mx-auto max-w-6xl px-4 py-8">
            <p class="text-sm text-stone-500">Portal Penitip · {{ consignor.code }}</p>
            <h1 class="text-2xl font-bold text-ink">Halo, {{ consignor.name }}</h1>
            <p class="mt-1 text-sm text-stone-500">
                Pantau status barang titipan dan hasil lelang Anda. Komisi {{ consignor.commission_rate }}% · Rekening {{ consignor.bank || '-' }}.
                Link berlaku sampai {{ dateTime(expiresAt) }}.
            </p>

            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <StatCard label="Barang dititipkan" :value="items.length" />
                <StatCard label="Sudah ditransfer ke Anda" :value="money(sum(settlements, 'paid'))" tone="green" />
                <StatCard label="Menunggu transfer" :value="money(sum(settlements, 'pending'))" tone="amber" />
            </div>

            <h2 class="mt-10 mb-4 text-lg font-bold text-ink">Barang titipan</h2>
            <div v-if="items.length" class="space-y-3">
                <div v-for="item in items" :key="item.code" class="card flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
                    <div class="h-20 w-28 shrink-0 overflow-hidden rounded-xl"><LotImage :src="item.image" class="text-3xl" /></div>
                    <div class="min-w-0 flex-1">
                        <p class="font-mono text-xs text-stone-500">{{ item.code }}</p>
                        <p class="font-semibold text-ink">{{ item.title }}</p>
                        <p class="text-sm text-stone-500">Harga limit Anda: {{ money(item.reserve_price) }}</p>
                    </div>
                    <div class="text-sm sm:text-right">
                        <StatusBadge :status="item.status" />
                        <template v-if="item.lot">
                            <p class="mt-1 text-stone-600">{{ item.lot.auction }}</p>
                            <p class="font-semibold text-ink">{{ money(item.lot.current_price) }} <span class="font-normal text-stone-500">· {{ item.lot.bids_count }} bid</span></p>
                            <Link v-if="item.lot.public" :href="route('lots.show', item.lot.id)" class="link text-xs">Lihat halaman lelang</Link>
                        </template>
                    </div>
                </div>
            </div>
            <EmptyState v-else title="Belum ada barang" icon="📦" />

            <h2 class="mt-10 mb-4 text-lg font-bold text-ink">Settlement (hasil penjualan)</h2>
            <div v-if="settlements.length" class="card overflow-x-auto">
                <table class="tbl">
                    <thead><tr><th>No.</th><th>Barang</th><th>Harga palu</th><th>Komisi</th><th>Untuk Anda</th><th>Status</th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        <tr v-for="s in settlements" :key="s.number">
                            <td class="font-mono text-xs">{{ s.number }}</td>
                            <td>{{ s.title }}</td>
                            <td>{{ money(s.hammer_price) }}</td>
                            <td class="text-xs">{{ money(s.commission) }} ({{ s.commission_rate }}%)</td>
                            <td class="font-bold">{{ money(s.net_amount) }}</td>
                            <td><StatusBadge :status="s.status" /><span v-if="s.paid_at" class="block text-xs text-stone-500">{{ dateTime(s.paid_at) }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState v-else title="Belum ada settlement" description="Settlement dibuat setelah pemenang melunasi pembayaran." icon="💸" />
        </div>
    </PublicLayout>
</template>
