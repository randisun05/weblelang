<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const props = defineProps({ defaults: Object });

const bidderSteps = [
    ['Daftar akun', 'Isi nama, email, dan nomor HP. Gratis.'],
    ['Verifikasi KTP', 'Unggah foto KTP dan NIK. Data dienkripsi dan hanya dilihat petugas verifikasi.'],
    ['Daftar sesi (jika ada jaminan)', 'Beberapa sesi mensyaratkan uang jaminan. Jaminan dikembalikan penuh bila Anda tidak menang.'],
    ['Menawar', 'Tawar manual atau pasang auto-bid. Penawaran di menit-menit terakhir akan memperpanjang waktu lelang.'],
    ['Bayar invoice', `Pemenang wajib membayar dalam ${props.defaults.invoice_due_hours} jam: harga palu + premi pembeli ${props.defaults.buyer_premium_rate}%.`],
    ['Ambil / kirim barang', 'Setelah lunas, barang diserahkan dengan berita acara serah terima.'],
];
const consignorSteps = [
    ['Hubungi kami', 'Ceritakan barang yang ingin dititipkan (foto, kondisi, kelengkapan dokumen).'],
    ['Serah terima & pemeriksaan', 'Barang diperiksa, difoto profesional, diberi kode, dan disimpan di gudang kami.'],
    ['Sepakati harga limit', 'Anda menentukan harga minimum (limit). Barang tidak akan terjual di bawah harga itu.'],
    ['Dilelang', 'Barang tampil di sesi lelang dan dipromosikan ke peserta terverifikasi.'],
    ['Terima hasil', `Setelah pemenang membayar, hasil penjualan dikurangi komisi (mulai ${props.defaults.commission_rate}%) ditransfer ke rekening Anda.`],
];
const methods = [
    ['🔨', 'Lelang terbuka', 'Semua peserta melihat harga tertinggi dan saling menaikkan penawaran sampai waktu habis. Mendukung auto-bid dan perpanjangan waktu anti-sniping. Beberapa lot punya tombol ⚡ Beli Langsung yang tersedia sampai ada penawaran pertama.'],
    ['✉️', 'Penawaran tertutup', 'Setiap peserta mengajukan satu harga terbaik tanpa melihat penawaran orang lain — bahkan admin tidak bisa melihatnya. Penawaran boleh diubah sebelum tenggat; setelah ditutup, penawaran tertinggi (yang memenuhi harga limit) menang. Jika sama, yang lebih dulu mengirim menang.'],
    ['🎙️', 'Live dengan juru lelang', 'Juru lelang membuka lot satu per satu sambil disiarkan langsung. Anda menawar dari HP/laptop; juru lelang memanggil "pertama… kedua…" lalu mengetuk palu. Penawaran baru selalu membatalkan panggilan.'],
];
const faqs = [
    ['Apa itu harga limit?', 'Harga minimum yang disepakati dengan penitip. Jika penawaran tertinggi di bawah limit, barang tidak terjual.'],
    ['Apa itu auto-bid?', 'Anda menentukan batas maksimum; sistem otomatis menaikkan penawaran Anda sekecil mungkin setiap kali dilampaui, hingga batas itu.'],
    ['Kenapa waktu lelang bisa bertambah?', `Untuk mencegah "sniping". Penawaran pada ${props.defaults.anti_snipe_minutes} menit terakhir memperpanjang waktu ${props.defaults.extend_minutes} menit, sehingga semua peserta punya kesempatan yang adil.`],
    ['Bagaimana jika pemenang tidak membayar?', 'Invoice dibatalkan, akun dapat diblokir, uang jaminan (jika ada) hangus, dan barang dilelang ulang.'],
    ['Apakah riwayat penawaran bisa dimanipulasi?', 'Tidak. Setiap penawaran dicatat dengan sidik digital berantai (hash chain) sehingga perubahan apa pun dapat terdeteksi.'],
];
</script>

<template>
    <Head title="Cara Kerja & Ketentuan" />
    <PublicLayout>
        <div class="mx-auto max-w-5xl px-4 py-12">
            <h1 class="font-display text-4xl font-extrabold text-ink">Cara Kerja</h1>
            <p class="mt-2 text-stone-500">Untuk peserta lelang dan pemilik barang (penitip).</p>

            <div class="mt-10 grid gap-8 md:grid-cols-2">
                <section class="card p-6">
                    <h2 class="text-xl font-bold text-ink">🙋 Untuk Peserta</h2>
                    <ol class="mt-4 space-y-4">
                        <li v-for="([t, d], i) in bidderSteps" :key="t" class="flex gap-3">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-800">{{ i + 1 }}</span>
                            <div><p class="font-semibold text-ink">{{ t }}</p><p class="text-sm text-stone-600">{{ d }}</p></div>
                        </li>
                    </ol>
                    <Link :href="route('register')" class="btn-primary mt-6 w-full">Daftar sebagai peserta</Link>
                </section>
                <section id="titip" class="card p-6">
                    <h2 class="text-xl font-bold text-ink">🤝 Untuk Penitip Barang</h2>
                    <ol class="mt-4 space-y-4">
                        <li v-for="([t, d], i) in consignorSteps" :key="t" class="flex gap-3">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-ink text-sm font-bold text-white">{{ i + 1 }}</span>
                            <div><p class="font-semibold text-ink">{{ t }}</p><p class="text-sm text-stone-600">{{ d }}</p></div>
                        </li>
                    </ol>
                </section>
            </div>

            <section class="mt-12">
                <h2 class="text-2xl font-bold text-ink">Metode lelang</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <div v-for="[icon, title, text] in methods" :key="title" class="card p-5">
                        <div class="text-3xl">{{ icon }}</div>
                        <h3 class="mt-2 font-semibold text-ink">{{ title }}</h3>
                        <p class="mt-1 text-sm text-stone-600">{{ text }}</p>
                    </div>
                </div>
            </section>

            <section class="mt-12">
                <h2 class="text-2xl font-bold text-ink">Pertanyaan umum</h2>
                <div class="mt-4 space-y-3">
                    <details v-for="[q, a] in faqs" :key="q" class="card group p-5">
                        <summary class="cursor-pointer list-none font-semibold text-ink">{{ q }}</summary>
                        <p class="mt-2 text-sm text-stone-600">{{ a }}</p>
                    </details>
                </div>
            </section>

            <section class="mt-12 rounded-2xl border border-stone-200 bg-white p-6 text-sm text-stone-600">
                <h2 class="text-lg font-bold text-ink">Syarat & Ketentuan Singkat</h2>
                <ul class="mt-3 list-disc space-y-1 pl-5">
                    <li>Setiap penawaran bersifat mengikat dan tidak dapat dibatalkan.</li>
                    <li>Barang dijual sesuai kondisi apa adanya (as is) berdasarkan hasil pemeriksaan yang ditampilkan.</li>
                    <li>Pemenang wajib melunasi invoice sebelum batas waktu; kegagalan bayar adalah wanprestasi.</li>
                    <li>Satu orang hanya boleh memiliki satu akun. Penitip dilarang menawar barangnya sendiri.</li>
                    <li>Data pribadi diproses sesuai UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi.</li>
                </ul>
            </section>
        </div>
    </PublicLayout>
</template>
