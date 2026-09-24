<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    label: String,
    value: [String, Number],
    hint: { type: String, default: '' },
    href: { type: String, default: null },
    tone: { type: String, default: 'stone' },
});
const tones = {
    stone: 'border-stone-200',
    amber: 'border-amber-300 bg-amber-50/50',
    red: 'border-red-200 bg-red-50/50',
    green: 'border-green-200 bg-green-50/50',
};
</script>

<template>
    <component :is="href ? Link : 'div'" :href="href" class="card block p-5 transition" :class="[tones[tone], href ? 'hover:shadow-md' : '']">
        <p class="text-sm font-medium text-stone-500">{{ label }}</p>
        <p class="mt-1 text-2xl font-bold text-ink">{{ value }}</p>
        <p v-if="hint" class="mt-1 text-xs text-stone-500">{{ hint }}</p>
    </component>
</template>
