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
import { connected, onLotUpdated } from '@/lib/realtime';
import { dateTime, money } from '@/lib/format';

const props = defineProps({ lot: Object, state: Object, viewer: Object, pollSeconds: Number });

const live = ref({ ...props.state });
const activeImage = ref(0);
const amount = ref(props.state.my_bid ?? props.state.minimum_bid);
const useAuto = ref(false);
const maxAmount = ref(null);
const submitting = ref(false);
const extendedFlash = ref(false);

syncServerTime(props.state.server_time);

// ---- Pembaruan state: websocket (Reverb) bila aktif, polling sebagai cadangan ----
let poller;
let stopRealtime;
let inflight = false;
const refresh = async () => {
    if (inflight) return;
    inflight = true;
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
    } finally {
        inflight = false;
    }
};
let ticks = 0;
onMounted(() => {
    if (!['live', 'scheduled'].includes(live.value.status.value)) return;

    // Event websocket hanya sinyal "ada perubahan"; datanya tetap diambil dari server.
    stopRealtime = onLotUpdated(`lots.${props.lot.id}`, refresh);

    // Lelang live butuh pembaruan lebih cepat (panggilan juru lelang). Saat websocket
    // tersambung, polling tetap jalan tapi jauh lebih jarang (jaga-jaga event terlewat).
    const seconds = props.state.method.value === 'live' ? 2 : props.pollSeconds || 4;
    poller = setInterval(() => {
        ticks++;
        if (!connected.value || ticks % 8 === 0) refresh();
    }, seconds * 1000);
});
onBeforeUnmount(() => {
    clearInterval(poller);
    stopRealtime?.();
});

watch(() => props.state, (s) => (live.value = { ...s }));
watch(() => live.value.minimum_bid, (min) => {
    if (!isSealed.value && (!amount.value || amount.value < min)) amount.value = min;
});

const isLive = computed(() => live.value.status.value === 'live');
const isSealed = computed(() => live.value.method.value === 'sealed');
const isAuctioneer = computed(() => live.value.method.value === 'live');
const callText = ['', '📣 Panggilan PERTAMA…', '📣 Panggilan KEDUA… segera terjual!'];
const premium = computed(() => Math.round((amount.value || 0) * props.lot.auction.buyer_premium_rate / 100));

const blocker = computed(() => {
    if (!isLive.value) return null;
    if (!props.viewer) return { text: 'Masuk atau daftar untuk mulai menawar.', href: route('login'), cta: 'Masuk' };
    if (props.viewer.is_backoffice) return { text: 'Akun petugas tidak dapat menawar.' };
    if (!props.viewer.email_verified) return { text: 'Verifikasi alamat email Anda terlebih dahulu (cek kotak masuk).', href: route('verification.notice'), cta: 'Kirim ulang email verifikasi' };
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
    if (!isSealed.value && useAuto.value && (!maxAmount.value || maxAmount.value < amount.value)) {
        Swal.fire({ icon: 'warning', title: 'Batas auto-bid tidak valid', text: 'Batas maksimum harus ≥ nominal penawaran.' });
        return;
    }

    const { isConfirmed } = await Swal.fire({
        icon: 'question',
        title: isSealed.value ? `Kirim penawaran tertutup ${money(amount.value)}?` : `Tawar ${money(amount.value)}?`,
        html: `<div style="text-align:left;font-size:14px">
            Jika menang, total yang dibayar:<br>
            <b>${money(amount.value)}</b> + premi ${props.lot.auction.buyer_premium_rate}% (${money(premium.value)})
            = <b>${money(amount.value + premium.value)}</b>
            ${!isSealed.value && useAuto.value ? `<br><br>Auto-bid aktif hingga <b>${money(maxAmount.value)}</b>.` : ''}
            ${isSealed.value ? '<br><br>Penawaran dirahasiakan. Anda dapat mengubahnya sebelum lot ditutup; yang dihitung adalah penawaran terakhir Anda.' : ''}
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
        { amount: amount.value, max_amount: !isSealed.value && useAuto.value ? maxAmount.value : null },
        { preserveScroll: true, onFinish: () => { submitting.value = false; refresh(); } },
    );
};

const buyNow = async () => {
    const price = live.value.buy_now_price;
    const fee = Math.round((price * props.lot.auction.buyer_premium_rate) / 100);
    const { isConfirmed } = await Swal.fire({
        icon: 'question',
        title: `Beli sekarang ${money(price)}?`,
        html: `<div style="text-align:left;font-size:14px">Lot langsung menjadi milik Anda tanpa menunggu lelang selesai.<br><br>
            Total bayar: <b>${money(price)}</b> + premi ${props.lot.auction.buyer_premium_rate}% (${money(fee)}) = <b>${money(price + fee)}</b>
            <br><br><small>Pembelian bersifat mengikat.</small></div>`,
        showCancelButton: true, confirmButtonText: 'Ya, beli sekarang', cancelButtonText: 'Batal', confirmButtonColor: '#059669',
    });
    if (isConfirmed) router.post(route('lots.buy-now', props.lot.id), {}, { preserveScroll: true });
};

const toggleWatch = () => router.post(route('lots.watch', props.lot.id), {}, { preserveScroll: true });
</script>

<template>
    <Head :title="`Lot ${lot.lot_number}: ${lot.item.title}`" />
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
                    <div v-if="isAuctioneer && lot.auction.stream_embed" class="card order-1 overflow-hidden lg:order-none">
                        <iframe :src="lot.auction.stream_embed" class="aspect-video w-full" allow="autoplay; encrypted-media" allowfullscreen title="Siaran langsung juru lelang" />
                    </div>
                    <a v-else-if="isAuctioneer && lot.auction.stream_url" :href="lot.auction.stream_url" target="_blank" rel="noopener"
                        class="card order-1 block p-4 text-center font-semibold text-red-600 hover:bg-red-50 lg:order-none">🔴 Tonton siaran juru lelang ↗</a>
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

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <StatusBadge :status="live.status" />
                            <StatusBadge v-if="live.method.value !== 'open'" :status="live.method" />
                            <span v-if="live.has_reserve && live.reserve_met !== null" class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="live.reserve_met ? 'bg-green-50 text-green-700' : 'bg-stone-100 text-stone-600'">
                                {{ live.reserve_met ? '✓ Harga limit tercapai' : 'Harga limit belum tercapai' }}
                            </span>
                        </div>

                        <div class="mt-5 rounded-2xl bg-stone-50 p-4">
                            <p class="text-xs text-stone-500">{{ live.bids_count && !live.concealed ? 'Penawaran tertinggi' : 'Harga awal' }}</p>
                            <p class="text-3xl font-extrabold text-ink tabular-nums">{{ money(live.current_price) }}</p>
                            <p class="mt-1 text-xs text-stone-500">{{ live.concealed ? '✉️ Penawaran tertutup — nominal & jumlah penawar dirahasiakan' : `${live.bids_count} penawaran` }}
                                <template v-if="lot.item.estimate_low"> · Estimasi {{ money(lot.item.estimate_low) }}–{{ money(lot.item.estimate_high) }}</template>
                            </p>
                            <p v-if="live.is_leader" class="mt-2 rounded-lg bg-green-100 px-3 py-1.5 text-sm font-semibold text-green-800">🏆 Anda penawar tertinggi</p>
                            <p v-if="live.my_bid" class="mt-2 rounded-lg bg-purple-50 px-3 py-1.5 text-sm font-semibold text-purple-800">✉️ Penawaran Anda: {{ money(live.my_bid) }}</p>
                            <p v-if="live.sold_via === 'buy_now'" class="mt-2 rounded-lg bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-800">⚡ Terjual lewat Beli Langsung</p>
                        </div>

                        <div v-if="isAuctioneer" class="mt-5">
                            <p v-if="live.status.value === 'live' && live.live_calls" class="animate-pulse rounded-xl px-4 py-3 text-center text-lg font-extrabold"
                                :class="live.live_calls === 2 ? 'bg-red-600 text-white' : 'bg-amber-300 text-ink'">{{ callText[live.live_calls] }}</p>
                            <p v-else-if="live.status.value === 'live'" class="rounded-xl bg-red-50 px-4 py-3 text-center text-sm font-semibold text-red-700">🎙️ Sedang dilelang oleh juru lelang</p>
                            <p v-else-if="live.status.value === 'scheduled'" class="rounded-xl bg-stone-100 px-4 py-3 text-center text-sm text-stone-600">Menunggu giliran dibuka juru lelang (sesi mulai {{ dateTime(live.starts_at) }})</p>
                        </div>
                        <div v-else class="mt-5">
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
                            <form v-else-if="isSealed" class="space-y-3" @submit.prevent="placeBid">
                                <p class="text-sm text-stone-600">Tawarkan satu harga terbaik Anda. Minimal <b>{{ money(live.minimum_bid) }}</b>, tanpa kelipatan.</p>
                                <MoneyInput v-model="amount" />
                                <button class="btn w-full bg-purple-700 py-3 text-base text-white hover:bg-purple-600" :disabled="submitting">
                                    ✉️ {{ live.my_bid ? 'Ubah penawaran' : 'Kirim penawaran tertutup' }}
                                </button>
                                <p class="text-center text-xs text-stone-500">Dirahasiakan dari semua pihak sampai lot ditutup · + premi {{ lot.auction.buyer_premium_rate }}% jika menang</p>
                            </form>
                            <form v-else class="space-y-3" @submit.prevent="placeBid">
                                <div v-if="live.buy_now_price" class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                                    <button type="button" class="btn w-full bg-emerald-600 text-white hover:bg-emerald-500" @click="buyNow">⚡ Beli Sekarang {{ money(live.buy_now_price) }}</button>
                                    <p class="mt-1 text-center text-xs text-emerald-800">Tersedia sampai ada penawaran pertama.</p>
                                </div>
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
                        <p v-else-if="live.concealed" class="mt-3 text-sm text-stone-500">🔒 Riwayat penawaran dirahasiakan dan baru dibuka setelah lot ditutup.</p>
                        <p v-else class="mt-3 text-sm text-stone-500">Belum ada penawaran. Jadilah yang pertama!</p>
                    </div>
                </aside>
            </div>
        </div>
    </PublicLayout>
</template>
