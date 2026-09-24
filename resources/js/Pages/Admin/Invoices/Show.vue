<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { dateTime, money } from '@/lib/format';

const props = defineProps({ invoice: Object });

const markPaid = async () => {
    const { isConfirmed, value } = await Swal.fire({
        title: 'Konfirmasi pembayaran', text: `Pastikan dana ${money(props.invoice.total)} sudah masuk ke rekening.`,
        input: 'text', inputLabel: 'No. referensi / mutasi (opsional)', showCancelButton: true, confirmButtonText: 'Tandai lunas', confirmButtonColor: '#16a34a',
    });
    if (isConfirmed) router.post(route('admin.invoices.paid', props.invoice.id), { reference: value || null }, { preserveScroll: true });
};
const cancel = async () => {
    const { isConfirmed, value } = await Swal.fire({
        title: 'Batalkan invoice?', text: 'Gunakan bila pemenang wanprestasi. Barang akan kembali siap dilelang.', input: 'text', inputLabel: 'Alasan',
        inputValidator: (v) => (!v ? 'Alasan wajib diisi' : undefined), showCancelButton: true, confirmButtonText: 'Batalkan', confirmButtonColor: '#dc2626',
    });
    if (isConfirmed) router.post(route('admin.invoices.cancel', props.invoice.id), { reason: value }, { preserveScroll: true });
};
const deliver = () => confirm('Catat bahwa barang sudah diserahkan ke pemenang?') && router.post(route('admin.invoices.deliver', props.invoice.id), {}, { preserveScroll: true });
</script>

<template>
    <Head :title="invoice.number" />
    <AdminLayout :title="`Invoice ${invoice.number}`">
        <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
            <section class="card p-6">
                <StatusBadge :status="invoice.status" />
                <h2 class="mt-3 text-lg font-bold text-ink">{{ invoice.item.title }}</h2>
                <p class="text-sm text-stone-500">{{ invoice.auction }} · Lot {{ invoice.lot_number }} · <Link :href="route('admin.items.show', invoice.item.id)" class="link">{{ invoice.item.code }}</Link> · Penitip: {{ invoice.consignor }}</p>
                <dl class="mt-6 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-stone-500">Harga palu</dt><dd>{{ money(invoice.hammer_price) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-stone-500">Premi pembeli</dt><dd>{{ money(invoice.buyer_premium) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-stone-500">Biaya admin</dt><dd>{{ money(invoice.admin_fee) }}</dd></div>
                    <div class="flex justify-between border-t border-stone-200 pt-3 text-lg font-bold text-ink"><dt>Total</dt><dd>{{ money(invoice.total) }}</dd></div>
                </dl>
                <dl class="mt-6 grid gap-3 border-t border-stone-100 pt-6 text-sm sm:grid-cols-2">
                    <div><dt class="text-stone-500">Pemenang</dt><dd>{{ invoice.user.name }}<br />{{ invoice.user.email }} · {{ invoice.user.phone }}</dd></div>
                    <div><dt class="text-stone-500">Jatuh tempo</dt><dd>{{ dateTime(invoice.due_at) }}</dd></div>
                    <div><dt class="text-stone-500">Pembayaran</dt><dd>{{ invoice.payment_method || '-' }} <span v-if="invoice.payment_ref" class="font-mono text-xs">({{ invoice.payment_ref }})</span><br />{{ invoice.paid_at ? dateTime(invoice.paid_at) : '' }}</dd></div>
                    <div><dt class="text-stone-500">Serah terima</dt><dd>{{ invoice.delivered_at ? dateTime(invoice.delivered_at) : 'Belum' }}</dd></div>
                </dl>
                <div v-if="invoice.has_proof" class="mt-6">
                    <p class="mb-2 text-sm font-medium">Bukti transfer dari pemenang</p>
                    <img :src="route('admin.invoices.proof', invoice.id)" alt="Bukti transfer" class="max-h-96 rounded-xl ring-1 ring-stone-200" />
                </div>
            </section>
            <aside class="space-y-4">
                <section class="card space-y-2 p-6">
                    <template v-if="invoice.status.value === 'unpaid'">
                        <button class="btn w-full bg-green-600 text-white hover:bg-green-500" @click="markPaid">✓ Konfirmasi lunas</button>
                        <button class="btn-outline w-full text-red-600" @click="cancel">Batalkan (wanprestasi)</button>
                    </template>
                    <button v-else-if="invoice.status.value === 'paid' && !invoice.delivered_at" class="btn-primary w-full" @click="deliver">📦 Catat serah terima barang</button>
                    <p v-else class="text-sm text-stone-500">Tidak ada tindakan tersisa.</p>
                </section>
                <section v-if="invoice.settlement" class="card p-6 text-sm">
                    <p class="text-stone-500">Settlement penitip</p>
                    <p class="font-mono">{{ invoice.settlement.number }}</p>
                    <p class="mt-1 text-lg font-bold">{{ money(invoice.settlement.net_amount) }}</p>
                    <StatusBadge :status="invoice.settlement.status" />
                    <Link :href="route('admin.settlements.index')" class="link mt-3 block">Kelola settlement →</Link>
                </section>
            </aside>
        </div>
    </AdminLayout>
</template>
