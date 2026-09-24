<script setup>
import { Link } from '@inertiajs/vue3';
import Countdown from './Countdown.vue';
import StatusBadge from './StatusBadge.vue';
import LotImage from './LotImage.vue';
import { money } from '@/lib/format';

defineProps({ lot: { type: Object, required: true } });
</script>

<template>
    <Link :href="route('lots.show', lot.id)" class="group card flex flex-col overflow-hidden transition hover:-translate-y-0.5 hover:shadow-lg">
        <div class="relative aspect-[4/3] overflow-hidden bg-stone-100">
            <LotImage :src="lot.image" :alt="lot.title" class="transition duration-500 group-hover:scale-105" />
            <div class="absolute top-3 left-3 flex gap-2">
                <StatusBadge :status="lot.status" />
            </div>
            <span class="absolute top-3 right-3 rounded-full bg-ink/80 px-2.5 py-0.5 text-xs font-semibold text-white">Lot {{ lot.lot_number }}</span>
            <span v-if="lot.is_leader !== undefined" class="absolute bottom-3 left-3 rounded-full px-2.5 py-0.5 text-xs font-bold"
                :class="lot.is_leader ? 'bg-green-600 text-white' : 'bg-red-600 text-white'">
                {{ lot.is_leader ? 'Anda memimpin' : 'Anda terlampaui' }}
            </span>
        </div>
        <div class="flex flex-1 flex-col gap-3 p-4">
            <div>
                <p class="text-xs font-medium text-brand-700">{{ lot.category }}</p>
                <h3 class="line-clamp-2 font-semibold text-ink group-hover:text-brand-700">{{ lot.title }}</h3>
            </div>
            <div class="mt-auto flex items-end justify-between gap-2">
                <div>
                    <p class="text-xs text-stone-500">{{ lot.bids_count ? `${lot.bids_count} penawaran` : 'Harga awal' }}</p>
                    <p class="text-lg font-bold text-ink">{{ money(lot.current_price) }}</p>
                </div>
                <div class="text-right text-xs text-stone-500">
                    <template v-if="lot.status.value === 'live'">
                        <p>Berakhir</p>
                        <Countdown :to="lot.ends_at" compact class="text-sm text-ink" />
                    </template>
                    <template v-else-if="lot.status.value === 'scheduled'">
                        <p>Mulai</p>
                        <Countdown :to="lot.starts_at" compact class="text-sm text-ink" />
                    </template>
                </div>
            </div>
        </div>
    </Link>
</template>
