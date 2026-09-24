<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Countdown from '@/Components/Countdown.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import LotImage from '@/Components/LotImage.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import { syncServerTime } from '@/lib/clock';
import { dateTime, money } from '@/lib/format';

const props = defineProps({ lot: Object, state: Object, viewer: Object, pollSeconds: Number });

const live = ref({ ...props.state });
const activeImage = ref(0);
const amount = ref(props.state.minimum_bid);
const useAuto = ref(false);
const maxAmount = ref(null);
const submitting = ref(false);
const extendedFlash = ref(false);

syncServerTime(props.state.server_time);

// ---- Polling state (fallback bila websocket tidak aktif) ----
let poller;
const refresh = async () => {
    try {
        const { data } = await axios.get(route('lots.state', props.lot.id));
        if (data.extended_count > live.value.extended_count) {
            extendedFlash.value = true;
            setTimeout(() => (extendedFlash.value = false), 6000);
        }
        live.value = data;
        syncServerTime(data.server_time);
    } catch {
        /* abaikan gangguan jaringan sesaat */
    }
};
onMounted(() => {
    if (['live', 'scheduled'].includes(live.value.status.value)) poller = setInterval(refresh, (props.pollSeconds || 4) * 1000);
});
onBeforeUnmount(() => clearInterval(poller));

watch(() => props.state, (s) => (live.value = { ...s }));
watch(() => live.value.minimum_bid, (min) => {
    if (!amount.value || amount.value < min) amount.value = min;
});

const isLive = computed(() => live.value.status.value === 'live');
const premium = computed(() => Math.round((amount.value || 0) * props.lot.auction.buyer_premium_rate / 100));

const blocker = computed(() => {
    if (!isLive.value) return null;
    if (!props.viewer) return { text: 'Masuk atau daftar untuk mulai menawar.', href: route('login'), cta: 'Masuk' };
    if (props.viewer.is_backoffice) return { text: 'Akun petugas tidak dapat menawar.' };
    if (!props.viewer.kyc_verified) return { text: 'Verifikasi identitas (KTP) diperlukan sebelum menawar.', href: route('user.profile'), cta: 'Verifikasi sekarang' };
    if (!['approved', 'not_required'].includes(props.viewer.registration)) {
        return { text: 'Sesi ini mensyaratkan uang jaminan. Daftar di halaman sesi lelang.', href: route('auctions.show', props.lot.auction.slug), cta: 'Daftar sesi' };
    }
    return null;
});

const quick = (steps) => (amount.value = live.value.minimum_bid + live.value.increment * (steps - 1));

const placeBid = async () => {
    if (amount.value < live.value.minimum_bid) {
        Swal.fire({ icon: 'warning', title: 'Nominal terlalu kecil', text: `Penawaran minimum ${money(live.value.minimum_bid)}.` });
        return;
    }
    if (useAuto.value && (!maxAmount.value || maxAmount.value < amount.value)) {
        Swal.fire({ icon: 'warning', title: 'Batas auto-bid tidak valid', text: 'Batas maksimum harus ≥ nominal penawaran.' });
        return;
    }

    const { isConfirmed } = await Swal.fire({
        icon: 'question',
        title: `Tawar ${money(amount.value)}?`,
        html: `<div style="text-align:left;font-size:14px">
            Jika menang, total yang dibayar:<br>
            <b>${money(amount.value)}</b> + premi ${props.lot.auction.buyer_premium_rate}% (${money(premium.value)})
            = <b>${money(amount.value + premium.value)}</b>
            ${useAuto.value ? `<br><br>Auto-bid aktif hingga <b>${money(maxAmount.value)}</b>.` : ''}
            <br><br><small>Penawaran bersifat mengikat dan tidak dapat dibatalkan.</small></div>`,
        showCancelButton: true,
        confirmButtonText: 'Ya, tawar',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#f59e0b',
    });
    if (!isConfirmed) return;

    submitting.value = true;
    router.post(
        route('lots.bid', props.lot.id),
        { amount: amount.value, max_amount: useAuto.value ? maxAmount.value : null },
        { preserveScroll: true, onFinish: () => { submitting.value = false; refresh(); } },
    );
};

const toggleWatch = () => router.post(route('lots.watch', props.lot.id), {}, { preserveScroll: true });
</script>

<template>
    <Head :title="lot.item.title" />
    <PublicLayout>
        <div class="mx-auto max-w-7xl px-4 py-6">
            <nav class="mb-4 text-sm text-stone-500">
                <Link :href="route('auctions.index')" class="hover:text-ink">Lelang</Link> /
                <Link :href="route('auctions.show', lot.auction.slug)" class="hover:text-ink">{{ lot.auction.title }}</Link> /
                <span class="text-stone-700">Lot {{ lot.lot_number }}</span>
            </nav>

            <div class="grid gap-8 lg:grid-cols-[1fr_420px]">
                <!-- Kiri: galeri & detail -->
                <div class="contents lg:block lg:space-y-6">
                    <div class="card order-1 overflow-hidden lg:order-none">
                        <div class="aspect-[4/3] bg-stone-100">
                            <LotImage :src="lot.item.images[activeImage]" :alt="lot.item.title" />
                        </div>
                        <div v-if="lot.item.images.length > 1" class="flex gap-2 overflow-x-auto p-3">
                            <button v-for="(img, i) in lot.item.images" :key="i" class="h-16 w-20 shrink-0 overflow-hidden rounded-lg ring-2"
                                :class="i === activeImage ? 'ring-brand-500' : 'ring-transparent opacity-70 hover:opacity-100'" @click="activeImage = i">
                                <img :src="img" class="h-full w-full object-cover" alt="" />
                            </button>
                        </div>
                    </div>

                    <div class="card order-3 p-6 lg:order-none">
                        <h2 class="text-lg font-semibold text-ink">Deskripsi</h2>
                        <p class="mt-2 whitespace-pre-line text-stone-600">{{ lot.item.description || 'Tidak ada deskripsi.' }}</p>

                        <h3 class="mt-6 font-semibold text-ink">Spesifikasi</h3>
                        <dl class="mt-2 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                            <div class="flex justify-between border-b border-stone-100 py-1.5"><dt class="text-stone-500">Kode barang</dt><dd class="font-medium">{{ lot.item.code }}</dd></div>
                            <div class="flex justify-between border-b border-stone-100 py-1.5"><dt class="text-stone-500">Kategori</dt><dd class="font-medium">{{ lot.item.category }}</dd></div>
                            <div class="flex justify-between border-b border-stone-100 py-1.5"><dt class="text-stone-500">Kondisi</dt><dd class="font-medium">{{ lot.item.condition }}</dd></div>
                            <div v-for="a in lot.item.attributes" :key="a.label" class="flex justify-between border-b border-stone-100 py-1.5">
                                <dt class="text-stone-500">{{ a.label }}</dt><dd class="font-medium">{{ a.value }}</dd>
                            </div>
                        </dl>
                        <p class="mt-6 rounded-xl bg-stone-50 p-4 text-xs text-stone-500">
                            Barang dijual dalam kondisi apa adanya sesuai hasil pemeriksaan kami. Peserta dipersilakan melihat barang langsung
                            di lokasi sebelum menawar dengan membuat janji terlebih dahulu.
                        </p>
                    </div>
                </div>

                <!-- Kanan: panel bid -->
                <aside class="order-2 space-y-4 lg:sticky lg:top-20 lg:self-start">
                    <div class="card p-6">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-xs font-semibold text-brand-700">LOT {{ lot.lot_number }} · {{ lot.item.category }}</p>
                                <h1 class="mt-1 text-xl font-bold text-ink">{{ lot.item.title }}</h1>
                            </div>
                            <button v-if="viewer" class="rounded-full p-2 text-xl hover:bg-stone-100" :title="viewer.watching ? 'Hapus dari pantauan' : 'Pantau lot ini'" @click="toggleWatch">
                                {{ viewer.watching ? '❤️' : '🤍' }}
                            </button>
                        </div>

                        <div class="mt-4 flex items-center gap-2">
                            <StatusBadge :status="live.status" />
                            <span v-if="live.has_reserve" class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="live.reserve_met ? 'bg-green-50 text-green-700' : 'bg-stone-100 text-stone-600'">
                                {{ live.reserve_met ? '✓ Harga limit tercapai' : 'Harga limit belum tercapai' }}
                            </span>
                        </div>

                        <div class="mt-5 rounded-2xl bg-stone-50 p-4">
                            <p class="text-xs text-stone-500">{{ live.bids_count ? 'Penawaran tertinggi' : 'Harga awal' }}</p>
                            <p class="text-3xl font-extrabold text-ink tabular-nums">{{ money(live.current_price) }}</p>
                            <p class="mt-1 text-xs text-stone-500">{{ live.bids_count }} penawaran
                                <template v-if="lot.item.estimate_low"> · Estimasi {{ money(lot.item.estimate_low) }}–{{ money(lot.item.estimate_high) }}</template>
                            </p>
                            <p v-if="live.is_leader" class="mt-2 rounded-lg bg-green-100 px-3 py-1.5 text-sm font-semibold text-green-800">🏆 Anda penawar tertinggi</p>
                        </div>

                        <div class="mt-5">
                            <Countdown v-if="live.status.value === 'live'" :to="live.ends_at" label="Berakhir dalam" @finished="refresh" />
                            <Countdown v-else-if="live.status.value === 'scheduled'" :to="live.starts_at" label="Dimulai dalam" @finished="refresh" />
                            <p class="mt-2 text-xs text-stone-500">Tutup: {{ dateTime(live.ends_at) }}</p>
                            <p v-if="extendedFlash" class="mt-2 animate-pulse rounded-lg bg-red-50 px-3 py-2 text-sm font-semibold text-red-700">
                                ⏱ Waktu diperpanjang karena ada penawaran di menit terakhir!
                            </p>
                        </div>

                        <!-- Form bid -->
                        <div v-if="isLive" class="mt-6 border-t border-stone-100 pt-5">
                            <div v-if="blocker" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">
                                {{ blocker.text }}
                                <Link v-if="blocker.href" :href="blocker.href" class="btn-primary btn-sm mt-3 w-full">{{ blocker.cta }}</Link>
                            </div>
                            <form v-else class="space-y-3" @submit.prevent="placeBid">
                                <p class="text-sm text-stone-600">Minimum <b>{{ money(live.minimum_bid) }}</b> · kelipatan {{ money(live.increment) }}</p>
                                <div class="grid grid-cols-3 gap-2">
                                    <button v-for="s in [1, 2, 5]" :key="s" type="button" class="btn-outline btn-sm" @click="quick(s)">
                                        {{ s === 1 ? 'Minimum' : `+${s - 1}× kelipatan` }}
                                    </button>
                                </div>
                                <MoneyInput v-model="amount" />
                                <label class="flex items-center gap-2 text-sm text-stone-600">
                                    <input v-model="useAuto" type="checkbox" class="rounded" /> Aktifkan auto-bid (tawar otomatis)
                                </label>
                                <div v-if="useAuto">
                                    <MoneyInput v-model="maxAmount" placeholder="Batas maksimum" />
                                    <p class="mt-1 text-xs text-stone-500">Sistem akan menawar seminimal mungkin untuk Anda hingga batas ini. Batas tidak terlihat peserta lain.</p>
                                </div>
                                <p v-if="viewer?.auto_bid" class="text-xs text-green-700">Auto-bid Anda aktif hingga {{ money(viewer.auto_bid) }}.</p>
                                <button class="btn-primary w-full py-3 text-base" :disabled="submitting">🔨 Tawar {{ money(amount) }}</button>
                                <p class="text-center text-xs text-stone-500">+ premi pembeli {{ lot.auction.buyer_premium_rate }}% jika menang</p>
                            </form>
                        </div>
                    </div>

                    <!-- Riwayat -->
                    <div class="card p-6">
                        <h2 class="font-semibold text-ink">Riwayat penawaran</h2>
                        <ul v-if="live.bids.length" class="mt-3 divide-y divide-stone-100">
                            <li v-for="(bid, i) in live.bids" :key="bid.id" class="flex items-center justify-between py-2 text-sm">
                                <span :class="i === 0 ? 'font-semibold text-ink' : 'text-stone-600'">
                                    {{ i === 0 ? '🥇 ' : '' }}{{ bid.bidder }}
                                    <span v-if="bid.is_auto" class="ml-1 rounded bg-stone-100 px-1.5 text-[10px] text-stone-500">auto</span>
                                </span>
                                <span class="text-right">
                                    <span class="block font-semibold tabular-nums" :class="i === 0 ? 'text-ink' : 'text-stone-500'">{{ money(bid.amount) }}</span>
                                    <span class="block text-[11px] text-stone-400">{{ dateTime(bid.at) }}</span>
                                </span>
                            </li>
                        </ul>
                        <p v-else class="mt-3 text-sm text-stone-500">Belum ada penawaran. Jadilah yang pertama!</p>
                    </div>
                </aside>
            </div>
        </div>
    </PublicLayout>
</template>
