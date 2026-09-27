<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Field from '@/Components/Field.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import Turnstile from '@/Components/Turnstile.vue';

const props = defineProps({ categories: Array, conditions: Array, handovers: Array, prefill: Object, commissionRate: Number });

const form = useForm({
    name: props.prefill?.name ?? '', phone: props.prefill?.phone ?? '', email: props.prefill?.email ?? '', city: '',
    category_id: '', title: '', description: '', condition: 'bekas_baik', expected_price: null, handover: 'antar',
    photos: [], consent: false, 'cf-turnstile-response': '',
});
const captcha = ref(null);
const previews = computed(() => form.photos.map((f) => URL.createObjectURL(f)));
const addPhotos = (e) => {
    form.photos = [...form.photos, ...Array.from(e.target.files)].slice(0, 6);
    e.target.value = '';
};
const removePhoto = (i) => form.photos.splice(i, 1);
const submit = () => form.post(route('consign.store'), { forceFormData: true, onError: () => captcha.value?.reset() });
const photoErrors = computed(() => Object.entries(form.errors).filter(([k]) => k.startsWith('photos')).map(([, v]) => v));

const steps = [
    ['📝', 'Isi formulir', 'Ceritakan barang Anda dan unggah fotonya.'],
    ['🔎', 'Kami tinjau', 'Tim kami menilai kelayakan & estimasi harga dalam 1–2 hari kerja.'],
    ['🤝', 'Serah terima', 'Barang diantar/dijemput, diperiksa, lalu tanda tangan perjanjian titip.'],
    ['🔨', 'Dilelang', 'Hasil penjualan dikurangi komisi ditransfer ke rekening Anda.'],
];
</script>

<template>
    <Head title="Titipkan Barang untuk Dilelang" />
    <PublicLayout>
        <section class="bg-ink text-white">
            <div class="mx-auto max-w-5xl px-4 py-12">
                <h1 class="font-display text-4xl font-extrabold">Titipkan barang Anda, <span class="text-brand-400">kami yang melelang.</span></h1>
                <p class="mt-3 max-w-2xl text-stone-300">Jangkau ribuan peserta terverifikasi. Anda menentukan harga minimum, kami urus foto, promosi,
                    penagihan, hingga serah terima. Komisi mulai {{ commissionRate }}% — hanya bila barang terjual.</p>
                <div class="mt-8 grid gap-4 sm:grid-cols-4">
                    <div v-for="([icon, title, text], i) in steps" :key="title" class="rounded-2xl bg-white/5 p-4">
                        <div class="text-2xl">{{ icon }}</div>
                        <p class="mt-2 text-xs font-bold text-brand-300">LANGKAH {{ i + 1 }}</p>
                        <p class="font-semibold">{{ title }}</p>
                        <p class="text-sm text-stone-400">{{ text }}</p>
                    </div>
                </div>
            </div>
        </section>

        <form class="mx-auto grid max-w-5xl gap-6 px-4 py-10 lg:grid-cols-[1fr_320px]" @submit.prevent="submit">
            <div class="space-y-6">
                <section class="card space-y-4 p-6">
                    <h2 class="font-semibold text-ink">Tentang barang</h2>
                    <Field label="Nama barang" :error="form.errors.title" required><input v-model="form.title" class="input" placeholder="mis. Kamera Canon EOS R6 + lensa 24-105" /></Field>
                    <div class="grid gap-4 md:grid-cols-2">
                        <Field label="Kategori" :error="form.errors.category_id">
                            <select v-model="form.category_id" class="input">
                                <option value="">Tidak yakin / lainnya</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.icon }} {{ c.name }}</option>
                            </select>
                        </Field>
                        <Field label="Harga yang Anda harapkan" :error="form.errors.expected_price" hint="Opsional — membantu kami menyarankan harga limit.">
                            <MoneyInput v-model="form.expected_price" />
                        </Field>
                    </div>
                    <Field label="Kondisi" :error="form.errors.condition" required>
                        <div class="flex flex-wrap gap-2">
                            <label v-for="c in conditions" :key="c.value" class="cursor-pointer rounded-full px-3 py-1.5 text-sm ring-1"
                                :class="form.condition === c.value ? 'bg-ink text-white ring-ink' : 'ring-stone-300'">
                                <input v-model="form.condition" type="radio" :value="c.value" class="hidden" />{{ c.label }}
                            </label>
                        </div>
                    </Field>
                    <Field label="Deskripsi, kelengkapan & riwayat" :error="form.errors.description" required>
                        <textarea v-model="form.description" rows="5" class="input" placeholder="Tahun pembelian, kelengkapan (dus, nota, charger), kekurangan/cacat, alasan dijual…" />
                    </Field>
                    <Field label="Foto barang (1–6)" required hint="Foto terang dari beberapa sisi, termasuk kekurangannya. Maks. 8 MB per foto.">
                        <div class="flex flex-wrap gap-3">
                            <div v-for="(p, i) in previews" :key="i" class="relative h-24 w-28 overflow-hidden rounded-xl">
                                <img :src="p" class="h-full w-full object-cover" alt="" />
                                <button type="button" class="absolute top-1 right-1 rounded-full bg-black/60 px-2 text-xs text-white" @click="removePhoto(i)">✕</button>
                            </div>
                            <label v-if="form.photos.length < 6" class="flex h-24 w-28 cursor-pointer items-center justify-center rounded-xl border-2 border-dashed border-stone-300 text-sm text-stone-500 hover:border-brand-500">
                                + Foto<input type="file" accept="image/*" multiple class="hidden" @change="addPhotos" />
                            </label>
                        </div>
                        <p v-for="(msg, i) in photoErrors" :key="i" class="mt-1 text-xs text-red-600">{{ msg }}</p>
                    </Field>
                </section>

                <section class="card space-y-4 p-6">
                    <h2 class="font-semibold text-ink">Data Anda</h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <Field label="Nama lengkap" :error="form.errors.name" required><input v-model="form.name" class="input" autocomplete="name" /></Field>
                        <Field label="Nomor HP / WhatsApp" :error="form.errors.phone" required><input v-model="form.phone" type="tel" class="input" placeholder="081234567890" /></Field>
                        <Field label="Email" :error="form.errors.email" required><input v-model="form.email" type="email" class="input" /></Field>
                        <Field label="Kota" :error="form.errors.city" required><input v-model="form.city" class="input" /></Field>
                    </div>
                    <Field label="Serah terima barang" :error="form.errors.handover" required>
                        <div class="flex flex-wrap gap-2">
                            <label v-for="h in handovers" :key="h.value" class="cursor-pointer rounded-full px-3 py-1.5 text-sm ring-1"
                                :class="form.handover === h.value ? 'bg-ink text-white ring-ink' : 'ring-stone-300'">
                                <input v-model="form.handover" type="radio" :value="h.value" class="hidden" />{{ h.label }}
                            </label>
                        </div>
                    </Field>
                </section>
            </div>

            <aside class="space-y-4 lg:sticky lg:top-20 lg:self-start">
                <div class="card space-y-4 p-6">
                    <p class="text-sm text-stone-600">Pengajuan tidak mengikat. Perjanjian titip baru dibuat setelah barang diperiksa dan harga limit disepakati bersama.</p>
                    <Field :error="form.errors.consent">
                        <label class="flex items-start gap-2 text-sm text-stone-600">
                            <input v-model="form.consent" type="checkbox" class="mt-0.5 rounded" />
                            <span>Saya setuju data & foto ini diproses untuk menilai pengajuan sesuai <Link :href="route('legal.privacy')" class="link" target="_blank">Kebijakan Privasi</Link>.</span>
                        </label>
                    </Field>
                    <Turnstile ref="captcha" v-model="form['cf-turnstile-response']" :error="form.errors.captcha" />
                    <button class="btn-primary w-full py-3 text-base" :disabled="form.processing">
                        {{ form.processing ? `Mengunggah… ${form.progress?.percentage ?? 0}%` : 'Kirim pengajuan' }}
                    </button>
                </div>
            </aside>
        </form>
    </PublicLayout>
</template>
