<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { dateTime } from '@/lib/format';

const props = defineProps({ logs: Object, filters: Object });
const action = ref(props.filters.action ?? '');
let t;
watch(action, (v) => { clearTimeout(t); t = setTimeout(() => router.get(route('admin.audit-logs.index'), { action: v }, { preserveState: true, replace: true }), 300); });
</script>

<template>
    <Head title="Log Audit" />
    <AdminLayout title="Log Audit">
        <input v-model="action" class="input mb-4 max-w-xs" placeholder="Filter aksi, mis. invoice, kyc, lot…" />
        <div class="card overflow-x-auto">
            <table class="tbl">
                <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Objek</th><th>Detail</th><th>IP</th></tr></thead>
                <tbody class="divide-y divide-stone-100">
                    <tr v-for="log in logs.data" :key="log.id">
                        <td class="text-xs whitespace-nowrap">{{ dateTime(log.at) }}</td>
                        <td>{{ log.user }}</td>
                        <td><code class="rounded bg-stone-100 px-1.5 py-0.5 text-xs">{{ log.action }}</code></td>
                        <td class="text-xs">{{ log.subject }}</td>
                        <td class="max-w-xs truncate font-mono text-xs text-stone-500">{{ log.properties ? JSON.stringify(log.properties) : '' }}</td>
                        <td class="font-mono text-xs">{{ log.ip }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="logs.links" />
    </AdminLayout>
</template>
