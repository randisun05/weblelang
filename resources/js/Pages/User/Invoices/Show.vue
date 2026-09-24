<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import AccountLayout from '@/Layouts/AccountLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Countdown from '@/Components/Countdown.vue';
import LotImage from '@/Components/LotImage.vue';
import { dateTime, money } from '@/lib/format';

const props = defineProps({ invoice: Object, midtrans: Object, bank: Object });

const proof = useForm({ proof: null });
const paying = ref(false);

const loadSnap = () =>
    new Promise((resolve, reject) => {
        if (window.snap) return resolve(window.snap);
        const s = document.createElement('script');
        s.src = props.midtrans.is_production ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js';
        s.dataset.clientKey = props.midtrans.client_key;
        s.onload = () => resolve(window.snap);
        s.onerror = reject;
        document.head.appendChild(s);
    });

const payOnline = async () => {
    paying.value = true;
    try {
        const [{ data }, snap] = await Promise.all([axios.post(route('user.invoices.snap', props.invoice.id)), loadSnap()]);
        snap.pay(data.token, {
            onSuccess: () => { Swal.fire({ icon: 'success', title: 'Pembayaran berhasil', text: 'Status invoice akan diperbarui otomatis.' }); router.reload(); },
            onPending: () => Swal.fire({ icon: 'info', title: 'Menunggu pembayaran', text: 'Selesaikan pembayaran sesuai instruksi.' }),
            onError: () => Swal.fire({ icon: 'error', title: 'Pembayaran gagal' }),
        });
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Gagal', text: e.response?.data?.message ?? 'Pembayaran online tidak tersedia.' });
    } finally {
        paying.value = false;
    }
};

const uploadProof = () => proof.post(route('user.invoices.proof', props.invoice.id), { forceFormData: true, preserveScroll: true });
</script>

<template>
    <Head :title="`Invoice ${invoice.number}`" />
    <AccountLayout :title="`Invoice ${invoice.number}`">
        <div class="grid gap-6 lg:grid-cols-[1fr_380px]">
            <section class="card p-6">
                <div class="flex items-start gap-4">
                    <div class="h-24 w-32 shrink-0 overflow-hidden rounded-xl"><LotImage :src="invoice.image" /></div>
                    <div>
                        <StatusBadge :status="invoice.status" />
                        <h2 class="mt-2 text-lg font-bold text-ink">{{ invoice.title }}</h2>
                        <p class="text-sm text-stone-500">{{ invoice.auction }}</p>
                        <Link :href="route('lots.show', invoice.lot_id)" class="link text-sm">Lihat lot</Link>
                    </div>
                </div>
                <dl class="mt-6 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-stone-500">Harga palu (penawaran menang)</dt><dd>{{ money(invoice.hammer_price) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-stone-500">Premi pembeli ({{ invoice.buyer_premium_rate }}%)</dt><dd>{{ money(invoice.buyer_premium) }}</dd></div>
                    <div v-if="invoice.admin_fee" class="flex justify-between"><dt class="text-stone-500">Biaya admin</dt><dd>{{ money(invoice.admin_fee) }}</dd></div>
                    <div class="flex justify-between border-t border-stone-200 pt-3 text-lg font-bold text-ink"><dt>Total</dt><dd>{{ money(invoice.total) }}</dd></div>
                </dl>
                <p v-if="invoice.paid_at" class="mt-6 rounded-xl bg-green-50 p-4 text-sm text-green-800">
                    ✓ Lunas pada {{ dateTime(invoice.paid_at) }} via {{ invoice.payment_method }}.
                    <template v-if="invoice.delivered_at"> Barang diserahkan {{ dateTime(invoice.delivered_at) }}.</template>
                    <template v-else> Tim kami akan menghubungi Anda untuk pengambilan/pengiriman barang.</template>
                </p>
            </section>

            <aside v-if="invoice.status.value === 'unpaid'" class="space-y-4">
                <div class="card p-6">
                    <Countdown :to="invoice.due_at" label="Batas pembayaran" />
                    <p class="mt-2 text-xs text-stone-500">Jatuh tempo {{ dateTime(invoice.due_at) }}. Lewat dari itu invoice dapat dibatalkan.</p>
                    <button v-if="midtrans.enabled" class="btn-primary mt-5 w-full py-3" :disabled="paying" @click="payOnline">
                        💳 Bayar online (VA, QRIS, e-wallet)
                    </button>
                </div>
                <div class="card p-6">
                    <h3 class="font-semibold text-ink">Transfer manual</h3>
                    <div class="mt-3 rounded-xl bg-stone-50 p-4 text-sm">
                        <p class="text-stone-500">{{ bank.name }}</p>
                        <p class="font-mono text-lg font-bold text-ink">{{ bank.account }}</p>
                        <p class="text-stone-600">a.n. {{ bank.holder }}</p>
                        <p class="mt-2 text-stone-600">Nominal: <b>{{ money(invoice.total) }}</b><br />Berita: <b>{{ invoice.number }}</b></p>
                    </div>
                    <form class="mt-4 space-y-3" @submit.prevent="uploadProof">
                        <input type="file" accept="image/*" class="block text-sm" @input="proof.proof = $event.target.files[0]" />
                        <p v-if="proof.errors.proof" class="text-xs text-red-600">{{ proof.errors.proof }}</p>
                        <button class="btn-dark w-full" :disabled="!proof.proof || proof.processing">Kirim bukti transfer</button>
                        <p v-if="invoice.has_proof" class="text-xs text-green-700">✓ Bukti transfer sudah terkirim, menunggu konfirmasi admin.</p>
                    </form>
                </div>
            </aside>
        </div>
    </AccountLayout>
</template>
