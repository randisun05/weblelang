<script setup>
defineProps({
    src: { type: String, default: null },
    alt: { type: String, default: '' },
    icon: { type: String, default: '🏷️' },
});
</script>

<template>
    <img v-if="src" :src="src" :alt="alt" loading="lazy" class="h-full w-full object-cover" />
    <div v-else class="flex h-full w-full items-center justify-center bg-gradient-to-br from-brand-100 via-stone-100 to-stone-200 text-5xl">
        <span aria-hidden="true">{{ icon }}</span>
    </div>
</template>
