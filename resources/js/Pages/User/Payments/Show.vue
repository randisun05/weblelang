<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';
import AccountLayout from '@/Layouts/AccountLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { money } from '@/lib/format';

const props = defineProps({ payment: Object });

// Webhook gateway bisa datang beberapa detik setelah peserta kembali; muat ulang status berkala.
let timer;
onMounted(() => {
    timer = setInterval(() => {
        if (props.payment.status.value !== 'pending') return clearInterval(timer);
        router.reload({ only: ['payment'] });
    }, 4000);
});
onBeforeUnmount(() => clearInterval(timer));

const icons = { pending: '⏳', paid: '✅', failed: '❌', expired: '⌛' };
</script>

<template>
    <Head title="Status Pembayaran" />
    <AccountLayout title="Status Pembayaran">
        <div class="card mx-auto max-w-lg p-8 text-center">
            <div class="text-5xl">{{ icons[payment.status.value] }}</div>
            <p class="mt-4 text-sm text-stone-500">{{ payment.description }}</p>
            <p class="text-3xl font-extrabold text-ink">{{ money(payment.amount) }}</p>
            <div class="mt-3"><StatusBadge :status="payment.status" /></div>
            <p class="mt-2 font-mono text-xs text-stone-400">{{ payment.reference }} · {{ payment.gateway }}<template v-if="payment.method"> · {{ payment.method }}</template></p>

            <p v-if="payment.status.value === 'pending'" class="mt-6 text-sm text-stone-600">
                Kami menunggu konfirmasi dari payment gateway. Halaman ini diperbarui otomatis.
            </p>
            <p v-else-if="payment.status.value === 'paid'" class="mt-6 text-sm text-green-700">Pembayaran berhasil diterima. Terima kasih!</p>
            <p v-else class="mt-6 text-sm text-stone-600">Pembayaran tidak berhasil. Silakan coba lagi.</p>

            <div class="mt-6 flex flex-col gap-2">
                <a v-if="payment.checkout_url && payment.status.value === 'pending'" :href="payment.checkout_url" class="btn-primary">Lanjutkan pembayaran</a>
                <Link :href="payment.back_url" class="btn-outline">Kembali</Link>
            </div>
        </div>
    </AccountLayout>
</template>
