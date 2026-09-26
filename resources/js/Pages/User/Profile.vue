<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AccountLayout from '@/Layouts/AccountLayout.vue';
import Field from '@/Components/Field.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({ profile: Object, banks: Object });

const form = useForm({
    name: props.profile.name, phone: props.profile.phone ?? '', address: props.profile.address ?? '',
    whatsapp_notifications: props.profile.whatsapp_notifications ?? true,
});
const kyc = useForm({ nik: '', address: props.profile.address ?? '', ktp: null });
const bank = useForm({ bank_name: props.profile.bank_name ?? '', bank_account: '', bank_holder: props.profile.bank_holder ?? props.profile.name });
const pw = useForm({ current_password: '', password: '', password_confirmation: '' });

const submitKyc = () => kyc.post(route('user.profile.kyc'), { forceFormData: true, preserveScroll: true, onSuccess: () => kyc.reset('nik', 'ktp') });
const submitPw = () => pw.put(route('user-password.update'), {
    preserveScroll: true,
    errorBag: 'updatePassword',
    onSuccess: () => pw.reset(),
});
</script>

<template>
    <Head title="Profil & KYC" />
    <AccountLayout title="Profil & Verifikasi">
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="card p-6">
                <h2 class="text-lg font-bold text-ink">Data diri</h2>
                <form class="mt-4 space-y-4" @submit.prevent="form.put(route('user.profile.update'), { preserveScroll: true })">
                    <Field label="Email"><input :value="profile.email" class="input bg-stone-50" disabled /></Field>
                    <Field label="Nama lengkap" :error="form.errors.name"><input v-model="form.name" class="input" /></Field>
                    <Field label="Nomor HP" :error="form.errors.phone"><input v-model="form.phone" class="input" /></Field>
                    <Field label="Alamat" :error="form.errors.address"><textarea v-model="form.address" rows="3" class="input" /></Field>
                    <label class="flex items-start gap-2 text-sm text-stone-700">
                        <input v-model="form.whatsapp_notifications" type="checkbox" class="mt-0.5 rounded" />
                        <span>Kirim notifikasi penting ke WhatsApp (penawaran terlampaui, menang lelang, tagihan, jaminan).</span>
                    </label>
                    <button class="btn-dark" :disabled="form.processing">Simpan</button>
                </form>
            </section>

            <section class="card p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-ink">Verifikasi identitas (KYC)</h2>
                    <StatusBadge :status="profile.kyc" />
                </div>
                <p v-if="profile.kyc.value === 'verified'" class="mt-4 rounded-xl bg-green-50 p-4 text-sm text-green-800">
                    ✓ Identitas Anda sudah terverifikasi (NIK {{ profile.nik_masked }}). Anda dapat mengikuti semua lelang.
                </p>
                <template v-else>
                    <p v-if="profile.kyc.value === 'pending'" class="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">
                        Data Anda sedang ditinjau. Anda dapat mengirim ulang jika ada kesalahan.
                    </p>
                    <p v-if="profile.kyc.value === 'rejected'" class="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-800">
                        Pengajuan ditolak: {{ profile.kyc_note }}. Silakan perbaiki dan kirim ulang.
                    </p>
                    <form class="mt-4 space-y-4" @submit.prevent="submitKyc">
                        <Field label="NIK (16 digit)" :error="kyc.errors.nik" required>
                            <input v-model="kyc.nik" inputmode="numeric" maxlength="16" class="input font-mono" />
                        </Field>
                        <Field label="Alamat sesuai KTP" :error="kyc.errors.address" required>
                            <textarea v-model="kyc.address" rows="2" class="input" />
                        </Field>
                        <Field label="Foto KTP" :error="kyc.errors.ktp" hint="JPG/PNG maks. 5 MB. Pastikan tulisan terbaca jelas." required>
                            <input type="file" accept="image/*" class="block text-sm" @input="kyc.ktp = $event.target.files[0]" />
                        </Field>
                        <p class="text-xs text-stone-500">🔒 NIK dienkripsi dan foto KTP disimpan di penyimpanan privat yang hanya dapat diakses petugas verifikasi.</p>
                        <button class="btn-primary" :disabled="kyc.processing">Kirim untuk verifikasi</button>
                    </form>
                </template>
            </section>

            <section class="card p-6 lg:col-span-2">
                <h2 class="text-lg font-bold text-ink">Rekening pengembalian uang jaminan</h2>
                <p class="mt-1 text-sm text-stone-500">
                    Uang jaminan dikembalikan otomatis ke rekening ini setelah sesi lelang selesai.
                    <template v-if="profile.bank_account_masked">Tersimpan: <b>{{ banks[profile.bank_name] }} {{ profile.bank_account_masked }}</b> a.n. {{ profile.bank_holder }}.</template>
                </p>
                <form class="mt-4 grid gap-4 md:grid-cols-4" @submit.prevent="bank.put(route('user.profile.bank'), { preserveScroll: true, onSuccess: () => bank.reset('bank_account') })">
                    <Field label="Bank" :error="bank.errors.bank_name">
                        <select v-model="bank.bank_name" class="input">
                            <option value="" disabled>Pilih bank</option>
                            <option v-for="(label, code) in banks" :key="code" :value="code">{{ label }}</option>
                        </select>
                    </Field>
                    <Field label="Nomor rekening" :error="bank.errors.bank_account" :hint="profile.bank_account_masked ? 'Isi ulang untuk mengganti.' : ''">
                        <input v-model="bank.bank_account" inputmode="numeric" class="input font-mono" />
                    </Field>
                    <Field label="Atas nama" :error="bank.errors.bank_holder"><input v-model="bank.bank_holder" class="input" /></Field>
                    <div class="flex items-end"><button class="btn-dark w-full" :disabled="bank.processing">Simpan rekening</button></div>
                </form>
                <p class="mt-2 text-xs text-stone-500">🔒 Nomor rekening disimpan terenkripsi.</p>
            </section>

            <section class="card flex flex-wrap items-center justify-between gap-4 p-6 lg:col-span-2">
                <div>
                    <h2 class="text-lg font-bold text-ink">Data pribadi Anda</h2>
                    <p class="text-sm text-stone-500">Unduh salinan seluruh data Anda (UU PDP). Permintaan penghapusan data dapat diajukan melalui
                        <a :href="route('legal.privacy') + '#pasal-6'" class="link">Kebijakan Privasi</a>.</p>
                </div>
                <a :href="route('user.profile.export')" class="btn-outline">⬇ Unduh data saya (JSON)</a>
            </section>

            <section class="card p-6 lg:col-span-2">
                <h2 class="text-lg font-bold text-ink">Ubah kata sandi</h2>
                <form class="mt-4 grid gap-4 md:grid-cols-3" @submit.prevent="submitPw">
                    <Field label="Kata sandi saat ini" :error="$page.props.errors.updatePassword?.current_password">
                        <input v-model="pw.current_password" type="password" class="input" autocomplete="current-password" />
                    </Field>
                    <Field label="Kata sandi baru" :error="$page.props.errors.updatePassword?.password">
                        <input v-model="pw.password" type="password" class="input" autocomplete="new-password" />
                    </Field>
                    <Field label="Ulangi">
                        <input v-model="pw.password_confirmation" type="password" class="input" autocomplete="new-password" />
                    </Field>
                    <div class="md:col-span-3"><button class="btn-dark" :disabled="pw.processing">Perbarui kata sandi</button></div>
                </form>
            </section>
        </div>
    </AccountLayout>
</template>
