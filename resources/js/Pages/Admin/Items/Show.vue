<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import LotImage from '@/Components/LotImage.vue';
import { dateTime, money } from '@/lib/format';

const props = defineProps({ item: Object });
const active = ref(0);

const flow = ['received', 'inspected', 'approved', 'listed', 'sold', 'delivered'];
const flowLabels = { received: 'Diterima', inspected: 'Diperiksa', approved: 'Disetujui', listed: 'Dilelang', sold: 'Terjual', delivered: 'Diserahkan' };
const stepIndex = flow.indexOf(props.item.status.value);

const transition = async (to) => {
    const { isConfirmed, value } = await Swal.fire({
        title: `Ubah status ke "${to.label}"?`,
        input: 'textarea',
        inputLabel: to.value === 'inspected' ? 'Catatan inspeksi (opsional)' : 'Catatan (opsional)',
        showCancelButton: true,
        confirmButtonText: 'Ubah status',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#1c1917',
    });
    if (isConfirmed) router.post(route('admin.items.transition', props.item.id), { status: to.value, notes: value || null }, { preserveScroll: true });
};
const destroy = () => confirm('Hapus barang ini?') && router.delete(route('admin.items.destroy', props.item.id));
</script>

<template>
    <Head :title="item.title" />
    <AdminLayout :title="`${item.code} — ${item.title}`">
        <!-- Progres alur kerja -->
        <ol class="card mb-6 flex overflow-x-auto p-4 text-xs">
            <li v-for="(s, i) in flow" :key="s" class="flex flex-1 items-center gap-2">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full font-bold"
                    :class="i <= stepIndex ? 'bg-brand-500 text-ink' : 'bg-stone-100 text-stone-400'">{{ i + 1 }}</span>
                <span class="whitespace-nowrap" :class="i <= stepIndex ? 'font-semibold text-ink' : 'text-stone-400'">{{ flowLabels[s] }}</span>
                <span v-if="i < flow.length - 1" class="mx-2 h-px flex-1 bg-stone-200" />
            </li>
        </ol>

        <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
            <div class="space-y-6">
                <section class="card overflow-hidden">
                    <div class="aspect-[16/9] bg-stone-100"><LotImage :src="item.images[active]?.url" /></div>
                    <div v-if="item.images.length > 1" class="flex gap-2 p-3">
                        <button v-for="(img, i) in item.images" :key="img.id" class="h-14 w-20 overflow-hidden rounded-lg ring-2" :class="i === active ? 'ring-brand-500' : 'ring-transparent'" @click="active = i">
                            <img :src="img.url" class="h-full w-full object-cover" alt="" />
                        </button>
                    </div>
                </section>
                <section class="card p-6">
                    <h2 class="font-semibold text-ink">Deskripsi</h2>
                    <p class="mt-2 text-sm whitespace-pre-line text-stone-600">{{ item.description || '-' }}</p>
                    <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                        <div class="flex justify-between border-b border-stone-100 py-1"><dt class="text-stone-500">Kategori</dt><dd>{{ item.category }}</dd></div>
                        <div class="flex justify-between border-b border-stone-100 py-1"><dt class="text-stone-500">Kondisi</dt><dd>{{ item.condition }}</dd></div>
                        <div v-for="a in item.attributes" :key="a.label" class="flex justify-between border-b border-stone-100 py-1"><dt class="text-stone-500">{{ a.label }}</dt><dd>{{ a.value }}</dd></div>
                    </dl>
                </section>
                <section v-if="item.lots.length" class="card overflow-x-auto">
                    <h2 class="px-4 pt-4 font-semibold text-ink">Riwayat lelang</h2>
                    <table class="tbl mt-2">
                        <thead><tr><th>Sesi</th><th>Lot</th><th>Harga</th><th>Bid</th><th>Status</th></tr></thead>
                        <tbody class="divide-y divide-stone-100">
                            <tr v-for="l in item.lots" :key="l.id">
                                <td><Link :href="route('admin.auctions.show', l.auction_id)" class="link">{{ l.auction }}</Link></td>
                                <td>{{ l.lot_number }}</td><td>{{ l.current_price === null ? '🔒' : money(l.current_price) }}</td><td>{{ l.bids_count ?? '🔒' }}</td>
                                <td><StatusBadge :status="l.status" /></td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>

            <aside class="space-y-4">
                <section class="card p-6">
                    <StatusBadge :status="item.status" />
                    <div v-if="item.transitions.length" class="mt-4 space-y-2">
                        <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Langkah berikutnya</p>
                        <button v-for="t in item.transitions" :key="t.value" class="w-full" :class="t.value === 'returned' ? 'btn-outline' : 'btn-primary'" @click="transition(t)">
                            {{ t.value === 'returned' ? '↩ Kembalikan ke penitip' : `→ ${t.label}` }}
                        </button>
                    </div>
                    <p v-else-if="item.status.value === 'listed'" class="mt-3 text-sm text-stone-600">Barang sedang dalam sesi lelang.</p>
                    <div class="mt-4 flex gap-2 border-t border-stone-100 pt-4">
                        <Link v-if="item.editable" :href="route('admin.items.edit', item.id)" class="btn-outline btn-sm flex-1">Ubah data</Link>
                        <button v-if="!item.lots.length" class="btn-outline btn-sm text-red-600" @click="destroy">Hapus</button>
                    </div>
                </section>
                <section class="card p-6 text-sm">
                    <dl class="space-y-3">
                        <div><dt class="text-stone-500">Penitip</dt><dd><Link :href="route('admin.consignors.show', item.consignor.id)" class="link">{{ item.consignor.code }} — {{ item.consignor.name }}</Link></dd></div>
                        <div><dt class="text-stone-500">Harga limit</dt><dd class="text-lg font-bold text-ink">{{ money(item.reserve_price) }}</dd></div>
                        <div><dt class="text-stone-500">Estimasi</dt><dd>{{ item.estimate_low ? `${money(item.estimate_low)} – ${money(item.estimate_high)}` : '-' }}</dd></div>
                        <div><dt class="text-stone-500">Komisi berlaku</dt><dd>{{ item.commission_rate }}%</dd></div>
                        <div><dt class="text-stone-500">Diterima</dt><dd>{{ dateTime(item.received_at) }}</dd></div>
                        <div><dt class="text-stone-500">Lokasi gudang</dt><dd>{{ item.storage_location || '-' }}</dd></div>
                        <div><dt class="text-stone-500">Inspeksi</dt><dd>{{ item.inspector || '-' }}<span v-if="item.inspection_notes" class="block whitespace-pre-line text-stone-600">{{ item.inspection_notes }}</span></dd></div>
                    </dl>
                </section>
            </aside>
        </div>
    </AdminLayout>
</template>
