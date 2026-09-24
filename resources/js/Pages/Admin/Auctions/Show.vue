<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import LotImage from '@/Components/LotImage.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { dateTime, money } from '@/lib/format';

const props = defineProps({ auction: Object, lots: Array, registrations: Array, availableItems: Array });

const tab = ref('lots');
const picking = ref(false);
const selected = ref({});
const addForm = useForm({ lots: [] });

const toggle = (item) => {
    if (selected.value[item.id] !== undefined) delete selected.value[item.id];
    else selected.value[item.id] = Math.max(1000, Math.round(item.reserve_price * 0.6 / 100000) * 100000 || item.reserve_price);
};
const addLots = () => {
    addForm.lots = Object.entries(selected.value).map(([item_id, starting_price]) => ({ item_id: Number(item_id), starting_price }));
    addForm.post(route('admin.auctions.lots.store', props.auction.id), { preserveScroll: true, onSuccess: () => { selected.value = {}; picking.value = false; } });
};

const publish = () => router.post(route('admin.auctions.publish', props.auction.id), {}, { preserveScroll: true });
const unpublish = () => router.post(route('admin.auctions.unpublish', props.auction.id), {}, { preserveScroll: true });
const removeLot = (lot) => confirm(`Hapus lot ${lot.lot_number} dari sesi?`) && router.delete(route('admin.auctions.lots.destroy', [props.auction.id, lot.id]), { preserveScroll: true });
const cancelLot = async (lot) => {
    const { isConfirmed, value } = await Swal.fire({
        title: `Batalkan lot ${lot.lot_number}?`, text: 'Penawaran yang ada tidak berlaku lagi.', input: 'text', inputLabel: 'Alasan',
        inputValidator: (v) => (!v ? 'Alasan wajib diisi' : undefined), showCancelButton: true, confirmButtonText: 'Batalkan lot', confirmButtonColor: '#dc2626',
    });
    if (isConfirmed) router.post(route('admin.auctions.lots.cancel', [props.auction.id, lot.id]), { reason: value }, { preserveScroll: true });
};
const decide = (reg, decision) => router.post(route('admin.registrations.decide', reg.id), { decision }, { preserveScroll: true });
</script>

<template>
    <Head :title="auction.title" />
    <AdminLayout :title="`${auction.code} — ${auction.title}`">
        <section class="card mb-6 flex flex-wrap items-center gap-4 p-5">
            <StatusBadge :status="auction.status" />
            <div class="text-sm text-stone-600">{{ dateTime(auction.starts_at) }} – {{ dateTime(auction.ends_at) }}</div>
            <div class="text-sm text-stone-600">Premi {{ auction.buyer_premium_rate }}% · Anti-sniping {{ auction.anti_snipe_minutes }}/{{ auction.extend_minutes }} mnt
                <template v-if="auction.deposit_amount"> · Jaminan {{ money(auction.deposit_amount) }}</template></div>
            <div class="ml-auto flex flex-wrap gap-2">
                <Link v-if="auction.status.value !== 'draft'" :href="route('auctions.show', auction.slug)" class="btn-outline btn-sm" target="_blank">Lihat publik ↗</Link>
                <Link v-if="auction.editable" :href="route('admin.auctions.edit', auction.id)" class="btn-outline btn-sm">Ubah</Link>
                <button v-if="auction.status.value === 'published'" class="btn-outline btn-sm" @click="unpublish">Tarik ke draf</button>
                <button v-if="auction.status.value === 'draft'" class="btn-primary btn-sm" @click="publish">🚀 Terbitkan</button>
            </div>
        </section>

        <div class="mb-4 flex gap-1 rounded-xl bg-stone-200/60 p-1 text-sm font-medium w-fit">
            <button class="rounded-lg px-4 py-1.5" :class="tab === 'lots' ? 'bg-white shadow-sm' : ''" @click="tab = 'lots'">Lot ({{ lots.length }})</button>
            <button class="rounded-lg px-4 py-1.5" :class="tab === 'regs' ? 'bg-white shadow-sm' : ''" @click="tab = 'regs'">Pendaftaran ({{ registrations.length }})</button>
        </div>

        <template v-if="tab === 'lots'">
            <div v-if="auction.editable" class="mb-4">
                <button class="btn-dark btn-sm" @click="picking = !picking">{{ picking ? 'Tutup' : '+ Tambah lot dari barang siap lelang' }}</button>
                <div v-if="picking" class="card mt-3 p-4">
                    <EmptyState v-if="!availableItems.length" title="Tidak ada barang berstatus 'Siap dilelang'" description="Setujui barang terlebih dahulu di menu Barang." icon="📦" />
                    <template v-else>
                        <table class="tbl">
                            <thead><tr><th></th><th>Barang</th><th>Penitip</th><th>Limit</th><th>Harga awal</th></tr></thead>
                            <tbody class="divide-y divide-stone-100">
                                <tr v-for="it in availableItems" :key="it.id">
                                    <td><input type="checkbox" class="rounded" :checked="selected[it.id] !== undefined" @change="toggle(it)" /></td>
                                    <td><span class="font-mono text-xs text-stone-500">{{ it.code }}</span><br />{{ it.title }}</td>
                                    <td>{{ it.consignor }}</td>
                                    <td>{{ money(it.reserve_price) }}</td>
                                    <td class="w-48"><MoneyInput v-if="selected[it.id] !== undefined" v-model="selected[it.id]" /></td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-for="(m, k) in addForm.errors" :key="k" class="mt-2 text-xs text-red-600">{{ m }}</p>
                        <button class="btn-primary mt-4" :disabled="!Object.keys(selected).length || addForm.processing" @click="addLots">
                            Tambahkan {{ Object.keys(selected).length }} lot
                        </button>
                    </template>
                </div>
            </div>

            <div v-if="lots.length" class="card overflow-x-auto">
                <table class="tbl">
                    <thead><tr><th>#</th><th></th><th>Barang</th><th>Awal / Limit</th><th>Tertinggi</th><th>Pemimpin</th><th>Tutup</th><th>Status</th><th></th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        <tr v-for="lot in lots" :key="lot.id">
                            <td class="font-bold">{{ lot.lot_number }}</td>
                            <td class="w-16"><div class="h-10 w-14 overflow-hidden rounded-lg"><LotImage :src="lot.image" class="text-xl" /></div></td>
                            <td><Link :href="route('admin.items.show', lot.item_id)" class="link">{{ lot.title }}</Link><br /><span class="font-mono text-xs text-stone-500">{{ lot.code }}</span></td>
                            <td class="text-xs">{{ money(lot.starting_price) }}<br /><span class="text-stone-500">{{ money(lot.reserve_price) }}</span></td>
                            <td><span class="font-semibold">{{ lot.bids_count ? money(lot.current_price) : '-' }}</span><br /><span class="text-xs text-stone-500">{{ lot.bids_count }} bid</span></td>
                            <td>{{ lot.leader || '-' }}</td>
                            <td class="text-xs">{{ dateTime(lot.ends_at) }}</td>
                            <td><StatusBadge :status="lot.status" /></td>
                            <td class="space-x-2 text-right whitespace-nowrap">
                                <Link :href="route('lots.show', lot.id)" class="link text-xs">Lihat</Link>
                                <button v-if="lot.status.value === 'scheduled' && !lot.bids_count" class="text-xs text-red-600 hover:underline" @click="removeLot(lot)">Hapus</button>
                                <button v-else-if="['scheduled', 'live'].includes(lot.status.value)" class="text-xs text-red-600 hover:underline" @click="cancelLot(lot)">Batalkan</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState v-else title="Belum ada lot" description="Tambahkan barang yang sudah disetujui ke sesi ini." icon="📦" />
        </template>

        <template v-else>
            <div v-if="registrations.length" class="card overflow-x-auto">
                <table class="tbl">
                    <thead><tr><th>Peserta</th><th>Tanggal</th><th>Bukti</th><th>Status</th><th></th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        <tr v-for="r in registrations" :key="r.id">
                            <td>{{ r.user }}<br /><span class="text-xs text-stone-500">{{ r.email }}</span></td>
                            <td class="text-xs">{{ dateTime(r.created_at) }}</td>
                            <td><a v-if="r.has_proof" :href="route('admin.registrations.proof', r.id)" target="_blank" class="link text-xs">Lihat bukti</a></td>
                            <td><StatusBadge :status="r.status" /></td>
                            <td class="space-x-2 text-right whitespace-nowrap">
                                <button v-if="r.status.value !== 'approved'" class="btn-primary btn-sm" @click="decide(r, 'approved')">Setujui</button>
                                <button v-if="r.status.value !== 'rejected'" class="btn-outline btn-sm" @click="decide(r, 'rejected')">Tolak</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState v-else :title="auction.deposit_amount ? 'Belum ada pendaftar' : 'Sesi ini tanpa uang jaminan'" icon="🪪" />
        </template>
    </AdminLayout>
</template>
