<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Field from '@/Components/Field.vue';
import Turnstile from '@/Components/Turnstile.vue';
import { ref } from 'vue';

const form = useForm({ email: '', password: '', remember: false, 'cf-turnstile-response': '' });
const captcha = ref(null);
const submit = () => form.post(route('login'), { onFinish: () => { form.reset('password'); captcha.value?.reset(); } });
</script>

<template>
    <Head title="Masuk" />
    <AuthLayout title="Masuk ke akun Anda" subtitle="Ikuti lelang, pantau penawaran, dan bayar invoice.">
        <form class="space-y-4" @submit.prevent="submit">
            <Field label="Email" :error="form.errors.email">
                <input v-model="form.email" type="email" class="input" autocomplete="username" required autofocus />
            </Field>
            <Field label="Kata sandi" :error="form.errors.password">
                <input v-model="form.password" type="password" class="input" autocomplete="current-password" required />
            </Field>
            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center gap-2"><input v-model="form.remember" type="checkbox" class="rounded" /> Ingat saya</label>
                <Link :href="route('password.request')" class="link">Lupa kata sandi?</Link>
            </div>
            <Turnstile ref="captcha" v-model="form['cf-turnstile-response']" :error="form.errors.captcha" />
            <button class="btn-primary w-full" :disabled="form.processing">Masuk</button>
            <p class="text-center text-sm text-stone-500">Belum punya akun? <Link :href="route('register')" class="link">Daftar sebagai peserta</Link></p>
        </form>
    </AuthLayout>
</template>
