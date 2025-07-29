<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.site_name', 'SkyRent');
        $this->migrator->add('general.description', 'Lorem Ipsum Sit Doler Amet');
        $this->migrator->add('general.logo', '');
        $this->migrator->add('general.logo_cms', '');
        $this->migrator->add('general.favicon', '');
        $this->migrator->add('general.about_us', 'About Us');
        $this->migrator->add('general.primary_color', '#350B75');
        $this->migrator->add('general.youtube', 'https://www.youtube.com/channel/UCV6b1a5g2k8z9c4d3e5f7wA');
        $this->migrator->add('general.tiktok', 'tiktok.com/@example');
        $this->migrator->add('general.instagram', 'instagram.com/example');
        $this->migrator->add('general.facebook', 'facebook.com/example');
        $this->migrator->add('general.twitter', 'x.com/example');
        $this->migrator->add('general.whatsapp', 'wa.me/1234567890');
        $this->migrator->add('general.telegram', 'te.me/example');
        $this->migrator->add('general.tagline', 'Tagline');
        $this->migrator->add('general.how_to_rent', '<h3 data-section-id="14faaz8" data-start="204" data-end="250"><strong data-start="208" data-end="250">Cara Sewa iPhone di Skyrental</strong></h3>
<p data-start="252" data-end="374">Sewa iPhone kini lebih mudah, aman, dan fleksibel. Kami hadir untuk memastikan pengalaman terbaik dalam setiap langkahnya.</p>
<ol data-start="376" data-end="1395">
<li data-start="376" data-end="542">
<p data-start="379" data-end="542"><strong data-start="379" data-end="408">Temukan iPhone yang Tepat</strong><br data-start="408" data-end="411">
Jelajahi koleksi iPhone kami — mulai dari kebutuhan harian, konten kreatif, hingga event khusus. Semua unit terawat, siap pakai.</p>
</li>
<li data-start="544" data-end="694">
<p data-start="547" data-end="694"><strong data-start="547" data-end="582">Atur Jadwal Sewa Sesuai Rencana</strong><br data-start="582" data-end="585">
Pilih tanggal, jam pengambilan, dan durasi sewa sesuai kebutuhanmu. Fleksibel untuk harian maupun per jam.</p>
</li>
<li data-start="696" data-end="833">
<p data-start="699" data-end="833"><strong data-start="699" data-end="733">Lengkapi Pemesanan Tanpa Ribet</strong><br data-start="733" data-end="736">
Cukup isi nama dan kontak aktif. Tanpa login, tanpa akun, langsung pesan dalam hitungan menit.</p>
</li>
<li data-start="835" data-end="962">
<p data-start="838" data-end="962"><strong data-start="838" data-end="871">Bayar dengan Metode Favoritmu</strong><br data-start="871" data-end="874">
Kami menerima transfer bank, QRIS, dan metode pembayaran lainnya yang cepat dan aman.</p>
</li>
<li data-start="964" data-end="1096">
<p data-start="967" data-end="1096"><strong data-start="967" data-end="1000">Konfirmasi &amp; Verifikasi Cepat</strong><br data-start="1000" data-end="1003">
Upload bukti pembayaran. Tim kami akan segera memverifikasi dan menyiapkan unit pilihanmu.</p>
</li>
<li data-start="1098" data-end="1244">
<p data-start="1101" data-end="1244"><strong data-start="1101" data-end="1137">Ambil atau Tunggu di Lokasi Kamu</strong><br data-start="1137" data-end="1140">
Datang ke lokasi kami, atau gunakan layanan pengantaran (jika tersedia) untuk pengalaman tanpa repot.</p>
</li>
<li data-start="1246" data-end="1395">
<p data-start="1249" data-end="1395"><strong data-start="1249" data-end="1277">Kembalikan dengan Tenang</strong><br data-start="1277" data-end="1280">
Setelah masa sewa berakhir, cukup kembalikan unit sesuai kesepakatan. Selama unit aman, kamu tak perlu khawatir.</p>
</li>
</ol>
<hr data-start="1397" data-end="1400">
<h3 data-section-id="pzpmzz" data-start="1402" data-end="1429">✨ Mengapa Memilih Kami?</h3>
<ul data-start="1430" data-end="1568">
<li data-start="1430" data-end="1468">
<p data-start="1432" data-end="1468">Unit iPhone terawat &amp; ready to use</p>
</li>
<li data-start="1469" data-end="1504">
<p data-start="1471" data-end="1504">Proses cepat, tanpa perlu login</p>
</li>
<li data-start="1505" data-end="1534">
<p data-start="1507" data-end="1534">Support ramah &amp; responsif</p>
</li>
<li data-start="1535" data-end="1568">
<p data-start="1537" data-end="1568">Aman, terpercaya, dan fleksibel</p>
</li>
</ul>
<br>');
        $this->migrator->add('general.terms_conditions', 'Syarat & Ketentuan');
        $this->migrator->add('general.privacy_policy', 'Kebijakan Privasi');
    }
};
