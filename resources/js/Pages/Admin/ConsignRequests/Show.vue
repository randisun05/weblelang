<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Field from '@/Components/Field.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import { dateTime, money } from '@/lib/format';

const props = defineProps({ request: Object, matches: Array, categories: Array, defaultCommission: Number });
const r = props.request;
const active = ref(0);

const form = useForm({
    consignor_id: props.matches[0]?.id ?? null,
    category_id: r.category_id ?? '',
    reserve_price: r.expected_price,
    commission_rate: props.defaultCommission,
    storage_location: '',
});
const accept = () => form.post(route('admin.consign-requests.accept', r.id));
const reviewing = () => router.post(route('admin.consign-requests.reviewing', r.id), {}, { preserveScroll: true });
const reject = async () => {
    const { isConfirmed, value } = await Swal.fire({
        title: 'Tolak pengajuan?', input: 'text', inputLabel: 'Alasan (dikirim ke pengaju)',
        inputPlaceholder: 'mis. Kategori barang belum kami layani / foto kurang jelas',
        inputValidator: (v) => (!v ? 'Alasan wajib diisi' : undefined),
        showCancelButton: true, confirmButtonText: 'Tolak', confirmButtonColor: '#dc2626',
    });
    if (isConfirmed) router.post(route('admin.consign-requests.reject', r.id), { reason: value }, { preserveScroll: true });
};
</script>

<template>
    <Head :title="r.code" />
    <AdminLayout :title="`Pengajuan ${r.code} — ${r.title}`">
        <div class="grid gap-6 xl:grid-cols-[1fr_400px]">
            <div class="space-y-6">
                <section class="card overflow-hidden">
                    <div class="aspect-[16/10] bg-stone-100"><img :src="r.photos[active]" class="h-full w-full object-contain" alt="" /></div>
                    <div v-if="r.photos.length > 1" class="flex gap-2 p-3">
                        <button v-for="(p, i) in r.photos" :key="i" class="h-14 w-20 overflow-hidden rounded-lg ring-2" :class="i === active ? 'ring-brand-500' : 'ring-transparent'" @click="active = i">
                            <img :src="p" class="h-full w-full object-cover" alt="" />
                        </button>
                    </div>
                </section>
                <section class="card p-6 text-sm">
                    <div class="flex flex-wrap items-center gap-2"><StatusBadge :status="r.status" /><span class="text-stone-500">masuk {{ dateTime(r.created_at) }}</span></div>
                    <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div><dt class="text-stone-500">Kategori</dt><dd>{{ r.category || '-' }}</dd></div>
                        <div><dt class="text-stone-500">Kondisi</dt><dd>{{ r.condition }}</dd></div>
                        <div><dt class="text-stone-500">Harapan harga</dt><dd class="font-semibold">{{ r.expected_price ? money(r.expected_price) : '-' }}</dd></div>
                        <div><dt class="text-stone-500">Serah terima</dt><dd>{{ r.handover }}</dd></div>
                    </dl>
                    <p class="mt-4 whitespace-pre-line text-stone-700">{{ r.description }}</p>
                    <div class="mt-4 rounded-xl bg-stone-50 p-4">
                        <p class="font-semibold text-ink">{{ r.name }}</p>
                        <p>{{ r.phone }} · {{ r.email }} · {{ r.city }}</p>
                        <a :href="`https://wa.me/${r.phone.replace(/^0/, '62').replace(/\D/g, '')}`" target="_blank" rel="noopener" class="link">Hubungi via WhatsApp ↗</a>
                    </div>
                </section>
            </div>

            <aside class="space-y-4">
                <section v-if="r.open" class="card space-y-4 p-6">
                    <h2 class="font-semibold text-ink">Terima pengajuan</h2>
                    <Field label="Penitip">
                        <select v-model="form.consignor_id" class="input">
                            <option :value="null">➕ Buat penitip baru dari data pengaju</option>
                            <option v-for="m in matches" :key="m.id" :value="m.id">{{ m.code }} — {{ m.name }} ({{ m.phone }})</option>
                        </select>
                        <p v-if="matches.length" class="mt-1 text-xs text-amber-700">Ditemukan penitip dengan HP/email yang sama — pilih agar tidak dobel.</p>
                    </Field>
                    <Field label="Kategori" :error="form.errors.category_id" required>
                        <select v-model="form.category_id" class="input">
                            <option value="" disabled>Pilih kategori</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </Field>
                    <Field label="Harga limit awal" hint="Bisa diubah setelah pemeriksaan & kesepakatan."><MoneyInput v-model="form.reserve_price" /></Field>
                    <Field v-if="!form.consignor_id" label="Komisi penitip baru (%)"><input v-model="form.commission_rate" type="number" step="0.5" class="input" /></Field>
                    <Field label="Lokasi penyimpanan (opsional)"><input v-model="form.storage_location" class="input" /></Field>
                    <button class="btn-primary w-full" :disabled="form.processing" @click="accept">✓ Terima & buat data barang</button>
                    <div class="flex gap-2">
                        <button v-if="r.status.value === 'new'" class="btn-outline btn-sm flex-1" @click="reviewing">Tandai sedang ditinjau</button>
                        <button class="btn-outline btn-sm flex-1 text-red-600" @click="reject">Tolak</button>
                    </div>
                </section>
                <section v-else class="card p-6 text-sm">
                    <p>Diproses oleh <b>{{ r.reviewer }}</b> · {{ dateTime(r.reviewed_at) }}</p>
                    <p v-if="r.reject_reason" class="mt-2 text-red-700">Alasan ditolak: {{ r.reject_reason }}</p>
                    <p v-if="r.item" class="mt-2">Barang: <Link :href="route('admin.items.show', r.item.id)" class="link">{{ r.item.code }} — {{ r.item.title }}</Link></p>
                    <p v-if="r.consignor" class="mt-1">Penitip: <Link :href="route('admin.consignors.show', r.consignor.id)" class="link">{{ r.consignor.code }} — {{ r.consignor.name }}</Link></p>
                </section>
            </aside>
        </div>
    </AdminLayout>
</template>
