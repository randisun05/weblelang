<script setup>
import { computed, watch } from 'vue';
import { useClock } from '@/lib/clock';

const props = defineProps({
    to: { type: String, required: true },
    compact: { type: Boolean, default: false },
    label: { type: String, default: '' },
});
const emit = defineEmits(['finished']);

const { now, serverNow } = useClock();

const remaining = computed(() => {
    now.value; // reaktif tiap detik
    return Math.max(0, new Date(props.to).getTime() - serverNow());
});

watch(remaining, (value, old) => {
    if (value === 0 && old > 0) emit('finished');
});

const parts = computed(() => {
    const s = Math.floor(remaining.value / 1000);
    return { d: Math.floor(s / 86400), h: Math.floor((s % 86400) / 3600), m: Math.floor((s % 3600) / 60), s: s % 60 };
});

const urgent = computed(() => remaining.value > 0 && remaining.value < 5 * 60 * 1000);
const pad = (n) => String(n).padStart(2, '0');
const text = computed(() => {
    const p = parts.value;
    if (remaining.value === 0) return 'Selesai';
    if (p.d > 0) return `${p.d}h ${pad(p.h)}j ${pad(p.m)}m`;
    return `${pad(p.h)}:${pad(p.m)}:${pad(p.s)}`;
});
</script>

<template>
    <span v-if="compact" class="font-mono tabular-nums" :class="urgent ? 'font-bold text-red-600' : ''">{{ text }}</span>
    <div v-else>
        <p v-if="label" class="mb-1 text-xs font-medium tracking-wide text-stone-500 uppercase">{{ label }}</p>
        <div class="flex gap-2" :class="urgent ? 'text-red-600' : 'text-ink'">
            <template v-if="remaining > 0">
                <div v-for="(v, k) in (parts.d ? { Hari: parts.d, Jam: parts.h, Menit: parts.m, Detik: parts.s } : { Jam: parts.h, Menit: parts.m, Detik: parts.s })" :key="k"
                    class="min-w-14 rounded-xl px-2 py-1.5 text-center" :class="urgent ? 'bg-red-50' : 'bg-stone-100'">
                    <div class="font-mono text-2xl font-bold tabular-nums">{{ pad(v) }}</div>
                    <div class="text-[10px] tracking-wide uppercase opacity-70">{{ k }}</div>
                </div>
            </template>
            <div v-else class="rounded-xl bg-stone-100 px-4 py-2 font-semibold text-stone-600">Lelang selesai</div>
        </div>
    </div>
</template>
