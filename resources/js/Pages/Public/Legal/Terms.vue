<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import LegalSection from '@/Components/LegalSection.vue';
import { date } from '@/lib/format';

const props = defineProps({ operator: Object, privacyEmail: String, rules: Object, version: String });
const op = props.operator;
</script>

<template>
    <Head title="Syarat & Ketentuan" />
    <PublicLayout>
        <article class="mx-auto max-w-3xl px-4 py-12">
            <p class="text-sm text-stone-500">Berlaku sejak {{ date(version) }} · versi {{ version }}</p>
            <h1 class="font-display text-4xl font-extrabold text-ink">Syarat & Ketentuan</h1>
            <p class="mt-4 text-stone-700">
                Syarat & Ketentuan ini mengatur penggunaan platform lelang online <b>{{ op.name }}</b> yang dikelola oleh
                <b>{{ op.legal_entity }}</b>, {{ op.address }}<template v-if="op.nib"> (NIB {{ op.nib }})</template><template v-if="op.auction_house">,
                bekerja sama dengan <b>{{ op.auction_house }}</b> sebagai balai lelang</template>. Dengan mendaftar, menawar, atau
                menitipkan barang, Anda menyatakan telah membaca, memahami, dan menyetujui ketentuan ini beserta
                <Link :href="route('legal.privacy')" class="link">Kebijakan Privasi</Link>.
            </p>

            <LegalSection no="1" title="Definisi">
                <ul>
                    <li><b>Penyelenggara</b>: {{ op.legal_entity }} selaku pengelola platform {{ op.name }}.</li>
                    <li><b>Peserta</b>: pengguna terdaftar yang mengikuti lelang.</li>
                    <li><b>Penitip</b>: pemilik barang yang menitipkan barangnya untuk dilelang berdasarkan perjanjian titip lelang.</li>
                    <li><b>Lot</b>: satu barang (atau satu paket barang) yang dilelang dalam suatu sesi.</li>
                    <li><b>Harga Limit</b>: harga minimum yang disepakati dengan Penitip; lot tidak terjual bila penawaran tertinggi di bawahnya.</li>
                    <li><b>Harga Palu</b>: penawaran pemenang yang disahkan pada saat lot ditutup.</li>
                    <li><b>Premi Pembeli</b>: biaya yang ditambahkan pada Harga Palu dan dibayar oleh pemenang.</li>
                    <li><b>Uang Jaminan</b>: dana yang disetor Peserta sebagai syarat mengikuti sesi tertentu.</li>
                </ul>
            </LegalSection>

            <LegalSection no="2" title="Pendaftaran & Akun">
                <ol>
                    <li>Peserta wajib berusia minimal 18 tahun (atau sudah menikah) dan cakap hukum.</li>
                    <li>Data yang diberikan wajib benar, lengkap, dan milik sendiri. Satu orang hanya boleh memiliki satu akun.</li>
                    <li>Peserta wajib lulus verifikasi identitas (KTP) dan verifikasi email sebelum dapat menawar.</li>
                    <li>Peserta bertanggung jawab menjaga kerahasiaan kata sandi. Seluruh aktivitas dari akun dianggap dilakukan oleh pemilik akun.</li>
                </ol>
            </LegalSection>

            <LegalSection no="3" title="Barang yang Dilelang">
                <ol>
                    <li>Barang dijual dalam kondisi <b>apa adanya (as is)</b> sesuai deskripsi, foto, dan hasil pemeriksaan yang ditampilkan.</li>
                    <li>Penyelenggara berupaya menyajikan informasi secara akurat, namun Peserta dianjurkan melihat barang secara langsung
                        (dengan janji temu) sebelum menawar. Tidak ada garansi kecuali dinyatakan secara tertulis pada deskripsi lot.</li>
                    <li>Estimasi harga hanya bersifat panduan dan bukan jaminan nilai.</li>
                    <li>Penyelenggara berhak menarik atau membatalkan lot sebelum ditutup (misalnya atas permintaan Penitip atau ditemukan
                        ketidaksesuaian), dan penawaran pada lot tersebut menjadi batal.</li>
                </ol>
            </LegalSection>

            <LegalSection no="4" title="Metode Lelang & Aturan Penawaran">
                <ol>
                    <li>Setiap penawaran <b>bersifat mengikat dan tidak dapat dibatalkan</b>.</li>
                    <li><b>Lelang terbuka</b>: penawaran harus lebih tinggi dari penawaran tertinggi ditambah kelipatan yang berlaku.
                        Penawaran pada {{ rules.anti_snipe_minutes }} menit terakhir memperpanjang waktu {{ rules.extend_minutes }} menit.
                        Fitur penawaran otomatis (auto-bid) menawar atas nama Peserta hingga batas maksimum yang ditetapkannya.</li>
                    <li><b>Beli Langsung</b>: bila tersedia, Peserta dapat membeli lot seharga Beli Langsung selama belum ada penawaran;
                        lot langsung menjadi milik pembeli tersebut.</li>
                    <li><b>Penawaran tertutup</b>: penawaran dirahasiakan hingga lot ditutup. Peserta dapat mengubah penawarannya sebelum
                        tenggat; penawaran terakhir yang dihitung. Penawaran tertinggi menang; bila sama, yang lebih dahulu diajukan.</li>
                    <li><b>Lelang live</b>: dipandu juru lelang. Lot terjual setelah panggilan kedua dan ketukan palu; penawaran yang masuk
                        sebelum palu diketuk membatalkan panggilan.</li>
                    <li>Waktu server platform menjadi acuan resmi. Riwayat penawaran dicatat dengan sidik digital berantai dan dapat diaudit.</li>
                    <li>Penyelenggara tidak bertanggung jawab atas kegagalan penawaran akibat gangguan koneksi atau perangkat Peserta.</li>
                </ol>
            </LegalSection>

            <LegalSection no="5" title="Uang Jaminan">
                <ol>
                    <li>Sesi tertentu mensyaratkan Uang Jaminan yang disetor sebelum menawar.</li>
                    <li>Uang Jaminan dikembalikan penuh ke rekening yang didaftarkan Peserta setelah sesi selesai, bagi Peserta yang tidak
                        menang atau pemenang yang telah melunasi pembayaran.</li>
                    <li>Uang Jaminan <b>hangus</b> bila Peserta menang namun tidak melunasi invoice sesuai ketentuan.</li>
                </ol>
            </LegalSection>

            <LegalSection no="6" title="Pemenang & Pembayaran">
                <ol>
                    <li>Pemenang menerima invoice berisi Harga Palu, Premi Pembeli (standar {{ rules.buyer_premium_rate }}%, dapat berbeda
                        per sesi dan tercantum pada halaman sesi), serta biaya lain bila ada.</li>
                    <li>Invoice wajib dilunasi dalam <b>{{ rules.invoice_due_hours }} jam</b> melalui pembayaran online atau transfer ke
                        rekening resmi yang tercantum pada invoice. Penyelenggara tidak pernah meminta pembayaran ke rekening pribadi.</li>
                    <li>Bila tidak dilunasi tepat waktu, invoice dibatalkan otomatis (wanprestasi), Uang Jaminan hangus, akun dapat
                        dibatasi, dan lot dapat dilelang ulang.</li>
                </ol>
            </LegalSection>

            <LegalSection no="7" title="Serah Terima Barang">
                <ol>
                    <li>Barang diserahkan setelah invoice lunas, dibuktikan dengan Berita Acara Serah Terima.</li>
                    <li>Pemenang wajib mengambil barang dalam {{ rules.pickup_days }} hari sejak lunas atau meminta pengiriman atas
                        biaya dan risiko pemenang. Lewat dari batas tersebut, Penyelenggara berhak mengenakan biaya penyimpanan.</li>
                    <li>Risiko atas barang beralih kepada pemenang sejak Berita Acara Serah Terima ditandatangani atau barang diserahkan
                        kepada jasa pengiriman.</li>
                </ol>
            </LegalSection>

            <LegalSection no="8" title="Ketentuan bagi Penitip">
                <ol>
                    <li>Penitip menjamin bahwa barang adalah miliknya yang sah, tidak dalam sengketa, tidak dijaminkan, dan bukan
                        hasil tindak pidana, serta menyerahkan dokumen kepemilikan yang diperlukan.</li>
                    <li>Harga Limit dan komisi (standar {{ rules.commission_rate }}% dari Harga Palu) disepakati dalam perjanjian titip lelang.</li>
                    <li>Hasil penjualan dikurangi komisi dan biaya lain yang disepakati ditransfer ke rekening Penitip setelah pemenang
                        melunasi pembayaran.</li>
                    <li>Barang yang tidak laku dapat dilelang ulang atau dikembalikan kepada Penitip sesuai kesepakatan.</li>
                    <li>Penitip dilarang menawar barangnya sendiri, baik langsung maupun melalui pihak lain.</li>
                </ol>
            </LegalSection>

            <LegalSection no="9" title="Larangan">
                <ul>
                    <li>Menaikkan harga secara tidak wajar melalui akun lain atau pihak terafiliasi (<i>shill bidding</i>).</li>
                    <li>Membuat lebih dari satu akun, menggunakan identitas orang lain, atau meminjamkan akun.</li>
                    <li>Menggunakan bot, skrip otomatis, atau cara lain yang mengganggu sistem.</li>
                    <li>Menawar tanpa niat dan kemampuan untuk membayar.</li>
                    <li>Menitipkan barang ilegal, palsu, atau yang dilarang diperjualbelikan menurut peraturan perundang-undangan.</li>
                </ul>
                <p>Pelanggaran dapat mengakibatkan pembatalan penawaran/transaksi, pemblokiran akun, hangusnya Uang Jaminan, dan
                    pelaporan kepada pihak berwenang.</p>
            </LegalSection>

            <LegalSection no="10" title="Batasan Tanggung Jawab">
                <p>Sejauh diizinkan hukum, tanggung jawab Penyelenggara atas suatu transaksi terbatas pada jumlah yang telah dibayarkan
                    Peserta untuk transaksi tersebut. Penyelenggara tidak bertanggung jawab atas kerugian tidak langsung, kehilangan
                    keuntungan, atau gangguan layanan di luar kendali wajar Penyelenggara (keadaan kahar).</p>
            </LegalSection>

            <LegalSection no="11" title="Perubahan Ketentuan">
                <p>Penyelenggara dapat mengubah ketentuan ini. Perubahan material diberitahukan melalui platform dan Peserta akan diminta
                    menyetujui ulang sebelum dapat melanjutkan penggunaan layanan.</p>
            </LegalSection>

            <LegalSection no="12" title="Hukum yang Berlaku & Penyelesaian Sengketa">
                <p>Ketentuan ini tunduk pada hukum Negara Republik Indonesia. Sengketa diselesaikan terlebih dahulu secara musyawarah;
                    bila tidak tercapai dalam 30 hari, para pihak sepakat menyelesaikannya melalui Pengadilan Negeri {{ op.city }}.</p>
            </LegalSection>

            <LegalSection no="13" title="Kontak">
                <p>{{ op.legal_entity }} · {{ op.address }} · Email: <a :href="`mailto:${op.email}`" class="link">{{ op.email }}</a> · Telepon/WA: {{ op.phone }}</p>
            </LegalSection>
        </article>
    </PublicLayout>
</template>
