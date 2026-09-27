<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// Muncul bila versi S&K/Kebijakan Privasi berubah sejak pengguna terakhir menyetujui.
const page = usePage();
const show = computed(() => page.props.auth.user?.needs_terms);
const form = useForm({ accept: false });
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 sm:items-center">
        <form class="card w-full max-w-lg p-6" @submit.prevent="form.post(route('legal.accept'), { preserveScroll: true })">
            <h2 class="text-lg font-bold text-ink">Pembaruan Syarat & Ketentuan</h2>
            <p class="mt-2 text-sm text-stone-600">
                Kami memperbarui <Link :href="route('legal.terms')" class="link" target="_blank">Syarat & Ketentuan</Link> dan
                <Link :href="route('legal.privacy')" class="link" target="_blank">Kebijakan Privasi</Link>.
                Mohon baca dan setujui untuk melanjutkan.
            </p>
            <label class="mt-4 flex items-start gap-2 text-sm text-stone-700">
                <input v-model="form.accept" type="checkbox" class="mt-0.5 rounded" />
                Saya telah membaca dan menyetujui Syarat & Ketentuan serta Kebijakan Privasi terbaru.
            </label>
            <p v-if="form.errors.accept" class="mt-1 text-xs text-red-600">{{ form.errors.accept }}</p>
            <button class="btn-primary mt-4 w-full" :disabled="!form.accept || form.processing">Setuju & lanjutkan</button>
        </form>
    </div>
</template>
