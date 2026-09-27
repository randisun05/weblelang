<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { dateTime } from '@/lib/format';

defineProps({ request: Object });
const flow = ['new', 'reviewing', 'accepted'];
const labels = { new: 'Terkirim', reviewing: 'Ditinjau', accepted: 'Diterima' };
</script>

<template>
    <Head :title="`Pengajuan ${request.code}`" />
    <PublicLayout>
        <div class="mx-auto max-w-xl px-4 py-12">
            <div class="card p-8 text-center">
                <p class="text-sm text-stone-500">Pengajuan titip barang</p>
                <p class="font-mono text-lg font-bold text-ink">{{ request.code }}</p>
                <h1 class="mt-2 text-xl font-bold text-ink">{{ request.title }}</h1>
                <div class="mt-3"><StatusBadge :status="request.status" /></div>

                <ol v-if="request.status.value !== 'rejected'" class="mt-8 flex items-center justify-center gap-2 text-xs">
                    <li v-for="(s, i) in flow" :key="s" class="flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full font-bold"
                            :class="flow.indexOf(request.status.value) >= i ? 'bg-brand-500 text-ink' : 'bg-stone-100 text-stone-400'">{{ i + 1 }}</span>
                        <span :class="flow.indexOf(request.status.value) >= i ? 'font-semibold text-ink' : 'text-stone-400'">{{ labels[s] }}</span>
                        <span v-if="i < flow.length - 1" class="h-px w-6 bg-stone-200" />
                    </li>
                </ol>

                <p v-if="request.status.value === 'new' || request.status.value === 'reviewing'" class="mt-6 text-sm text-stone-600">
                    Terima kasih, {{ request.name }}. Tim kami meninjau pengajuan Anda dalam 1–2 hari kerja dan akan menghubungi Anda.
                </p>
                <p v-else-if="request.status.value === 'accepted'" class="mt-6 rounded-xl bg-green-50 p-4 text-sm text-green-800">
                    🎉 Pengajuan diterima! Kami akan menghubungi Anda untuk jadwal serah terima ({{ request.handover.toLowerCase() }}), pemeriksaan, dan perjanjian titip.
                </p>
                <p v-else class="mt-6 rounded-xl bg-stone-100 p-4 text-sm text-stone-700">Mohon maaf, pengajuan belum dapat kami terima. Alasan: {{ request.reject_reason }}</p>

                <p class="mt-6 text-xs text-stone-400">Dikirim {{ dateTime(request.created_at) }}<template v-if="request.reviewed_at"> · ditinjau {{ dateTime(request.reviewed_at) }}</template></p>
                <p class="mt-2 text-xs text-stone-400">Simpan halaman ini — link yang sama juga dikirim ke email Anda.</p>
                <Link :href="route('consign.create')" class="btn-outline mt-6">Ajukan barang lain</Link>
            </div>
        </div>
    </PublicLayout>
</template>
