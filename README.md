# Sistem Informasi Kepegawaian

Aplikasi sistem informasi berbasis web dengan **landing page** publik dan **panel admin**. Data pegawai disimpan terenkripsi di database dan hanya bisa dibaca oleh admin yang memegang **RSA private key**.

Dibuat dengan PHP native dan MySQL untuk UTS Keamanan Data dan Informasi (kriptografi simetris dan asimetris).

Repositori: https://github.com/zas-qnta/Uts_Keamanan_Data_Informasi_ZaskiaQN

## Ketentuan Tugas dan Penerapannya

| Ketentuan | Penerapan |
|---|---|
| Login admin tanpa password, diganti input kunci / private key | `auth/login.php` menerima RSA private key (tempel teks PEM atau pilih file `.pem`). Kunci diverifikasi terhadap `keys/public.pem`. Jika cocok, data didekripsi. Jika tidak cocok, panel tetap terbuka dalam **mode terkunci**: seluruh teks tampil sebagai ciphertext dan data tidak dapat diubah. |
| Setelah masuk, semua konten halaman admin didekripsi | `admin/dashboard.php`, `admin/pegawai.php`, dan `admin/enkripsi.php` mendekripsi setiap baris database dengan private key yang dipakai login. |
| Konten dibuat dan disimpan dalam bentuk terenkripsi | Data pegawai dienkripsi lebih dulu (`encryption/hybrid.php`), baru disimpan ke tabel `pegawai_terenkripsi`. |
| Kode, hasil, dan database dikumpulkan | Kode dan database ada di repositori ini (`database/kepegawaian.sql`). Kode dan hasilnya disatukan dalam satu file PDF. |

## Fitur

- **Landing page** dengan dua halaman: Beranda dan Data Pegawai. Halaman publik hanya menampilkan nama dan jabatan.
- **Login admin** dengan RSA private key, tanpa password.
- **Dashboard**: statistik sistem, status autentikasi, status dekripsi, dan 5 pegawai terbaru.
- **Data Pegawai**: tambah, ubah, dan hapus data. Setiap simpan dienkripsi ulang.
- **Enkripsi dan Dekripsi**: membandingkan hasil dekripsi dengan isi database (ciphertext, IV, tag, dan kunci AES terbungkus).
- **Mode terkunci** bila private key tidak cocok: tampilan hanya ciphertext, form tambah/ubah/hapus tidak tersedia.
- Perlindungan CSRF pada semua form, cookie sesi `httponly` dan `samesite=Strict`, serta `session_regenerate_id` saat login.

## Algoritma

Sistem memakai skema **hybrid**:

1. **AES-256-GCM (simetris)** mengenkripsi data pegawai. Kunci AES (32 byte) dan IV (12 byte) dibuat acak untuk setiap data. GCM menghasilkan authentication tag (16 byte) untuk menjaga keutuhan data.
2. **RSA-OAEP (asimetris)** membungkus kunci AES dengan public key. Kunci AES hanya bisa dibuka kembali dengan private key.

Data yang dienkripsi berupa satu paket JSON berisi NIP, nama, jabatan, email, dan telepon. Per baris di database tersimpan: `ciphertext`, `iv`, `tag`, dan `wrapped_key` (semuanya base64). Server hanya menyimpan public key, sedangkan private key dipegang admin.

### Verifikasi private key saat login

Sistem mengenkripsi 32 byte acak dengan public key sistem, lalu mencoba membukanya dengan private key yang diinput. Jika hasilnya sama, key dianggap cocok.

## Alur

```
Simpan data
  Data pegawai (JSON)
    -> AES-256-GCM dengan kunci acak      -> ciphertext + iv + tag
    -> kunci AES dibungkus RSA public key -> wrapped_key
    -> simpan ke tabel pegawai_terenkripsi

Login dan tampil
  Admin memasukkan private key
    -> diverifikasi terhadap public key sistem
    -> wrapped_key dibuka dengan private key -> kunci AES
    -> AES-256-GCM mendekripsi ciphertext    -> data pegawai tampil di halaman admin
```

## Struktur Database

| Tabel | Isi |
|---|---|
| `pegawai_terenkripsi` | `id`, `ciphertext`, `iv`, `tag`, `wrapped_key`, `created_at`, `updated_at`. Seluruh data pegawai ada di sini dalam bentuk terenkripsi. |
| `pegawai_publik` | `pegawai_id`, `nama`, `jabatan`. Salinan data yang boleh tampil di halaman publik. NIP, email, dan telepon tidak disimpan di tabel ini. |

## Struktur Folder

```
sistem-kepegawaian/
|-- admin/
|   |-- dashboard.php
|   |-- enkripsi.php
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
```

## Kebutuhan

- Laragon (Apache dan MySQL/MariaDB)
- PHP 8.x dengan ekstensi OpenSSL aktif
- Perintah `openssl` untuk membuat pasangan kunci

## Instalasi

1. Salin folder `sistem-kepegawaian` ke `C:\laragon\www\` (atau `D:\laragon\www\`).
2. Jalankan Apache dan MySQL di Laragon, lalu import `database/kepegawaian.sql` lewat phpMyAdmin. Database `kepegawaian` beserta tabel `pegawai_terenkripsi` dan `pegawai_publik` akan terbentuk.
3. Sesuaikan koneksi di `config/database.php` bila perlu:

   ```php
   $host = 'localhost';
   $dbname = 'kepegawaian';
   $username = 'root';
   $password = '';
   ```

4. Buat pasangan kunci RSA. Simpan `private.pem` di luar folder proyek:

   ```bash
   openssl genrsa -out private.pem 2048
   openssl rsa -in private.pem -pubout -out keys/public.pem
   ```

5. Buka `http://localhost/sistem-kepegawaian/`, klik **Login Admin**, lalu tempel isi `private.pem` atau pilih filenya.

> **Catatan:** jika `keys/public.pem` diganti, data lama di database tidak bisa dibuka dengan private key yang baru dan akan tampil sebagai ciphertext. Kosongkan tabel (`TRUNCATE pegawai_terenkripsi;`) lalu input ulang datanya.

## Cara Penggunaan

1. Buka halaman publik, lihat daftar nama dan jabatan pegawai.
2. Login admin dengan private key yang **cocok**: seluruh data tampil terdekripsi dan bisa ditambah, diubah, atau dihapus.
3. Login admin dengan key **salah**: panel terbuka dalam mode terkunci dan semua data hanya tampil sebagai ciphertext.
4. Buka menu **Enkripsi & Dekripsi** untuk melihat perbandingan data asli dengan isi database.

## Catatan Keamanan

- Private key tidak boleh disimpan di dalam folder proyek maupun di-commit ke Git (sudah dikecualikan di `.gitignore`).
- Selama sesi login, private key disimpan di sesi PHP server agar halaman admin bisa mendekripsi data. Sesi dihapus saat logout.
- Gunakan HTTPS jika aplikasi dipasang di server sungguhan, karena private key dikirim saat login.
- `config/database.php` memakai akun `root` tanpa password. Ganti untuk lingkungan produksi.
- Simpan cadangan private key. Tanpa kunci ini, data terenkripsi tidak dapat dipulihkan.
