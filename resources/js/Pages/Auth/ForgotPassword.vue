<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Field from '@/Components/Field.vue';
import Turnstile from '@/Components/Turnstile.vue';

const form = useForm({ email: '', 'cf-turnstile-response': '' });
</script>

<template>
    <Head title="Lupa Kata Sandi" />
    <AuthLayout title="Lupa kata sandi" subtitle="Kami akan mengirim tautan untuk mengatur ulang kata sandi.">
        <div v-if="$page.props.session.status" class="mb-4 rounded-xl bg-green-50 p-3 text-sm text-green-700">{{ $page.props.session.status }}</div>
        <form class="space-y-4" @submit.prevent="form.post(route('password.email'))">
            <Field label="Email" :error="form.errors.email">
                <input v-model="form.email" type="email" class="input" required autofocus />
            </Field>
            <Turnstile v-model="form['cf-turnstile-response']" :error="form.errors.captcha" />
            <button class="btn-primary w-full" :disabled="form.processing">Kirim tautan reset</button>
        </form>
    </AuthLayout>
</template>
