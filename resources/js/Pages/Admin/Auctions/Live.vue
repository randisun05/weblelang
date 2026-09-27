<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import LotImage from '@/Components/LotImage.vue';
import { dateTime, money } from '@/lib/format';
import { connected, onLotUpdated } from '@/lib/realtime';

const props = defineProps({ auction: Object, lots: Array, current: Object });
const busy = ref(false);

// Penawaran online masuk terus: segarkan konsol saat ada event websocket, dan polling
// tiap 2 detik sebagai cadangan (jauh lebih jarang bila websocket tersambung).
let timer;
let stopRealtime;
let ticks = 0;
const reload = () => !busy.value && router.reload({ only: ['current', 'lots'] });
onMounted(() => {
    stopRealtime = onLotUpdated(`auctions.${props.auction.id}`, reload);
    timer = setInterval(() => {
        ticks++;
        if (!connected.value || ticks % 8 === 0) reload();
    }, 2000);
});
onBeforeUnmount(() => {
    clearInterval(timer);
    stopRealtime?.();
});

const act = (name, lot) => {
    busy.value = true;
    router.post(route(`admin.auctions.live.${name}`, [props.auction.id, lot.id]), {}, { preserveScroll: true, onFinish: () => (busy.value = false) });
};
const callText = ['Belum ada panggilan', 'PANGGILAN PERTAMA', 'PANGGILAN KEDUA'];
</script>

<template>
    <Head title="Konsol Juru Lelang" />
    <AdminLayout :title="`🎙️ Konsol Juru Lelang — ${auction.title}`">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <StatusBadge :status="auction.status" />
            <Link :href="route('admin.auctions.show', auction.id)" class="link text-sm">← Kelola sesi</Link>
            <a :href="route('auctions.show', auction.slug)" target="_blank" class="link text-sm">Halaman peserta ↗</a>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
            <section class="card p-6">
                <template v-if="current">
                    <div class="flex flex-wrap gap-6">
                        <div class="h-40 w-56 overflow-hidden rounded-2xl"><LotImage :src="current.image" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-red-600"><span class="animate-live">●</span> LOT {{ current.lot_number }} SEDANG BERJALAN</p>
                            <h2 class="text-2xl font-bold text-ink">{{ current.title }}</h2>
                            <p class="mt-3 text-sm text-stone-500">{{ current.bids_count ? 'Penawaran tertinggi' : 'Harga awal' }}</p>
                            <p class="text-5xl font-extrabold text-ink tabular-nums">{{ money(current.current_price) }}</p>
                            <p class="mt-1 text-sm text-stone-600">
                                {{ current.leader ? `oleh ${current.leader}` : 'Belum ada penawar' }} · berikutnya min. <b>{{ money(current.next_bid) }}</b>
                            </p>
                            <p class="mt-1 text-xs" :class="current.reserve_met ? 'text-green-700' : 'text-amber-700'">
                                Harga limit {{ money(current.reserve_price) }} — {{ current.reserve_met ? 'tercapai ✓' : 'belum tercapai' }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 rounded-2xl p-4 text-center text-xl font-extrabold tracking-wide"
                        :class="current.live_calls === 2 ? 'bg-red-600 text-white' : current.live_calls === 1 ? 'bg-amber-400 text-ink' : 'bg-stone-100 text-stone-500'">
                        {{ callText[current.live_calls] }}
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <button class="btn-primary py-4 text-base" :disabled="busy || current.live_calls >= 2" @click="act('call', current)">
                            📣 {{ current.live_calls === 0 ? 'Panggilan pertama' : 'Panggilan kedua' }}
                        </button>
                        <button class="btn py-4 text-base text-white" :class="current.live_calls >= 2 ? 'bg-red-600 hover:bg-red-500' : 'bg-stone-300'"
                            :disabled="busy || current.live_calls < 2" @click="act('hammer', current)">
                            🔨 Ketuk palu — {{ current.bids_count && current.reserve_met ? 'TERJUAL' : 'tutup lot' }}
                        </button>
                    </div>
                    <p class="mt-2 text-xs text-stone-500">Penawaran baru otomatis membatalkan panggilan. Palu hanya bisa diketuk setelah panggilan kedua.</p>

                    <h3 class="mt-6 font-semibold text-ink">Penawaran masuk</h3>
                    <ul class="mt-2 divide-y divide-stone-100 text-sm">
                        <li v-for="(b, i) in current.bids" :key="b.id" class="flex justify-between py-2" :class="i === 0 ? 'font-semibold text-ink' : 'text-stone-600'">
                            <span>{{ b.bidder }} <span v-if="b.is_auto" class="text-xs text-stone-400">(auto)</span></span>
                            <span>{{ money(b.amount) }} <span class="ml-2 text-xs text-stone-400">{{ dateTime(b.at) }}</span></span>
                        </li>
                        <li v-if="!current.bids.length" class="py-2 text-stone-500">Belum ada penawaran.</li>
                    </ul>
                </template>
                <div v-else class="py-16 text-center">
                    <p class="text-4xl">🎙️</p>
                    <p class="mt-3 font-semibold text-ink">Tidak ada lot yang sedang berjalan</p>
                    <p class="text-sm text-stone-500">Pilih lot berikutnya dari daftar untuk dibuka.</p>
                </div>
            </section>

            <aside class="space-y-4">
                <section v-if="auction.stream_embed" class="card overflow-hidden">
                    <iframe :src="auction.stream_embed" class="aspect-video w-full" allow="autoplay; encrypted-media" allowfullscreen title="Siaran langsung" />
                </section>
                <section class="card p-4">
                    <h3 class="mb-2 font-semibold text-ink">Urutan lot</h3>
                    <ul class="divide-y divide-stone-100 text-sm">
                        <li v-for="lot in lots" :key="lot.id" class="flex items-center justify-between gap-2 py-2">
                            <span class="min-w-0">
                                <b>{{ lot.lot_number }}.</b> <span class="truncate">{{ lot.title }}</span>
                                <span v-if="['sold', 'unsold'].includes(lot.status.value)" class="block text-xs text-stone-500">{{ money(lot.current_price || lot.starting_price) }} {{ lot.leader ? `· ${lot.leader}` : '' }}</span>
                            </span>
                            <button v-if="lot.status.value === 'scheduled'" class="btn-outline btn-sm shrink-0" :disabled="busy || !!current" @click="act('open', lot)">Buka</button>
                            <StatusBadge v-else :status="lot.status" />
                        </li>
                    </ul>
                </section>
            </aside>
        </div>
    </AdminLayout>
</template>
