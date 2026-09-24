<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import Countdown from '@/Components/Countdown.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { dateTime, money, num } from '@/lib/format';

defineProps({ stats: Object, endingSoon: Array, recentBids: Array });
</script>

<template>
    <Head title="Dashboard Admin" />
    <AdminLayout title="Dashboard">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Lot live" :value="num(stats.live_lots)" :hint="`${num(stats.bids_today)} bid hari ini`" tone="red" />
            <StatCard label="Pendapatan bulan ini" :value="money(stats.revenue_month)" :hint="`Total nilai terjual ${money(stats.gmv_total)}`" tone="green" />
            <StatCard label="Invoice belum dibayar" :value="num(stats.invoices_unpaid)" :hint="money(stats.invoices_unpaid_total)"
                :href="route('admin.invoices.index', { status: 'unpaid' })" tone="amber" />
            <StatCard label="Settlement tertunda" :value="num(stats.settlements_pending)" :hint="money(stats.settlements_pending_total)"
                :href="route('admin.settlements.index', { status: 'pending' })" tone="amber" />
        </div>

        <h2 class="mt-8 mb-3 text-sm font-semibold tracking-wide text-stone-500 uppercase">Perlu tindakan</h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Barang menunggu inspeksi/persetujuan" :value="num(stats.items_waiting)" :href="route('admin.items.index', { status: 'received' })" />
            <StatCard label="Barang siap dilelang" :value="num(stats.items_ready)" :href="route('admin.items.index', { status: 'approved' })" />
            <StatCard label="KYC menunggu verifikasi" :value="num(stats.kyc_pending)" :href="route('admin.bidders.index', { kyc: 'pending' })" />
            <StatCard label="Pendaftaran jaminan menunggu" :value="num(stats.registrations_pending)" :href="route('admin.auctions.index')" />
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-2">
            <section class="card p-5">
                <h2 class="mb-4 font-semibold text-ink">⏱ Segera berakhir</h2>
                <ul v-if="endingSoon.length" class="divide-y divide-stone-100">
                    <li v-for="lot in endingSoon" :key="lot.id" class="flex items-center justify-between gap-3 py-3 text-sm">
                        <div class="min-w-0">
                            <Link :href="route('lots.show', lot.id)" class="block truncate font-medium text-ink hover:underline">{{ lot.title }}</Link>
                            <span class="text-stone-500">{{ money(lot.current_price) }} · {{ lot.bids_count }} bid</span>
                        </div>
                        <Countdown :to="lot.ends_at" compact />
                    </li>
                </ul>
                <EmptyState v-else title="Tidak ada lot live" icon="😴" />
            </section>
            <section class="card p-5">
                <h2 class="mb-4 font-semibold text-ink">🔨 Penawaran terbaru</h2>
                <ul v-if="recentBids.length" class="divide-y divide-stone-100">
                    <li v-for="b in recentBids" :key="b.id" class="flex items-center justify-between gap-3 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-ink">{{ b.title }}</p>
                            <p class="text-stone-500">{{ b.user }} <span v-if="b.is_auto" class="text-xs">(auto)</span> · {{ dateTime(b.at) }}</p>
                        </div>
                        <span class="font-semibold">{{ money(b.amount) }}</span>
                    </li>
                </ul>
                <EmptyState v-else title="Belum ada penawaran" icon="📭" />
            </section>
        </div>
    </AdminLayout>
</template>
