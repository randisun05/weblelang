<script setup>
import { usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

// Widget Cloudflare Turnstile. Tidak dirender bila situs belum mengonfigurasi kunci.
const token = defineModel({ type: String, default: '' });
defineProps({ error: { type: String, default: '' } });

const siteKey = usePage().props.app.turnstile_site_key;
const el = ref(null);
let widgetId = null;

const load = () =>
    new Promise((resolve) => {
        if (window.turnstile) return resolve(window.turnstile);
        const s = document.createElement('script');
        s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        s.async = true;
        s.onload = () => resolve(window.turnstile);
        document.head.appendChild(s);
    });

onMounted(async () => {
    if (!siteKey) return;
    const turnstile = await load();
    widgetId = turnstile.render(el.value, {
        sitekey: siteKey,
        language: 'id',
        callback: (t) => (token.value = t),
        'expired-callback': () => (token.value = ''),
    });
});
onBeforeUnmount(() => widgetId !== null && window.turnstile?.remove(widgetId));

// Token hanya sekali pakai: reset setelah submit gagal.
defineExpose({ reset: () => widgetId !== null && window.turnstile?.reset(widgetId) });
</script>

<template>
    <div v-if="siteKey">
        <div ref="el" />
        <p v-if="error" class="mt-1 text-xs font-medium text-red-600">{{ error }}</p>
    </div>
</template>
