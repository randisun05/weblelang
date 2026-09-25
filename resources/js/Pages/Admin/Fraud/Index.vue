<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { dateTime } from '@/lib/format';

defineProps({ status: String, counts: Object, flags: Object });

const tabs = [['open', 'Perlu ditinjau'], ['confirmed', 'Dikonfirmasi'], ['dismissed', 'Diabaikan']];

const review = async (flag, decision) => {
    const confirmCase = decision === 'confirmed';
    const { isConfirmed, value } = await Swal.fire({
        title: confirmCase ? 'Konfirmasi kecurangan?' : 'Abaikan kecurigaan ini?',
        input: 'text', inputLabel: 'Catatan (opsional)',
        html: confirmCase ? '<label style="display:flex;gap:6px;justify-content:center;font-size:14px"><input id="blk" type="checkbox" checked> Blokir akun-akun terkait</label>' : '',
        showCancelButton: true, confirmButtonText: confirmCase ? 'Konfirmasi' : 'Abaikan', confirmButtonColor: confirmCase ? '#dc2626' : '#57534e',
        preConfirm: (note) => ({ note, block: confirmCase && document.getElementById('blk')?.checked }),
    });
    if (isConfirmed) router.post(route('admin.fraud.review', flag.id), { decision, note: value.note || null, block_users: value.block }, { preserveScroll: true });
};
</script>

<template>
    <Head title="Kecurigaan" />
    <AdminLayout title="🚩 Indikasi Kecurangan (Shill Bidding)">
        <p class="mb-4 max-w-3xl text-sm text-stone-600">
            Sistem otomatis menandai akun yang menawar di lot yang sama dari <b>perangkat</b> atau <b>IP</b> yang sama, serta penawar yang
            nomor HP/NIK-nya sama dengan <b>penitip</b> barang. IP yang sama bisa wajar (kantor/keluarga) — tinjau sebelum mengambil tindakan.
        </p>
        <div class="mb-4 flex gap-1 rounded-xl bg-stone-200/60 p-1 text-sm font-medium w-fit">
            <Link v-for="[value, label] in tabs" :key="value" :href="route('admin.fraud.index', { status: value })"
                class="rounded-lg px-4 py-1.5" :class="status === value ? 'bg-white shadow-sm' : ''">
                {{ label }} <span class="text-stone-400">{{ counts[value] ?? 0 }}</span>
            </Link>
        </div>

        <div v-if="flags.data.length" class="space-y-3">
            <div v-for="f in flags.data" :key="f.id" class="card flex flex-wrap items-start gap-4 p-5">
                <span class="rounded-full px-2.5 py-0.5 text-xs font-bold" :class="f.severity === 'high' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800'">
                    {{ f.severity === 'high' ? 'TINGGI' : 'SEDANG' }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-ink">{{ f.label }}</p>
                    <p v-if="f.lot" class="text-sm text-stone-600">Lot {{ f.lot.number }} — <Link :href="route('lots.show', f.lot.id)" class="link">{{ f.lot.title }}</Link></p>
                    <p class="mt-1 text-xs text-stone-500">{{ Object.entries(f.details || {}).map(([k, v]) => `${k}: ${Array.isArray(v) ? v.join(', ') : v}`).join(' · ') }} · {{ dateTime(f.created_at) }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <Link v-for="u in f.users" :key="u.id" :href="route('admin.bidders.show', u.id)"
                            class="rounded-lg bg-stone-100 px-2.5 py-1 text-xs hover:bg-stone-200">
                            {{ u.name }} <span class="text-stone-500">{{ u.email }}</span> <span v-if="u.is_blocked" class="text-red-600">(diblokir)</span>
                        </Link>
                    </div>
                    <p v-if="f.reviewer" class="mt-2 text-xs text-stone-500">Ditinjau {{ f.reviewer }}<template v-if="f.note">: “{{ f.note }}”</template></p>
                </div>
                <div v-if="status === 'open'" class="flex gap-2">
                    <button class="btn-outline btn-sm" @click="review(f, 'dismissed')">Abaikan</button>
                    <button class="btn-danger btn-sm" @click="review(f, 'confirmed')">Konfirmasi</button>
                </div>
            </div>
        </div>
        <EmptyState v-else title="Tidak ada indikasi kecurangan" icon="✅" />
        <Pagination :links="flags.links" />
    </AdminLayout>
</template>
