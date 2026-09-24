<script setup>
import { Link } from '@inertiajs/vue3';
import FlashMessages from '@/Components/FlashMessages.vue';

defineProps({ title: String, subtitle: { type: String, default: '' } });
</script>

<template>
    <div class="grid min-h-screen lg:grid-cols-2">
        <FlashMessages />
        <div class="relative hidden overflow-hidden bg-ink lg:block">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(245,158,11,.35),transparent_55%)]" />
            <div class="relative flex h-full flex-col justify-between p-12 text-white">
                <Link :href="route('home')" class="font-display text-2xl font-extrabold">Web<span class="text-brand-400">Lelang</span></Link>
                <div>
                    <p class="font-display text-4xl leading-tight font-bold">Barang titipan terbaik,<br />harga ditentukan oleh Anda.</p>
                    <ul class="mt-6 space-y-2 text-stone-300">
                        <li>✓ Peserta terverifikasi identitas</li>
                        <li>✓ Countdown & riwayat penawaran real-time</li>
                        <li>✓ Pembayaran aman & barang terjamin diperiksa</li>
                    </ul>
                </div>
                <p class="text-xs text-stone-500">© {{ new Date().getFullYear() }} WebLelang</p>
            </div>
        </div>
        <div class="flex items-center justify-center px-4 py-12">
            <div class="w-full max-w-md">
                <Link :href="route('home')" class="mb-8 block font-display text-2xl font-extrabold text-ink lg:hidden">Web<span class="text-brand-600">Lelang</span></Link>
                <h1 class="text-2xl font-bold text-ink">{{ title }}</h1>
                <p v-if="subtitle" class="mt-1 text-sm text-stone-500">{{ subtitle }}</p>
                <div class="mt-8"><slot /></div>
            </div>
        </div>
    </div>
</template>
