<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Field from '@/Components/Field.vue';

const recovery = ref(false);
const form = useForm({ code: '', recovery_code: '' });
</script>

<template>
    <Head title="Verifikasi 2FA" />
    <AuthLayout title="Verifikasi dua langkah" :subtitle="recovery ? 'Masukkan salah satu kode pemulihan Anda.' : 'Masukkan 6 digit kode dari aplikasi authenticator.'">
        <form class="space-y-4" @submit.prevent="form.post(route('two-factor.login'))">
            <Field v-if="!recovery" label="Kode autentikasi" :error="form.errors.code">
                <input v-model="form.code" inputmode="numeric" autocomplete="one-time-code" class="input text-center font-mono text-xl tracking-[.5em]" maxlength="6" autofocus />
            </Field>
            <Field v-else label="Kode pemulihan" :error="form.errors.recovery_code">
                <input v-model="form.recovery_code" class="input font-mono" autocomplete="one-time-code" />
            </Field>
            <button class="btn-primary w-full" :disabled="form.processing">Verifikasi</button>
            <button type="button" class="w-full text-sm text-stone-500 hover:text-ink" @click="recovery = !recovery">
                {{ recovery ? 'Gunakan kode authenticator' : 'Gunakan kode pemulihan' }}
            </button>
        </form>
    </AuthLayout>
</template>
