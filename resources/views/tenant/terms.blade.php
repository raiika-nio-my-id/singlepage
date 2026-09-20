<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Syarat &amp; Ketentuan — {{ $platformName }}</title>
    <meta name="robots" content="noindex,follow">
    @include('partials.theme-boot')
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="legal-wrap">
    <header>
        <div>
            <h1>Syarat &amp; Ketentuan dan Kebijakan Konten</h1>
            <p>{{ $platformName }} sebagai penyedia platform &middot; Diperbarui {{ $updatedOn }}</p>
            <p>Berlaku untuk situs <strong>{{ $siteHost }}</strong></p>
        </div>
        @include('partials.theme-toggle')
    </header>
    <main class="card">
        <p class="note">
            {{ $platformName }} hanya menyediakan sarana teknis untuk menayangkan website.
            Seluruh konten pada situs ini dibuat, diisi, dan dapat diubah sewaktu-waktu oleh pengguna/pemilik situs yang bersangkutan.
            {{ $platformName }} tidak bertanggung jawab atas bentuk maupun isi konten tersebut.
        </p>

        <h2>1. Identitas layanan</h2>
        <p>
            {{ $platformName }} adalah penyedia platform (platform provider) untuk pembuatan dan penayangan website satu halaman melalui subdomain.
            {{ $platformName }} bukan pemilik, penerbit, editor, penyelenggara, atau penjamin isi dari masing-masing website klien.
        </p>

        <h2>2. Sifat layanan</h2>
        <p>
            Layanan yang diberikan terbatas pada penyediaan infrastruktur teknis, termasuk penayangan HTML yang disimpan oleh pengguna.
            {{ $platformName }} tidak membuat, tidak menyunting, tidak meninjau secara menyeluruh, tidak memverifikasi, dan tidak menyetujui konten yang tampil di website klien.
        </p>

        <h2>3. Kendali penuh pengguna atas konten</h2>
        <p>
            Pengguna atau pemilik akun yang terkait dengan website ini berhak mengisi, mengubah, mengganti, dan menghapus konten sesuai kehendaknya melalui sistem yang disediakan.
            Segala teks, gambar, tautan, klaim, data kontak, harga, penawaran, undangan, maupun materi lain yang tampil di situs ini merupakan hasil tindakan pengguna tersebut.
        </p>

        <h2>4. Penafian tanggung jawab konten</h2>
        <p>
            {{ $platformName }} tidak bertanggung jawab atas segala bentuk atau isi konten yang tertera pada website ini, termasuk tetapi tidak terbatas pada:
        </p>
        <ol>
            <li>kebenaran, keakuratan, atau kelengkapan informasi;</li>
            <li>penawaran, janji, harga, produk, jasa, atau penggalangan dana;</li>
            <li>identitas, alamat, nomor telepon, atau data pihak ketiga;</li>
            <li>penipuan, perbuatan merugikan, atau tindak pidana yang dilakukan melalui atau dengan memanfaatkan website;</li>
            <li>pelanggaran hak cipta, merek, privasi, pencemaran nama baik, atau hak pihak lain;</li>
            <li>kerugian langsung maupun tidak langsung yang diderita pengunjung atau pihak ketiga.</li>
        </ol>

        <h2>5. Tanggung jawab pengguna</h2>
        <p>
            Pemilik/pengelola website bertanggung jawab penuh secara hukum atas seluruh konten dan atas segala akibat yang timbul terhadap pengunjung maupun pihak ketiga.
            Pengguna wajib memastikan bahwa konten dan penggunaan situs tidak melanggar hukum yang berlaku di Republik Indonesia, termasuk tidak digunakan untuk penipuan, pemerasan, phishing, pornografi anak, ujaran kebencian, terorisme, atau perbuatan melawan hukum lainnya.
        </p>

        <h2>6. Tidak ada jaminan kepada pengunjung</h2>
        <p>
            Keberadaan website pada subdomain {{ $platformName }} tidak berarti {{ $platformName }} memverifikasi, merekomendasikan, atau menjamin keabsahan pihak yang mengelola situs.
            Pengunjung wajib melakukan verifikasi sendiri sebelum bertransaksi, mentransfer dana, menyerahkan data pribadi, atau mengandalkan informasi apa pun di situs ini.
        </p>

        <h2>7. Pembatasan tanggung jawab platform</h2>
        <p>
            Sepanjang diizinkan oleh hukum yang berlaku, {{ $platformName }} tidak bertanggung jawab atas tuntutan, ganti rugi, kehilangan data, kerugian finansial, atau sengketa yang timbul dari konten, transaksi, atau penggunaan website klien.
            {{ $platformName }} tidak menjadi pihak dalam hubungan antara pengunjung dan pemilik situs.
        </p>

        <h2>8. Penyalahgunaan dan penonaktifan</h2>
        <p>
            Penggunaan platform untuk penipuan atau perbuatan melawan hukum dilarang.
            {{ $platformName }} berhak menonaktifkan, menangguhkan, atau menghapus website tanpa pemberitahuan apabila terdapat indikasi pelanggaran, laporan, atau permintaan dari pihak yang berwenang.
        </p>

        <h2>9. Pengaduan</h2>
        <p>
            Jika Anda menemukan konten yang diduga melanggar hukum atau merugikan pihak lain, segera hubungi pengelola platform {{ $platformName }}.
            Laporan dapat ditindaklanjuti dengan peninjauan dan/atau penonaktifan situs terkait.
        </p>

        <h2>10. Perubahan ketentuan</h2>
        <p>
            Ketentuan ini dapat diubah sewaktu-waktu. Versi yang berlaku adalah yang ditayangkan pada halaman ini.
            Penggunaan situs setelah perubahan berarti penerimaan atas ketentuan yang diperbarui.
        </p>

        <h2>11. Hukum yang berlaku</h2>
        <p>
            Syarat dan ketentuan ini diatur oleh hukum Republik Indonesia.
        </p>
    </main>
    <footer>
        <p><a href="{{ $homeUrl }}">Kembali ke website</a></p>
        <p>&copy; {{ date('Y') }} {{ $platformName }}. Penyedia platform, bukan pemilik konten situs ini.</p>
    </footer>
    <script src="{{ asset('js/theme.js') }}" defer></script>
</body>
</html>
