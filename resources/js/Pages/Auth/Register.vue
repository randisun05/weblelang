<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Field from '@/Components/Field.vue';

const form = useForm({ name: '', email: '', phone: '', password: '', password_confirmation: '', terms: false });
const submit = () => form.post(route('register'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <Head title="Daftar Peserta" />
    <AuthLayout title="Daftar sebagai peserta lelang" subtitle="Gratis. Setelah daftar, lengkapi verifikasi KTP agar bisa menawar.">
        <form class="space-y-4" @submit.prevent="submit">
            <Field label="Nama lengkap (sesuai KTP)" :error="form.errors.name" required>
                <input v-model="form.name" class="input" autocomplete="name" required />
            </Field>
            <Field label="Email" :error="form.errors.email" required>
                <input v-model="form.email" type="email" class="input" autocomplete="email" required />
            </Field>
            <Field label="Nomor HP / WhatsApp" :error="form.errors.phone" required>
                <input v-model="form.phone" type="tel" class="input" placeholder="081234567890" required />
            </Field>
            <div class="grid gap-4 sm:grid-cols-2">
                <Field label="Kata sandi" :error="form.errors.password" required>
                    <input v-model="form.password" type="password" class="input" autocomplete="new-password" required />
                </Field>
                <Field label="Ulangi kata sandi" required>
                    <input v-model="form.password_confirmation" type="password" class="input" autocomplete="new-password" required />
                </Field>
            </div>
            <Field :error="form.errors.terms">
                <label class="flex items-start gap-2 text-sm text-stone-600">
                    <input v-model="form.terms" type="checkbox" class="mt-0.5 rounded" />
                    <span>Saya menyetujui <Link :href="route('how-it-works')" class="link" target="_blank">syarat & ketentuan lelang</Link>, termasuk kewajiban membayar bila menang.</span>
                </label>
            </Field>
            <button class="btn-primary w-full" :disabled="form.processing">Buat akun</button>
            <p class="text-center text-sm text-stone-500">Sudah punya akun? <Link :href="route('login')" class="link">Masuk</Link></p>
        </form>
    </AuthLayout>
</template>
