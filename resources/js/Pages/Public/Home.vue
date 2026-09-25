<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import LotCard from '@/Components/LotCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Countdown from '@/Components/Countdown.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { dateTime, num } from '@/lib/format';

defineProps({ live: Array, upcoming: Array, auctions: Array, categories: Array, stats: Object });

const steps = [
    { icon: '📝', title: 'Daftar & verifikasi', text: 'Buat akun gratis lalu unggah KTP. Verifikasi selesai maks. 1x24 jam.' },
    { icon: '🔎', title: 'Pilih barang', text: 'Semua barang sudah diperiksa tim kami — lengkap dengan foto dan kondisi.' },
    { icon: '🔨', title: 'Tawar atau auto-bid', text: 'Pasang batas maksimum dan sistem menawar otomatis untuk Anda.' },
    { icon: '🎉', title: 'Menang & bayar', text: 'Bayar online (VA, QRIS, e-wallet) atau transfer, lalu ambil/kirim barang.' },
];
</script>

<template>
    <Head title="Lelang Online Barang Titipan" />
    <PublicLayout>
        <!-- Hero -->
        <section class="relative overflow-hidden bg-ink text-white">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_80%_0%,rgba(245,158,11,.35),transparent_50%),radial-gradient(circle_at_0%_100%,rgba(245,158,11,.15),transparent_40%)]" />
            <div class="relative mx-auto grid max-w-7xl gap-10 px-4 py-16 md:grid-cols-2 md:py-24">
                <div>
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-brand-300">
                        <span class="animate-live h-2 w-2 rounded-full bg-red-500"></span> {{ stats.live }} lot sedang berlangsung
                    </span>
                    <h1 class="mt-5 font-display text-4xl leading-tight font-extrabold md:text-6xl">
                        Temukan barang istimewa,<br /><span class="text-brand-400">tawar sesuai harga Anda.</span>
                    </h1>
                    <p class="mt-5 max-w-lg text-lg text-stone-300">
                        Lelang online barang titipan yang sudah diperiksa, difoto, dan dijamin keasliannya. Transparan, aman, dan bisa diikuti dari HP.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link :href="route('auctions.index')" class="btn-primary px-6 py-3 text-base">Lihat jadwal lelang</Link>
                        <Link :href="route('consign.create')" class="btn border border-white/20 px-6 py-3 text-base text-white hover:bg-white/10">Titipkan barang Anda</Link>
                    </div>
                    <dl class="mt-10 grid max-w-md grid-cols-3 gap-6 border-t border-white/10 pt-6">
                        <div><dt class="text-xs text-stone-400">Terjual</dt><dd class="text-2xl font-bold">{{ num(stats.sold) }}</dd></div>
                        <div><dt class="text-xs text-stone-400">Live sekarang</dt><dd class="text-2xl font-bold">{{ num(stats.live) }}</dd></div>
                        <div><dt class="text-xs text-stone-400">Kategori</dt><dd class="text-2xl font-bold">{{ categories.length }}</dd></div>
                    </dl>
                </div>
                <div class="hidden gap-4 md:grid md:grid-cols-2">
                    <div v-for="(lot, i) in live.slice(0, 4)" :key="lot.id" :class="i % 2 ? 'translate-y-8' : ''">
                        <LotCard :lot="lot" />
                    </div>
                </div>
            </div>
        </section>

        <!-- Kategori -->
        <section class="mx-auto max-w-7xl px-4 pt-10">
            <div class="flex gap-3 overflow-x-auto pb-2">
                <span v-for="cat in categories" :key="cat.id" class="flex shrink-0 items-center gap-2 rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-medium shadow-sm">
                    <span>{{ cat.icon }}</span>{{ cat.name }}
                </span>
            </div>
        </section>

        <!-- Live -->
        <section class="mx-auto max-w-7xl px-4 pt-10">
            <div class="mb-5 flex items-end justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-ink">🔴 Sedang berlangsung</h2>
                    <p class="text-sm text-stone-500">Urut dari yang paling cepat berakhir</p>
                </div>
                <Link :href="route('auctions.index')" class="link text-sm">Semua lelang →</Link>
            </div>
            <div v-if="live.length" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <LotCard v-for="lot in live" :key="lot.id" :lot="lot" />
            </div>
            <EmptyState v-else title="Belum ada lot yang sedang berlangsung" description="Cek jadwal lelang berikutnya di bawah." icon="⏳" />
        </section>

        <!-- Sesi -->
        <section v-if="auctions.length" class="mx-auto max-w-7xl px-4 pt-14">
            <h2 class="mb-5 text-2xl font-bold text-ink">📅 Sesi lelang</h2>
            <div class="grid gap-5 md:grid-cols-3">
                <Link v-for="a in auctions" :key="a.slug" :href="route('auctions.show', a.slug)" class="card p-5 transition hover:shadow-md">
                    <div class="flex flex-wrap gap-2"><StatusBadge :status="a.status" /><StatusBadge :status="a.method" /></div>
                    <h3 class="mt-3 text-lg font-semibold text-ink">{{ a.title }}</h3>
                    <p class="mt-1 text-sm text-stone-500">{{ a.lots_count }} lot · {{ dateTime(a.starts_at) }}</p>
                    <div class="mt-4 text-sm">
                        <Countdown v-if="a.status.value === 'live'" :to="a.ends_at" label="Berakhir dalam" />
                        <Countdown v-else :to="a.starts_at" label="Dimulai dalam" />
                    </div>
                </Link>
            </div>
        </section>

        <!-- Segera -->
        <section v-if="upcoming.length" class="mx-auto max-w-7xl px-4 pt-14">
            <h2 class="mb-5 text-2xl font-bold text-ink">✨ Segera dilelang</h2>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <LotCard v-for="lot in upcoming" :key="lot.id" :lot="lot" />
            </div>
        </section>

        <!-- Cara kerja -->
        <section class="mx-auto max-w-7xl px-4 pt-16">
            <div class="card overflow-hidden">
                <div class="grid md:grid-cols-4">
                    <div v-for="(s, i) in steps" :key="i" class="border-stone-100 p-6 md:border-r last:md:border-r-0">
                        <div class="text-3xl">{{ s.icon }}</div>
                        <p class="mt-3 text-xs font-bold text-brand-700">LANGKAH {{ i + 1 }}</p>
                        <h3 class="font-semibold text-ink">{{ s.title }}</h3>
                        <p class="mt-1 text-sm text-stone-500">{{ s.text }}</p>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
