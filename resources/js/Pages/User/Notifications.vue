<script setup>
import { Head, router } from '@inertiajs/vue3';
import AccountLayout from '@/Layouts/AccountLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { dateTime } from '@/lib/format';

defineProps({ notifications: Object });
const readAll = () => router.post(route('user.notifications.read-all'), {}, { preserveScroll: true });
</script>

<template>
    <Head title="Notifikasi" />
    <AccountLayout title="Notifikasi">
        <div v-if="notifications.data.length" class="card divide-y divide-stone-100">
            <div class="flex justify-end p-3">
                <button v-if="$page.props.auth.unread_notifications" class="link text-sm" @click="readAll">Tandai semua sudah dibaca</button>
            </div>
            <a v-for="n in notifications.data" :key="n.id" :href="route('user.notifications.open', n.id)"
                class="flex gap-4 p-4 transition hover:bg-stone-50" :class="n.read ? '' : 'bg-brand-50/60'">
                <span class="text-2xl">{{ n.data.icon }}</span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-2">
                        <span class="font-semibold text-ink">{{ n.data.title }}</span>
                        <span v-if="!n.read" class="h-2 w-2 rounded-full bg-brand-500" />
                    </span>
                    <span class="block text-sm text-stone-600">{{ n.data.body }}</span>
                    <span class="block text-xs text-stone-400">{{ dateTime(n.at) }}</span>
                </span>
            </a>
        </div>
        <EmptyState v-else title="Belum ada notifikasi" description="Kabar tentang penawaran, kemenangan, dan pembayaran akan muncul di sini." icon="🔔" />
        <Pagination :links="notifications.links" />
    </AccountLayout>
</template>
