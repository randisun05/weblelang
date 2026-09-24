<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Field from '@/Components/Field.vue';

const form = useForm({ password: '' });
</script>

<template>
    <Head title="Konfirmasi Kata Sandi" />
    <AuthLayout title="Konfirmasi kata sandi" subtitle="Demi keamanan, masukkan kembali kata sandi Anda untuk melanjutkan.">
        <form class="space-y-4" @submit.prevent="form.post(route('password.confirm'), { onFinish: () => form.reset() })">
            <Field label="Kata sandi" :error="form.errors.password">
                <input v-model="form.password" type="password" class="input" autocomplete="current-password" required autofocus />
            </Field>
            <button class="btn-primary w-full" :disabled="form.processing">Konfirmasi</button>
        </form>
    </AuthLayout>
</template>
