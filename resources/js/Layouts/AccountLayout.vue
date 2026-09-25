<script setup>
import { Link } from '@inertiajs/vue3';
import PublicLayout from './PublicLayout.vue';

defineProps({ title: String });
const tabs = [
    { label: 'Ringkasan', route: 'user.dashboard' },
    { label: 'Invoice', route: 'user.invoices.*', href: 'user.invoices.index' },
    { label: 'Notifikasi', route: 'user.notifications.*', href: 'user.notifications.index' },
    { label: 'Profil & KYC', route: 'user.profile' },
];
</script>

<template>
    <PublicLayout>
        <div class="mx-auto max-w-6xl px-4 py-8">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-sm text-stone-500">Akun Peserta</p>
                    <h1 class="text-2xl font-bold text-ink">{{ title }}</h1>
                </div>
                <nav class="flex gap-1 rounded-xl bg-stone-100 p-1">
                    <Link v-for="tab in tabs" :key="tab.label" :href="route(tab.href ?? tab.route)"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium"
                        :class="route().current(tab.route) ? 'bg-white text-ink shadow-sm' : 'text-stone-600 hover:text-ink'">
                        {{ tab.label }}
                    </Link>
                </nav>
            </div>
            <slot />
        </div>
    </PublicLayout>
</template>
