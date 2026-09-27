<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FlashMessages from '@/Components/FlashMessages.vue';

defineProps({ title: { type: String, default: '' } });

const page = usePage();
const user = computed(() => page.props.auth.user);
const open = ref(false);
const isAdmin = computed(() => ['super_admin', 'admin'].includes(user.value?.role));

const groups = computed(() => [
    { title: null, items: [{ label: 'Dashboard', icon: '📊', route: 'admin.dashboard' }] },
    {
        title: 'Barang Titipan',
        items: [
            { label: 'Pengajuan Titip', icon: '📮', route: 'admin.consign-requests.*', href: 'admin.consign-requests.index' },
            { label: 'Penitip', icon: '🤝', route: 'admin.consignors.*', href: 'admin.consignors.index' },
            { label: 'Barang', icon: '📦', route: 'admin.items.*', href: 'admin.items.index' },
            isAdmin.value && { label: 'Kategori', icon: '🏷️', route: 'admin.categories.*', href: 'admin.categories.index' },
        ],
    },
    isAdmin.value && {
        title: 'Lelang',
        items: [
            { label: 'Sesi Lelang', icon: '🔨', route: 'admin.auctions.*', href: 'admin.auctions.index' },
            { label: 'Peserta & KYC', icon: '🪪', route: 'admin.bidders.*', href: 'admin.bidders.index' },
            { label: 'Kecurigaan', icon: '🚩', route: 'admin.fraud.*', href: 'admin.fraud.index' },
        ],
    },
    isAdmin.value && {
        title: 'Keuangan',
        items: [
            { label: 'Invoice', icon: '🧾', route: 'admin.invoices.*', href: 'admin.invoices.index' },
            { label: 'Settlement Penitip', icon: '💸', route: 'admin.settlements.*', href: 'admin.settlements.index' },
            { label: 'Transaksi Gateway', icon: '💳', route: 'admin.transactions.*', href: 'admin.transactions.index' },
            { label: 'Laporan & Ekspor', icon: '📈', route: 'admin.reports.*', href: 'admin.reports.index' },
        ],
    },
    {
        title: 'Sistem',
        items: [
            isAdmin.value && { label: 'Log Audit', icon: '🛡️', route: 'admin.audit-logs.*', href: 'admin.audit-logs.index' },
            user.value?.role === 'super_admin' && { label: 'Pengguna', icon: '👥', route: 'admin.users.*', href: 'admin.users.index' },
            { label: 'Keamanan 2FA', icon: '🔐', route: 'admin.security.*', href: 'admin.security.two-factor' },
        ],
    },
].filter(Boolean).map((g) => ({ ...g, items: g.items.filter(Boolean) })));
</script>

<template>
    <div class="min-h-screen bg-stone-100">
        <FlashMessages />
        <aside class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full overflow-y-auto bg-ink text-stone-300 transition lg:translate-x-0"
            :class="{ 'translate-x-0': open }">
            <Link :href="route('admin.dashboard')" class="flex h-16 items-center gap-2 px-5">
                <span class="text-xl">🔨</span>
                <span class="font-display text-lg font-extrabold text-white">Web<span class="text-brand-400">Lelang</span></span>
                <span class="ml-auto rounded bg-white/10 px-1.5 py-0.5 text-[10px] tracking-wide uppercase">Admin</span>
            </Link>
            <nav class="space-y-5 px-3 pb-8">
                <div v-for="(group, gi) in groups" :key="gi">
                    <p v-if="group.title" class="mb-1 px-3 text-[11px] font-semibold tracking-wider text-stone-500 uppercase">{{ group.title }}</p>
                    <Link v-for="item in group.items" :key="item.label" :href="route(item.href ?? item.route)"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm"
                        :class="route().current(item.route) ? 'bg-white/10 font-semibold text-white' : 'hover:bg-white/5 hover:text-white'"
                        @click="open = false">
                        <span class="w-5 text-center">{{ item.icon }}</span>{{ item.label }}
                    </Link>
                </div>
            </nav>
        </aside>
        <div v-if="open" class="fixed inset-0 z-30 bg-black/40 lg:hidden" @click="open = false" />

        <div class="lg:pl-64">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-stone-200 bg-white/90 px-4 backdrop-blur lg:px-8">
                <button class="rounded-lg p-2 lg:hidden" aria-label="Menu" @click="open = true">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
                <h1 class="truncate text-lg font-semibold text-ink">{{ title }}</h1>
                <div class="ml-auto flex items-center gap-3 text-sm">
                    <Link :href="route('home')" class="hidden text-stone-500 hover:text-ink sm:inline">Lihat website ↗</Link>
                    <span class="hidden text-right sm:block">
                        <span class="block font-medium text-ink">{{ user?.name }}</span>
                        <span class="block text-xs text-stone-500">{{ user?.role_label }}</span>
                    </span>
                    <button class="btn-outline btn-sm" @click="router.post(route('logout'))">Keluar</button>
                </div>
            </header>
            <main class="p-4 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
