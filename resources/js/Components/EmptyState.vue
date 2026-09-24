<script setup>
defineProps({ title: String, description: { type: String, default: '' }, icon: { type: String, default: '📭' } });
</script>

<template>
    <div class="rounded-2xl border border-dashed border-stone-300 bg-white/60 px-6 py-12 text-center">
        <div class="text-4xl">{{ icon }}</div>
        <p class="mt-3 font-semibold text-stone-700">{{ title }}</p>
        <p v-if="description" class="mt-1 text-sm text-stone-500">{{ description }}</p>
        <div class="mt-4"><slot /></div>
    </div>
</template>
