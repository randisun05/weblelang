<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import LotCard from '@/Components/LotCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Countdown from '@/Components/Countdown.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { dateTime, money } from '@/lib/format';

const props = defineProps({ auction: Object, registration: Object, deposit: Object, onlinePayment: Boolean, hasBank: Boolean, lots: Object, categories: Array, filters: Object });

const filter = reactive({ q: props.filters.q ?? '', category: props.filters.category ?? '', sort: props.filters.sort ?? 'lot' });
let timer;
watch(filter, () => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('auctions.show', props.auction.slug), filter, { preserveState: true, preserveScroll: true, replace: true }), 350);
});

const reg = useForm({ proof: null });
const register = () => reg.post(route('auctions.register', props.auction.slug), { forceFormData: true, onSuccess: () => reg.reset() });
const payingDeposit = ref(false);
const payDeposit = () => {
    payingDeposit.value = true;
    router.post(route('auctions.deposit.pay', props.auction.slug), {}, { onFinish: () => (payingDeposit.value = false) });
};
</script>

<template>
    <Head :title="auction.title" />
    <PublicLayout>
        <section class="border-b border-stone-200 bg-white">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 py-8 md:grid-cols-[1fr_auto] md:items-center">
                <div>
                    <div class="flex items-center gap-2">
                        <StatusBadge :status="auction.status" /><StatusBadge :status="auction.method" />
                        <span class="text-xs text-stone-400">{{ auction.code }}</span>
                    </div>
                    <h1 class="mt-2 font-display text-3xl font-extrabold text-ink">{{ auction.title }}</h1>
                    <p class="mt-2 max-w-3xl whitespace-pre-line text-stone-600">{{ auction.description }}</p>
                    <div class="mt-4 flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full bg-stone-100 px-3 py-1">🗓 {{ dateTime(auction.starts_at) }} – {{ dateTime(auction.ends_at) }}</span>
                        <span class="rounded-full bg-stone-100 px-3 py-1">💰 Premi pembeli {{ auction.buyer_premium_rate }}%</span>
                        <span class="rounded-full bg-stone-100 px-3 py-1" :title="auction.method_description">ℹ️ {{ auction.method_description }}</span>
                        <span v-if="auction.anti_snipe_minutes && auction.method.value === 'open'" class="rounded-full bg-stone-100 px-3 py-1">⏱ Bid di {{ auction.anti_snipe_minutes }} menit terakhir memperpanjang {{ auction.extend_minutes }} menit</span>
                        <span v-if="auction.deposit_amount" class="rounded-full bg-amber-100 px-3 py-1 text-amber-900">🔒 Jaminan {{ money(auction.deposit_amount) }}</span>
                    </div>
                </div>
                <div class="space-y-3">
                    <Countdown v-if="auction.status.value === 'live'" :to="auction.ends_at" label="Sesi berakhir dalam" />
                    <Countdown v-else-if="auction.status.value === 'published'" :to="auction.starts_at" label="Sesi dimulai dalam" />
                </div>
            </div>

            <!-- Siaran lelang live -->
            <div v-if="auction.method.value === 'live' && (auction.stream_embed || auction.stream_url || auction.live_lot_id)" class="mx-auto max-w-7xl px-4 pb-6">
                <div class="grid gap-4 md:grid-cols-[2fr_1fr]">
                    <iframe v-if="auction.stream_embed" :src="auction.stream_embed" class="aspect-video w-full rounded-2xl" allow="autoplay; encrypted-media" allowfullscreen title="Siaran langsung" />
                    <a v-else-if="auction.stream_url" :href="auction.stream_url" target="_blank" rel="noopener" class="card flex items-center justify-center p-6 font-semibold text-red-600">🔴 Tonton siaran juru lelang ↗</a>
                    <div class="card flex flex-col justify-center p-6 text-center">
                        <template v-if="auction.live_lot_id">
                            <p class="text-sm font-bold text-red-600"><span class="animate-live">●</span> Lot sedang dilelang</p>
                            <Link :href="route('lots.show', auction.live_lot_id)" class="btn-primary mt-3">Ikut menawar sekarang →</Link>
                        </template>
                        <p v-else class="text-sm text-stone-500">Juru lelang belum membuka lot. Tetap di halaman ini.</p>
                    </div>
                </div>
            </div>

            <!-- Pendaftaran jaminan -->
            <div v-if="auction.deposit_amount && (auction.status.value !== 'closed' || registration)" class="mx-auto max-w-7xl px-4 pb-6">
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm">
                    <template v-if="!$page.props.auth.user">Sesi ini mensyaratkan uang jaminan. <a :href="route('login')" class="link">Masuk</a> untuk mendaftar.</template>
                    <template v-else-if="registration && registration.value === 'approved'">
                        Status pendaftaran Anda: <StatusBadge :status="registration" />
                        <StatusBadge v-if="deposit" :status="deposit" class="ml-1" />
                        <p v-if="deposit?.value === 'held' && !hasBank" class="mt-2 text-amber-900">
                            Isi <Link :href="route('user.profile')" class="link">rekening pengembalian jaminan</Link> agar jaminan dapat dikembalikan otomatis setelah sesi selesai.
                        </p>
                    </template>
                    <div v-else class="space-y-3">
                        <p v-if="registration?.value === 'pending'">Status pendaftaran Anda: <StatusBadge :status="registration" /> — bukti transfer sedang diperiksa, atau bayar online agar langsung aktif.</p>
                        <div v-if="onlinePayment" class="flex flex-wrap items-center gap-3">
                            <button class="btn-primary btn-sm" :disabled="payingDeposit" @click="payDeposit">💳 Bayar jaminan {{ money(auction.deposit_amount) }} online</button>
                            <span class="text-xs text-amber-900">Pendaftaran langsung disetujui otomatis setelah pembayaran berhasil.</span>
                        </div>
                    <form class="flex flex-wrap items-center gap-3" @submit.prevent="register">
                        <span>{{ onlinePayment ? 'Atau transfer manual' : 'Transfer jaminan' }} <b>{{ money(auction.deposit_amount) }}</b> lalu unggah bukti transfer:</span>
                        <input type="file" accept="image/*" class="text-sm" @input="reg.proof = $event.target.files[0]" />
                        <button class="btn-primary btn-sm" :disabled="!reg.proof || reg.processing">Kirim bukti</button>
                        <span v-if="reg.errors.proof" class="text-red-600">{{ reg.errors.proof }}</span>
                        <span v-if="registration?.value === 'rejected'" class="text-red-600">Pendaftaran sebelumnya ditolak, silakan kirim ulang.</span>
                    </form>
                    </div>
                </div>
            </div>
        </section>

        <div class="mx-auto max-w-7xl px-4 py-8">
            <div class="mb-6 flex flex-wrap gap-3">
                <input v-model="filter.q" class="input max-w-xs" placeholder="Cari barang…" />
                <select v-model="filter.category" class="input max-w-52">
                    <option value="">Semua kategori</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <select v-model="filter.sort" class="input max-w-52">
                    <option value="lot">Nomor lot</option>
                    <option value="ending">Segera berakhir</option>
                    <option value="price_low">Harga terendah</option>
                    <option value="price_high">Harga tertinggi</option>
                </select>
            </div>
            <div v-if="lots.data.length" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <LotCard v-for="lot in lots.data" :key="lot.id" :lot="lot" />
            </div>
            <EmptyState v-else title="Tidak ada lot yang cocok" icon="🔍" />
            <Pagination :links="lots.links" />
        </div>
    </PublicLayout>
</template>
