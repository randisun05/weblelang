<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AccountLayout from '@/Layouts/AccountLayout.vue';
import LotCard from '@/Components/LotCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

defineProps({ active: Array, won: Array, watchlist: Array, unpaidInvoices: Number, kyc: Object });
</script>

<template>
    <Head title="Akun Saya" />
    <AccountLayout :title="`Halo, ${$page.props.auth.user.name}`">
        <div class="grid gap-4 md:grid-cols-2">
            <div v-if="kyc.value !== 'verified'" class="card flex items-center justify-between gap-4 border-amber-300 bg-amber-50 p-5">
                <div>
                    <p class="font-semibold text-amber-900">Verifikasi identitas</p>
                    <p class="text-sm text-amber-800">Status: <StatusBadge :status="kyc" />. Anda perlu terverifikasi untuk menawar.</p>
                </div>
                <Link :href="route('user.profile')" class="btn-primary btn-sm shrink-0">Lengkapi</Link>
            </div>
            <div v-if="unpaidInvoices" class="card flex items-center justify-between gap-4 border-red-200 bg-red-50 p-5">
                <div>
                    <p class="font-semibold text-red-900">{{ unpaidInvoices }} invoice menunggu pembayaran</p>
                    <p class="text-sm text-red-800">Segera bayar sebelum jatuh tempo agar tidak dibatalkan.</p>
                </div>
                <Link :href="route('user.invoices.index')" class="btn-danger btn-sm shrink-0">Bayar</Link>
            </div>
        </div>

        <section class="mt-8">
            <h2 class="mb-4 text-lg font-bold text-ink">Penawaran aktif</h2>
            <div v-if="active.length" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <LotCard v-for="lot in active" :key="lot.id" :lot="lot" />
            </div>
            <EmptyState v-else title="Belum ada penawaran aktif" icon="🔨">
                <Link :href="route('auctions.index')" class="btn-primary btn-sm">Jelajahi lelang</Link>
            </EmptyState>
        </section>

        <section v-if="won.length" class="mt-10">
            <h2 class="mb-4 text-lg font-bold text-ink">🏆 Lot yang Anda menangkan</h2>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <LotCard v-for="lot in won" :key="lot.id" :lot="lot" />
            </div>
        </section>

        <section class="mt-10">
            <h2 class="mb-4 text-lg font-bold text-ink">❤️ Daftar pantauan</h2>
            <div v-if="watchlist.length" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <LotCard v-for="lot in watchlist" :key="lot.id" :lot="lot" />
            </div>
            <EmptyState v-else title="Belum ada lot yang dipantau" description="Klik ikon hati pada halaman lot untuk memantau." icon="🤍" />
        </section>
    </AccountLayout>
</template>
