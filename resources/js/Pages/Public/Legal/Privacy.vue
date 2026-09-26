<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import LegalSection from '@/Components/LegalSection.vue';
import { date } from '@/lib/format';

const props = defineProps({ operator: Object, privacyEmail: String, rules: Object, version: String });
const op = props.operator;
</script>

<template>
    <Head title="Kebijakan Privasi" />
    <PublicLayout>
        <article class="mx-auto max-w-3xl px-4 py-12">
            <p class="text-sm text-stone-500">Berlaku sejak {{ date(version) }} · versi {{ version }}</p>
            <h1 class="font-display text-4xl font-extrabold text-ink">Kebijakan Privasi</h1>
            <p class="mt-4 text-stone-700">
                <b>{{ op.legal_entity }}</b> ("kami"), pengelola {{ op.name }}, bertindak sebagai pengendali data pribadi dan berkomitmen
                melindungi data pribadi Anda sesuai Undang-Undang Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi (UU PDP).
                Kebijakan ini menjelaskan data apa yang kami kumpulkan, untuk apa, dan hak-hak Anda.
            </p>

            <LegalSection no="1" title="Data yang Kami Kumpulkan">
                <ul>
                    <li><b>Identitas & kontak</b>: nama, email, nomor telepon, alamat.</li>
                    <li><b>Data verifikasi (KYC)</b>: NIK dan foto KTP — termasuk data pribadi yang bersifat spesifik.</li>
                    <li><b>Data keuangan</b>: nomor rekening untuk pengembalian uang jaminan atau pembayaran hasil lelang (Penitip),
                        riwayat pembayaran dan invoice. Data kartu/e-wallet diproses langsung oleh payment gateway dan tidak kami simpan.</li>
                    <li><b>Aktivitas lelang</b>: penawaran, daftar pantauan, pendaftaran sesi.</li>
                    <li><b>Data teknis</b>: alamat IP, jenis perangkat & peramban, dan cookie yang diperlukan untuk keamanan dan sesi login.</li>
                </ul>
            </LegalSection>

            <LegalSection no="2" title="Tujuan & Dasar Pemrosesan">
                <ul>
                    <li>Menjalankan layanan lelang, penagihan, serah terima barang, dan pembayaran hasil penjualan — <i>pelaksanaan perjanjian</i>.</li>
                    <li>Memverifikasi identitas, mencegah penipuan, akun ganda, dan manipulasi harga — <i>kepentingan yang sah</i> dan <i>kewajiban hukum</i>.</li>
                    <li>Mengirim notifikasi transaksi (penawaran terlampaui, kemenangan, pembayaran) — <i>pelaksanaan perjanjian</i>.</li>
                    <li>Memenuhi kewajiban perpajakan, akuntansi, dan permintaan otoritas yang sah — <i>kewajiban hukum</i>.</li>
                    <li>Hal lain di luar tujuan di atas hanya dengan <i>persetujuan</i> Anda, yang dapat ditarik kapan saja.</li>
                </ul>
            </LegalSection>

            <LegalSection no="3" title="Keamanan Data">
                <ul>
                    <li>NIK dan nomor rekening disimpan dalam bentuk terenkripsi; foto KTP dan bukti transfer disimpan di penyimpanan privat
                        yang hanya dapat diakses petugas berwenang.</li>
                    <li>Akses petugas dibatasi sesuai peran, dilindungi autentikasi dua faktor, dan dicatat dalam log audit.</li>
                    <li>Koneksi ke platform dienkripsi (HTTPS). Foto barang diproses ulang untuk menghapus metadata lokasi.</li>
                </ul>
            </LegalSection>

            <LegalSection no="4" title="Berbagi Data dengan Pihak Lain">
                <p>Kami tidak menjual data pribadi Anda. Data hanya dibagikan seperlunya kepada:</p>
                <ul>
                    <li>Payment gateway (mis. Midtrans atau Xendit) untuk memproses pembayaran dan transfer dana.</li>
                    <li>Penyedia infrastruktur (hosting, email) yang terikat kewajiban kerahasiaan.</li>
                    <li>Penyedia layanan pesan WhatsApp, hanya nomor HP dan isi notifikasi transaksi. Notifikasi WhatsApp dapat dimatikan kapan saja di halaman profil.</li>
                    <li v-if="op.auction_house">{{ op.auction_house }} selaku balai lelang, untuk keperluan penyelenggaraan dan pelaporan lelang.</li>
                    <li>Otoritas yang berwenang bila diwajibkan peraturan perundang-undangan.</li>
                </ul>
                <p>Kepada Penitip, kami hanya menampilkan informasi yang diperlukan (harga dan status barangnya), tanpa identitas Peserta.
                    Pada riwayat penawaran publik, nama Peserta ditampilkan tersamar.</p>
            </LegalSection>

            <LegalSection no="5" title="Masa Penyimpanan">
                <p>Data disimpan selama akun aktif dan selama diperlukan untuk tujuan di atas. Dokumen transaksi dan keuangan disimpan
                    sesuai kewajiban perpajakan dan akuntansi (umumnya hingga 10 tahun). Setelah itu data dihapus atau dianonimkan.</p>
            </LegalSection>

            <LegalSection no="6" title="Hak Anda">
                <p>Sesuai UU PDP, Anda berhak untuk:</p>
                <ul>
                    <li>Mendapatkan informasi dan <b>mengakses serta memperoleh salinan</b> data pribadi Anda
                        <template v-if="$page.props.auth.user">— tersedia langsung di <Link :href="route('user.profile')" class="link">Profil → Unduh data saya</Link></template>.</li>
                    <li>Memperbaiki dan memperbarui data yang tidak akurat.</li>
                    <li>Meminta penghapusan data, sepanjang tidak bertentangan dengan kewajiban penyimpanan menurut hukum.</li>
                    <li>Menarik persetujuan dan mengajukan keberatan atas pemrosesan tertentu.</li>
                    <li>Mengajukan keluhan kepada lembaga pengawas pelindungan data pribadi.</li>
                </ul>
                <p>Ajukan permintaan ke <a :href="`mailto:${privacyEmail}`" class="link">{{ privacyEmail }}</a>. Kami menanggapi paling lambat
                    3 x 24 jam untuk konfirmasi penerimaan dan menyelesaikannya dalam jangka waktu yang ditentukan peraturan.</p>
            </LegalSection>

            <LegalSection no="7" title="Kegagalan Pelindungan Data">
                <p>Bila terjadi kegagalan pelindungan data pribadi, kami akan memberitahukan kepada Anda dan lembaga pengawas paling lambat
                    3 x 24 jam, memuat data yang terdampak, waktu dan cara terjadinya, serta upaya penanganan dan pemulihan.</p>
            </LegalSection>

            <LegalSection no="8" title="Cookie">
                <p>Kami hanya menggunakan cookie yang diperlukan untuk login, keamanan (termasuk perlindungan CSRF dan pencegahan
                    akun ganda), serta preferensi tampilan. Kami tidak menggunakan cookie iklan pihak ketiga.</p>
            </LegalSection>

            <LegalSection no="9" title="Perubahan Kebijakan">
                <p>Perubahan material akan diberitahukan melalui platform dan Anda akan diminta menyetujui ulang.</p>
            </LegalSection>

            <LegalSection no="10" title="Kontak">
                <p>{{ op.legal_entity }} · {{ op.address }} · Pelindungan data: <a :href="`mailto:${privacyEmail}`" class="link">{{ privacyEmail }}</a></p>
            </LegalSection>
        </article>
    </PublicLayout>
</template>
