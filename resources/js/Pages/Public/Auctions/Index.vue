<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Countdown from '@/Components/Countdown.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { dateTime, money } from '@/lib/format';

defineProps({ auctions: Object });
</script>

<template>
    <Head title="Jadwal Lelang" />
    <PublicLayout>
        <div class="mx-auto max-w-5xl px-4 py-10">
            <h1 class="font-display text-3xl font-extrabold text-ink">Jadwal Lelang</h1>
            <p class="mt-1 text-stone-500">Sesi yang sedang berlangsung, akan datang, dan yang telah selesai.</p>

            <div v-if="auctions.data.length" class="mt-8 space-y-4">
                <Link v-for="a in auctions.data" :key="a.slug" :href="route('auctions.show', a.slug)"
                    class="card flex flex-col gap-4 p-5 transition hover:shadow-md md:flex-row md:items-center">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <StatusBadge :status="a.status" />
                            <StatusBadge :status="a.method" />
                            <span class="text-xs text-stone-400">{{ a.code }}</span>
                        </div>
                        <h2 class="mt-2 text-lg font-semibold text-ink">{{ a.title }}</h2>
                        <p class="mt-1 line-clamp-2 text-sm text-stone-500">{{ a.description }}</p>
                        <p class="mt-2 text-sm text-stone-600">
                            {{ a.lots_count }} lot · {{ dateTime(a.starts_at) }} – {{ dateTime(a.ends_at) }}
                            <span v-if="a.deposit_amount" class="ml-2 rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-800">Jaminan {{ money(a.deposit_amount) }}</span>
                        </p>
                    </div>
                    <div class="shrink-0">
                        <Countdown v-if="a.status.value === 'live'" :to="a.ends_at" label="Berakhir dalam" />
                        <Countdown v-else-if="a.status.value === 'published'" :to="a.starts_at" label="Dimulai dalam" />
                        <span v-else class="text-sm text-stone-500">Sesi selesai</span>
                    </div>
                </Link>
            </div>
            <EmptyState v-else class="mt-8" title="Belum ada jadwal lelang" description="Nantikan sesi lelang berikutnya." icon="📅" />
            <Pagination :links="auctions.links" />
        </div>
    </PublicLayout>
</template>
