<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Field from '@/Components/Field.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

defineProps({ users: Array, roles: Array });
const form = useForm({ name: '', email: '', role: 'staff', password: '' });
const submit = () => form.post(route('admin.users.store'), { preserveScroll: true, onSuccess: () => form.reset() });
const update = (u, changes) => router.put(route('admin.users.update', u.id), { role: u.role.value, is_blocked: u.is_blocked, ...changes }, { preserveScroll: true });
</script>

<template>
    <Head title="Pengguna" />
    <AdminLayout title="Akun Petugas">
        <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
            <section class="card overflow-x-auto">
                <table class="tbl">
                    <thead><tr><th>Nama</th><th>Peran</th><th>2FA</th><th>Status</th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        <tr v-for="u in users" :key="u.id">
                            <td><span class="font-medium text-ink">{{ u.name }}</span><br /><span class="text-xs text-stone-500">{{ u.email }}</span></td>
                            <td>
                                <select :value="u.role.value" class="input py-1.5" @change="update(u, { role: $event.target.value })">
                                    <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                                </select>
                            </td>
                            <td>{{ u.two_factor ? '✅' : '—' }}</td>
                            <td>
                                <button class="text-xs" :class="u.is_blocked ? 'text-green-700' : 'text-red-600'" @click="update(u, { is_blocked: !u.is_blocked })">
                                    {{ u.is_blocked ? 'Aktifkan' : 'Blokir' }}
                                </button>
                                <StatusBadge v-if="u.is_blocked" :status="{ label: 'Diblokir', color: 'red', value: 'blocked' }" class="ml-2" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
            <form class="card h-fit space-y-4 p-6" @submit.prevent="submit">
                <h2 class="font-semibold text-ink">Tambah petugas</h2>
                <Field label="Nama" :error="form.errors.name"><input v-model="form.name" class="input" /></Field>
                <Field label="Email" :error="form.errors.email"><input v-model="form.email" type="email" class="input" /></Field>
                <Field label="Peran" :error="form.errors.role">
                    <select v-model="form.role" class="input"><option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option></select>
                </Field>
                <Field label="Kata sandi awal" :error="form.errors.password"><input v-model="form.password" type="password" class="input" autocomplete="new-password" /></Field>
                <p class="text-xs text-stone-500">Admin & super admin wajib mengaktifkan 2FA saat login pertama.</p>
                <button class="btn-primary" :disabled="form.processing">Buat akun</button>
            </form>
        </div>
    </AdminLayout>
</template>
