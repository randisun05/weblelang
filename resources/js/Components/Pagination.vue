<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({ links: { type: Array, required: true } });
</script>

<template>
    <nav v-if="links.length > 3" class="mt-6 flex flex-wrap items-center justify-center gap-1" aria-label="Paginasi">
        <template v-for="(link, i) in links" :key="i">
            <span v-if="!link.url" class="rounded-lg px-3 py-1.5 text-sm text-stone-400" v-html="link.label" />
            <Link v-else :href="link.url" preserve-scroll class="rounded-lg px-3 py-1.5 text-sm"
                :class="link.active ? 'bg-ink font-semibold text-white' : 'text-stone-600 hover:bg-stone-200'" v-html="link.label" />
        </template>
    </nav>
</template>
