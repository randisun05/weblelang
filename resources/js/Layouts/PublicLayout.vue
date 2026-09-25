<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Swal from 'sweetalert2';
import FlashMessages from '@/Components/FlashMessages.vue';
import TermsConsent from '@/Components/TermsConsent.vue';
import { onUserNotification } from '@/lib/realtime';

const page = usePage();
const user = computed(() => page.props.auth.user);
const open = ref(false);

// Notifikasi real-time (mis. "Anda terlampaui"): tampilkan toast & perbarui lonceng.
let stopNotifications;
onMounted(() => {
    if (!user.value) return;
    stopNotifications = onUserNotification(user.value.id, (n) => {
        page.props.auth.unread_notifications = (page.props.auth.unread_notifications || 0) + 1;
        Swal.fire({
            toast: true, position: 'top-end', timer: 6000, showConfirmButton: false, icon: 'info',
            title: `${n.icon ?? '🔔'} ${n.title ?? 'Notifikasi baru'}`, text: n.body ?? '',
        });
    });
});
onBeforeUnmount(() => stopNotifications?.());

const nav = [
    { label: 'Beranda', route: 'home' },
    { label: 'Jadwal Lelang', route: 'auctions.index' },
    { label: 'Titip Barang', route: 'consign.create' },
    { label: 'Cara Kerja', route: 'how-it-works' },
];

const logout = () => router.post(route('logout'));
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <FlashMessages />
        <TermsConsent />
        <header class="sticky top-0 z-30 border-b border-stone-200/80 bg-white/90 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4">
                <Link :href="route('home')" class="flex items-center gap-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-ink text-lg">🔨</span>
                    <span class="font-display text-xl font-extrabold text-ink">Web<span class="text-brand-600">Lelang</span></span>
                </Link>

                <nav class="hidden items-center gap-1 md:flex">
                    <Link v-for="item in nav" :key="item.route" :href="route(item.route)"
                        class="rounded-lg px-3 py-2 text-sm font-medium"
                        :class="route().current(item.route) ? 'bg-stone-100 text-ink' : 'text-stone-600 hover:text-ink'">
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="hidden items-center gap-2 md:flex">
                    <template v-if="user">
                        <Link v-if="user.is_backoffice" :href="route('admin.dashboard')" class="btn-dark btn-sm">Panel Admin</Link>
                        <template v-else>
                            <Link :href="route('user.notifications.index')" class="relative rounded-full p-2 text-lg hover:bg-stone-100" title="Notifikasi">
                                🔔
                                <span v-if="$page.props.auth.unread_notifications" class="absolute -top-0.5 -right-0.5 min-w-5 rounded-full bg-red-600 px-1 text-center text-[10px] font-bold text-white">
                                    {{ $page.props.auth.unread_notifications > 9 ? '9+' : $page.props.auth.unread_notifications }}
                                </span>
                            </Link>
                            <Link :href="route('user.dashboard')" class="btn-outline btn-sm">Akun Saya</Link>
                        </template>
                        <button class="btn-sm btn text-stone-500 hover:text-ink" @click="logout">Keluar</button>
                    </template>
                    <template v-else>
                        <Link :href="route('login')" class="btn-outline btn-sm">Masuk</Link>
                        <Link :href="route('register')" class="btn-primary btn-sm">Daftar Peserta</Link>
                    </template>
                </div>

                <button class="rounded-lg p-2 md:hidden" aria-label="Menu" @click="open = !open">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
            </div>
            <div v-if="open" class="border-t border-stone-200 bg-white px-4 py-3 md:hidden">
                <Link v-for="item in nav" :key="item.route" :href="route(item.route)" class="block rounded-lg px-3 py-2 text-sm">{{ item.label }}</Link>
                <div class="mt-2 flex gap-2 border-t border-stone-100 pt-3">
                    <template v-if="user">
                        <Link :href="user.is_backoffice ? route('admin.dashboard') : route('user.dashboard')" class="btn-outline btn-sm flex-1">
                            {{ user.is_backoffice ? 'Panel Admin' : 'Akun Saya' }}
                        </Link>
                        <button class="btn-outline btn-sm" @click="logout">Keluar</button>
                    </template>
                    <template v-else>
                        <Link :href="route('login')" class="btn-outline btn-sm flex-1">Masuk</Link>
                        <Link :href="route('register')" class="btn-primary btn-sm flex-1">Daftar</Link>
                    </template>
                </div>
            </div>
        </header>

        <main class="flex-1">
            <slot />
        </main>

        <footer class="mt-16 bg-ink text-stone-300">
            <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 md:grid-cols-3">
                <div>
                    <p class="font-display text-xl font-extrabold text-white">Web<span class="text-brand-400">Lelang</span></p>
                    <p class="mt-2 text-sm text-stone-400">Lelang online barang titipan yang aman, transparan, dan mudah diikuti dari mana saja.</p>
                </div>
                <div class="text-sm">
                    <p class="mb-2 font-semibold text-white">Jelajahi</p>
                    <Link :href="route('auctions.index')" class="block py-1 hover:text-white">Jadwal lelang</Link>
                    <Link :href="route('how-it-works')" class="block py-1 hover:text-white">Cara ikut lelang</Link>
                    <Link :href="route('consign.create')" class="block py-1 hover:text-white">Titip barang untuk dilelang</Link>
                    <Link :href="route('legal.terms')" class="block py-1 hover:text-white">Syarat & Ketentuan</Link>
                    <Link :href="route('legal.privacy')" class="block py-1 hover:text-white">Kebijakan Privasi</Link>
                </div>
                <div class="text-sm">
                    <p class="mb-2 font-semibold text-white">Keamanan</p>
                    <p class="text-stone-400">Peserta terverifikasi KTP · Riwayat penawaran tidak dapat diubah · Pembayaran aman via payment gateway</p>
                </div>
            </div>
            <div class="border-t border-white/10 py-4 text-center text-xs text-stone-500">© {{ new Date().getFullYear() }} {{ $page.props.app.name }}</div>
        </footer>
    </div>
</template>
