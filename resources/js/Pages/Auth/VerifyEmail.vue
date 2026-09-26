<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const form = useForm({});
</script>

<template>
    <Head title="Verifikasi Email" />
    <AuthLayout title="Verifikasi email Anda" subtitle="Satu langkah lagi sebelum Anda dapat menawar.">
        <div class="space-y-4 text-sm text-stone-600">
            <p>Kami telah mengirim tautan verifikasi ke <b>{{ $page.props.auth.user?.email }}</b>. Klik tautan di email tersebut untuk mengaktifkan akun.</p>
            <p v-if="$page.props.session.status === 'verification-link-sent'" class="rounded-xl bg-green-50 p-3 text-green-700">
                Tautan verifikasi baru sudah dikirim.
            </p>
            <button class="btn-primary w-full" :disabled="form.processing" @click="form.post(route('verification.send'))">Kirim ulang email verifikasi</button>
            <button class="w-full text-stone-500 hover:text-ink" @click="router.post(route('logout'))">Keluar</button>
        </div>
    </AuthLayout>
</template>
