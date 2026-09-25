<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Field from '@/Components/Field.vue';

const props = defineProps({ enabled: Boolean, confirmed: Boolean, mustEnable: Boolean });

const qr = ref(null);
const secret = ref(null);
const codes = ref([]);
const confirm = useForm({ code: '' });

const loadSetup = async () => {
    const [{ data: q }, { data: s }] = await Promise.all([axios.get(route('two-factor.qr-code')), axios.get(route('two-factor.secret-key'))]);
    qr.value = q.svg;
    secret.value = s.secretKey;
};
const loadCodes = async () => (codes.value = (await axios.get(route('two-factor.recovery-codes'))).data);

onMounted(() => {
    if (props.enabled && !props.confirmed) loadSetup().catch(() => {});
    if (props.confirmed) loadCodes().catch(() => {});
});

const enable = () => router.post(route('two-factor.enable'), {}, { preserveScroll: true, onSuccess: () => loadSetup() });
const confirmCode = () => confirm.post(route('two-factor.confirm'), { errorBag: 'confirmTwoFactorAuthentication', preserveScroll: true, onSuccess: () => loadCodes() });
const regenerate = () => router.post(route('two-factor.regenerate-recovery-codes'), {}, { preserveScroll: true, onSuccess: () => loadCodes() });
const disable = () => router.delete(route('two-factor.disable'), { preserveScroll: true });
</script>

<template>
    <Head title="Keamanan 2FA" />
    <AdminLayout title="Autentikasi Dua Faktor (2FA)">
        <div class="card max-w-2xl p-6">
            <p v-if="mustEnable && !confirmed" class="mb-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">
                Peran Anda wajib memakai 2FA. Panel admin terkunci sampai 2FA aktif.
            </p>

            <template v-if="!enabled">
                <p class="text-stone-600">Lindungi akun dengan kode dari aplikasi authenticator (Google Authenticator, Authy, dll.).</p>
                <button class="btn-primary mt-4" @click="enable">Aktifkan 2FA</button>
            </template>

            <template v-else-if="!confirmed">
                <p class="text-stone-600">1. Pindai QR berikut dengan aplikasi authenticator.</p>
                <div v-if="qr" class="mt-4 inline-block rounded-xl bg-white p-3 ring-1 ring-stone-200" v-html="qr" />
                <p v-if="secret" class="mt-2 text-xs text-stone-500">Atau masukkan kunci: <code class="font-mono">{{ secret }}</code></p>
                <form class="mt-6 max-w-xs space-y-3" @submit.prevent="confirmCode">
                    <Field label="2. Masukkan kode 6 digit" :error="$page.props.errors.confirmTwoFactorAuthentication?.code">
                        <input v-model="confirm.code" inputmode="numeric" maxlength="6" class="input text-center font-mono tracking-[.4em]" />
                    </Field>
                    <button class="btn-primary" :disabled="confirm.processing">Konfirmasi</button>
                </form>
            </template>

            <template v-else>
                <p class="rounded-xl bg-green-50 p-4 text-sm text-green-800">✓ 2FA aktif untuk akun Anda.</p>
                <h3 class="mt-6 font-semibold text-ink">Kode pemulihan</h3>
                <p class="text-sm text-stone-500">Simpan di tempat aman. Setiap kode hanya bisa dipakai sekali.</p>
                <div class="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-stone-50 p-4 font-mono text-sm">
                    <span v-for="c in codes" :key="c">{{ c }}</span>
                </div>
                <div class="mt-4 flex gap-2">
                    <button class="btn-outline btn-sm" @click="regenerate">Buat ulang kode</button>
                    <button v-if="!mustEnable" class="btn-danger btn-sm" @click="disable">Nonaktifkan 2FA</button>
                </div>
            </template>
        </div>
    </AdminLayout>
</template>
