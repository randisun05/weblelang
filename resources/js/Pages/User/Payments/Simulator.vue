<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { money } from '@/lib/format';

const props = defineProps({ payment: Object });
const busy = ref(false);
const simulate = (outcome) => {
    busy.value = true;
    router.post(route('payments.simulate', props.payment.reference), { outcome }, { onFinish: () => (busy.value = false) });
};
</script>

<template>
    <Head title="Simulator Pembayaran" />
    <div class="flex min-h-screen items-center justify-center bg-stone-100 p-4">
        <div class="card w-full max-w-md overflow-hidden">
            <div class="bg-amber-400 px-6 py-3 text-center text-sm font-bold text-ink">⚠ MODE SIMULATOR — bukan transaksi sungguhan</div>
            <div class="p-8 text-center">
                <p class="text-sm text-stone-500">{{ payment.description }}</p>
                <p class="mt-1 text-3xl font-extrabold text-ink">{{ money(payment.amount) }}</p>
                <p class="mt-1 font-mono text-xs text-stone-400">{{ payment.reference }}</p>
                <p class="mt-6 text-sm text-stone-600">Halaman ini meniru halaman checkout gateway untuk pengembangan & demo. Di production gunakan Midtrans atau Xendit.</p>
                <div class="mt-6 grid gap-2">
                    <button class="btn bg-green-600 text-white hover:bg-green-500" :disabled="busy" @click="simulate('paid')">✓ Simulasikan pembayaran berhasil</button>
                    <button class="btn-outline" :disabled="busy" @click="simulate('failed')">✕ Simulasikan gagal</button>
                </div>
            </div>
        </div>
    </div>
</template>
