<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import LotCard from '@/Components/LotCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Countdown from '@/Components/Countdown.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { dateTime, money } from '@/lib/format';

const props = defineProps({ auction: Object, registration: Object, deposit: Object, lots: Object, categories: Array, filters: Object });

const filter = reactive({ q: props.filters.q ?? '', category: props.filters.category ?? '', sort: props.filters.sort ?? 'lot' });
let timer;
watch(filter, () => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('auctions.show', props.auction.slug), filter, { preserveState: true, preserveScroll: true, replace: true }), 350);
});

const reg = useForm({ proof: null });
const register = () => reg.post(route('auctions.register', props.auction.slug), { forceFormData: true, onSuccess: () => reg.reset() });
</script>

<template>
    <Head :title="auction.title" />
    <PublicLayout>
        <section class="border-b border-stone-200 bg-white">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 py-8 md:grid-cols-[1fr_auto] md:items-center">
                <div>
                    <div class="flex items-center gap-2"><StatusBadge :status="auction.status" /><span class="text-xs text-stone-400">{{ auction.code }}</span></div>
                    <h1 class="mt-2 font-display text-3xl font-extrabold text-ink">{{ auction.title }}</h1>
                    <p class="mt-2 max-w-3xl whitespace-pre-line text-stone-600">{{ auction.description }}</p>
                    <div class="mt-4 flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full bg-stone-100 px-3 py-1">🗓 {{ dateTime(auction.starts_at) }} – {{ dateTime(auction.ends_at) }}</span>
                        <span class="rounded-full bg-stone-100 px-3 py-1">💰 Premi pembeli {{ auction.buyer_premium_rate }}%</span>
                        <span v-if="auction.anti_snipe_minutes" class="rounded-full bg-stone-100 px-3 py-1">⏱ Bid di {{ auction.anti_snipe_minutes }} menit terakhir memperpanjang {{ auction.extend_minutes }} menit</span>
                        <span v-if="auction.deposit_amount" class="rounded-full bg-amber-100 px-3 py-1 text-amber-900">🔒 Jaminan {{ money(auction.deposit_amount) }}</span>
                    </div>
                </div>
                <div class="space-y-3">
                    <Countdown v-if="auction.status.value === 'live'" :to="auction.ends_at" label="Sesi berakhir dalam" />
                    <Countdown v-else-if="auction.status.value === 'published'" :to="auction.starts_at" label="Sesi dimulai dalam" />
                </div>
            </div>

            <!-- Pendaftaran jaminan -->
            <div v-if="auction.deposit_amount && (auction.status.value !== 'closed' || registration)" class="mx-auto max-w-7xl px-4 pb-6">
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm">
                    <template v-if="!$page.props.auth.user">Sesi ini mensyaratkan uang jaminan. <a :href="route('login')" class="link">Masuk</a> untuk mendaftar.</template>
                    <template v-else-if="registration && registration.value !== 'rejected'">
                        Status pendaftaran Anda: <StatusBadge :status="registration" />
                        <StatusBadge v-if="deposit" :status="deposit" class="ml-1" />
                    </template>
                    <form v-else class="flex flex-wrap items-center gap-3" @submit.prevent="register">
                        <span>Transfer jaminan <b>{{ money(auction.deposit_amount) }}</b> lalu unggah bukti transfer:</span>
                        <input type="file" accept="image/*" class="text-sm" @input="reg.proof = $event.target.files[0]" />
                        <button class="btn-primary btn-sm" :disabled="!reg.proof || reg.processing">Kirim bukti</button>
                        <span v-if="reg.errors.proof" class="text-red-600">{{ reg.errors.proof }}</span>
                        <span v-if="registration?.value === 'rejected'" class="text-red-600">Pendaftaran sebelumnya ditolak, silakan kirim ulang.</span>
                    </form>
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
