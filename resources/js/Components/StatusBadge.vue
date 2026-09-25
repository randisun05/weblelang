<script setup>
import { computed } from 'vue';

const props = defineProps({ status: { type: Object, required: true } });

// Kelas ditulis lengkap supaya terdeteksi Tailwind.
const colors = {
    gray: 'bg-stone-100 text-stone-700 ring-stone-200',
    slate: 'bg-slate-100 text-slate-700 ring-slate-200',
    amber: 'bg-amber-50 text-amber-800 ring-amber-200',
    green: 'bg-green-50 text-green-700 ring-green-200',
    emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    red: 'bg-red-50 text-red-700 ring-red-200',
    orange: 'bg-orange-50 text-orange-700 ring-orange-200',
    sky: 'bg-sky-50 text-sky-700 ring-sky-200',
    indigo: 'bg-indigo-50 text-indigo-700 ring-indigo-200',
    purple: 'bg-purple-50 text-purple-700 ring-purple-200',
};
const cls = computed(() => colors[props.status.color] ?? colors.gray);
</script>

<template>
    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset" :class="cls">
        <span v-if="status.value === 'live'" class="animate-live h-1.5 w-1.5 rounded-full bg-red-500"></span>
        {{ status.label }}
    </span>
</template>
