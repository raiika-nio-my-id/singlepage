<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Syarat &amp; Ketentuan — {{ $platformName }}</title>
    <meta name="robots" content="noindex,follow">
    <style>
        :root {
            --ink: #1c1917;
            --muted: #57534e;
            --line: #e7e5e4;
            --paper: #fafaf9;
            --card: #ffffff;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: var(--ink);
            background: var(--paper);
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            line-height: 1.65;
        }
        header, main, footer {
            width: min(46rem, calc(100% - 2rem));
            margin-left: auto;
            margin-right: auto;
        }
        header { padding: 2.5rem 0 1rem; }
        header p {
            margin: .35rem 0 0;
            color: var(--muted);
            font-size: .95rem;
        }
        a { color: #0f3d3e; }
        h1 {
            margin: 0;
            font-size: 1.75rem;
            line-height: 1.25;
        }
        main {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 1.5rem 1.4rem 1.75rem;
        }
        h2 {
            margin: 1.6rem 0 .45rem;
            font-size: 1.05rem;
        }
        p, li { font-size: .98rem; }
        p { margin: 0 0 .85rem; }
        ol {
            margin: 0;
            padding-left: 1.2rem;
        }
        li { margin: 0 0 .45rem; }
        .note {
            background: #f5f5f4;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: .9rem 1rem;
            margin: 0 0 1.25rem;
        }
        footer {
            padding: 1.5rem 0 2.5rem;
            color: var(--muted);
            font-size: .9rem;
        }
    </style>
</head>
<body>
    <header>
        <h1>Syarat &amp; Ketentuan dan Kebijakan Konten</h1>
        <p>{{ $platformName }} sebagai penyedia platform &middot; Diperbarui {{ $updatedOn }}</p>
        <p>Berlaku untuk situs <strong>{{ $siteHost }}</strong></p>
    </header>
    <main>
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
</body>
</html>
