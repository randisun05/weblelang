<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { dateTime, money } from '@/lib/format';

const props = defineProps({ bidder: Object, invoices: Array, bids: Array });

const verify = () => router.post(route('admin.bidders.kyc', props.bidder.id), { decision: 'verified' }, { preserveScroll: true });
const reject = async () => {
    const { isConfirmed, value } = await Swal.fire({
        title: 'Tolak KYC', input: 'text', inputLabel: 'Alasan (akan dilihat peserta)', inputPlaceholder: 'Foto KTP buram / nama tidak sesuai',
        inputValidator: (v) => (!v ? 'Alasan wajib diisi' : undefined), showCancelButton: true, confirmButtonText: 'Tolak', confirmButtonColor: '#dc2626',
    });
    if (isConfirmed) router.post(route('admin.bidders.kyc', props.bidder.id), { decision: 'rejected', note: value }, { preserveScroll: true });
};
const block = () => confirm(props.bidder.is_blocked ? 'Buka blokir peserta?' : 'Blokir peserta ini?') && router.post(route('admin.bidders.block', props.bidder.id), {}, { preserveScroll: true });
</script>

<template>
    <Head :title="bidder.name" />
    <AdminLayout :title="`Peserta: ${bidder.name}`">
        <div class="grid gap-6 xl:grid-cols-[1fr_420px]">
            <div class="space-y-6">
                <section class="card p-6">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold text-ink">Verifikasi identitas</h2>
                        <StatusBadge :status="bidder.kyc" />
                    </div>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-stone-500">Nama</dt><dd class="font-medium">{{ bidder.name }}</dd></div>
                        <div><dt class="text-stone-500">NIK</dt><dd class="font-mono">{{ bidder.nik || '-' }}</dd></div>
                        <div><dt class="text-stone-500">Email / HP</dt><dd>{{ bidder.email }}<br />{{ bidder.phone }}</dd></div>
                        <div><dt class="text-stone-500">Alamat</dt><dd>{{ bidder.address || '-' }}</dd></div>
                    </dl>
                    <div v-if="bidder.has_ktp" class="mt-4 overflow-hidden rounded-xl ring-1 ring-stone-200">
                        <img :src="route('admin.bidders.ktp', bidder.id)" alt="KTP" class="max-h-96 w-full object-contain bg-stone-50" />
                    </div>
                    <p v-if="bidder.kyc_note" class="mt-3 text-sm text-red-700">Catatan: {{ bidder.kyc_note }}</p>
                    <div v-if="bidder.kyc.value === 'pending'" class="mt-4 flex gap-2">
                        <button class="btn-primary" @click="verify">✓ Verifikasi</button>
                        <button class="btn-outline text-red-600" @click="reject">Tolak</button>
                    </div>
                    <p class="mt-4 text-xs text-stone-400">Akses ke data & dokumen ini tercatat di log audit.</p>
                </section>
                <section class="card overflow-x-auto">
                    <h2 class="px-4 pt-4 font-semibold text-ink">Penawaran terakhir</h2>
                    <table class="tbl mt-2">
                        <thead><tr><th>Lot</th><th>Nominal</th><th>IP</th><th>Waktu</th></tr></thead>
                        <tbody class="divide-y divide-stone-100">
                            <tr v-for="b in bids" :key="b.id">
                                <td><Link :href="route('lots.show', b.lot_id)" class="link">{{ b.title }}</Link></td>
                                <td>{{ b.amount === null ? '🔒 tertutup' : money(b.amount) }} <span v-if="b.is_auto" class="text-xs text-stone-500">auto</span></td>
                                <td class="font-mono text-xs">{{ b.ip }}</td>
                                <td class="text-xs">{{ dateTime(b.at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
            <aside class="space-y-6">
                <section class="card p-6 text-sm">
                    <p class="text-stone-500">Terdaftar {{ dateTime(bidder.created_at) }}</p>
                    <p v-if="bidder.kyc_verified_at" class="text-stone-500">Terverifikasi {{ dateTime(bidder.kyc_verified_at) }}</p>
                    <button class="mt-4 w-full" :class="bidder.is_blocked ? 'btn-outline' : 'btn-danger'" @click="block">
                        {{ bidder.is_blocked ? 'Buka blokir' : 'Blokir peserta' }}
                    </button>
                </section>
                <section class="card p-6">
                    <h2 class="font-semibold text-ink">Invoice</h2>
                    <ul class="mt-3 divide-y divide-stone-100 text-sm">
                        <li v-for="i in invoices" :key="i.id" class="flex items-center justify-between py-2">
                            <Link :href="route('admin.invoices.show', i.id)" class="link">{{ i.number }}</Link>
                            <span>{{ money(i.total) }}</span>
                            <StatusBadge :status="i.status" />
                        </li>
                        <li v-if="!invoices.length" class="py-2 text-stone-500">Belum ada.</li>
                    </ul>
                </section>
            </aside>
        </div>
    </AdminLayout>
</template>
