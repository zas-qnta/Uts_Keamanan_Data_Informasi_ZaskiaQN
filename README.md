# Sistem Informasi Kepegawaian

Aplikasi sistem informasi berbasis web yang terdiri dari **landing page** dan **halaman admin**. Seluruh data pegawai disimpan dalam bentuk terenkripsi di database dan hanya dapat dibaca oleh admin yang memegang RSA private key.

Dibuat dengan PHP native dan MySQL untuk tugas kriptografi simetris dan asimetris.

## Ketentuan Tugas dan Penerapannya

| Ketentuan | Penerapan |
|---|---|
| Login admin tidak memakai password, diganti input kunci / private key | `auth/login.php` menerima RSA private key (tempel teks PEM atau pilih file `.pem`). Kunci diperiksa terhadap `keys/public.pem`. Jika cocok, data didekripsi. Jika tidak cocok, panel tetap terbuka dalam mode terkunci: seluruh teks pada halaman admin tampil sebagai ciphertext dan data tidak dapat diubah. |
| Setelah masuk, semua konten halaman admin didekripsi | `admin/dashboard.php` dan `admin/pegawai.php` mendekripsi setiap baris database dengan private key yang dipakai login. |
| Konten dibuat dan disimpan dalam bentuk terenkripsi | Data pegawai dienkripsi lebih dulu (`encryption/hybrid.php`), baru disimpan ke tabel `pegawai_terenkripsi`. |
| Kode, hasil, dan database dikumpulkan | Kode dan database ada di repositori ini (`database/kepegawaian.sql`). Kode dan hasilnya disatukan dalam satu file PDF. |

## Fitur

- Landing page dengan dua halaman: Beranda dan Data Pegawai (hanya menampilkan ciphertext).
- Login admin dengan RSA private key.
- Dashboard berisi statistik dan 5 pegawai terbaru hasil dekripsi.
- Tambah, ubah, dan hapus data pegawai. Setiap simpan dienkripsi ulang.
- Perlindungan CSRF pada semua form, serta cookie sesi `httponly` dan `samesite=Strict`.

## Algoritma

Sistem memakai skema hybrid:

1. **AES-256-GCM (simetris)** mengenkripsi data pegawai. Kunci AES (32 byte) dan IV (12 byte) dibuat acak untuk setiap data. GCM juga menghasilkan authentication tag untuk menjaga keutuhan data.
2. **RSA-OAEP (asimetris)** membungkus kunci AES dengan public key. Kunci AES hanya bisa dibuka kembali dengan private key.

Yang tersimpan di database per baris: `ciphertext`, `iv`, `tag`, dan `wrapped_key` (semuanya base64). Server hanya menyimpan public key, sedangkan private key dipegang admin.

## Alur

    Simpan data
    Data pegawai (JSON)
      -> AES-256-GCM dengan kunci acak     -> ciphertext + iv + tag
      -> kunci AES dibungkus RSA public key -> wrapped_key
      -> simpan ke tabel pegawai_terenkripsi

    Login dan tampil
    Admin memasukkan private key
      -> diverifikasi terhadap public key sistem
      -> wrapped_key dibuka dengan private key -> kunci AES
      -> AES-256-GCM mendekripsi ciphertext    -> data pegawai tampil di halaman admin

## Struktur Folder

    sistem-kepegawaian/
    |-- admin/
    |   |-- dashboard.php
    |   |-- layout.php
    |   |-- pegawai.php
    |-- auth/
    |   |-- login.php
    |   |-- logout.php
    |-- config/
    |   |-- bootstrap.php
    |   |-- database.php
    |-- database/
    |   |-- kepegawaian.sql
    |-- encryption/
    |   |-- hybrid.php
    |-- keys/
    |   |-- public.pem
    |-- index.php
    |-- README.md

## Kebutuhan

- Laragon (Apache dan MySQL/MariaDB)
- PHP 8.x dengan ekstensi OpenSSL aktif
- Perintah `openssl` untuk membuat pasangan kunci

## Instalasi

1. Salin folder `sistem-kepegawaian` ke `D:\laragon\www\`.
2. Jalankan Apache dan MySQL di Laragon, lalu import `database/kepegawaian.sql` lewat phpMyAdmin. Database `kepegawaian` dan tabel `pegawai_terenkripsi` akan terbentuk.
3. Sesuaikan koneksi di `config/database.php` bila perlu:

        $host = 'localhost';
        $dbname = 'kepegawaian';
        $username = 'root';
        $password = '';

4. Buat pasangan kunci RSA. Simpan `private.pem` di luar folder proyek:

        openssl genrsa -out private.pem 2048
        openssl rsa -in private.pem -pubout -out keys\public.pem

5. Buka `http://localhost/sistem-kepegawaian/`, klik **Login Admin**, lalu tempel isi `private.pem` atau pilih filenya.

Catatan: jika `keys/public.pem` diganti, data lama di database tidak bisa dibuka dengan private key yang baru dan akan tampil sebagai `[gagal didekripsi]`. Kosongkan tabel (`TRUNCATE pegawai_terenkripsi;`) lalu input ulang datanya.

## Catatan Keamanan

- Private key tidak boleh disimpan di dalam folder proyek maupun di-commit ke Git (sudah dikecualikan di `.gitignore`).
- Selama sesi login, private key disimpan di sesi PHP server agar halaman admin bisa mendekripsi data. Sesi dihapus saat logout.
- Gunakan HTTPS jika aplikasi dipasang di server sungguhan, karena private key dikirim saat login.
- Simpan cadangan private key. Tanpa kunci ini, data terenkripsi tidak dapat dipulihkan.