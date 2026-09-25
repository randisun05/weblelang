<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Field from '@/Components/Field.vue';

const props = defineProps({ token: String, email: String });
const form = useForm({ token: props.token, email: props.email ?? '', password: '', password_confirmation: '' });
</script>

<template>
    <Head title="Atur Ulang Kata Sandi" />
    <AuthLayout title="Atur ulang kata sandi">
        <form class="space-y-4" @submit.prevent="form.post(route('password.update'))">
            <Field label="Email" :error="form.errors.email">
                <input v-model="form.email" type="email" class="input" required />
            </Field>
            <Field label="Kata sandi baru" :error="form.errors.password">
                <input v-model="form.password" type="password" class="input" autocomplete="new-password" required />
            </Field>
            <Field label="Ulangi kata sandi">
                <input v-model="form.password_confirmation" type="password" class="input" autocomplete="new-password" required />
            </Field>
            <button class="btn-primary w-full" :disabled="form.processing">Simpan kata sandi</button>
        </form>
    </AuthLayout>
</template>
