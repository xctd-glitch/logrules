# Smart Redirect Platform

Smart Redirect Platform (SRP) menyediakan REST API dan dashboard progresif untuk mengelola alur redirect berbasis negara, performa eksperimen A/B, serta monitoring klik secara waktu nyata. Seluruh komponen ditulis dengan PHP 8.3, mengikuti PSR-12, dan dipaketkan menggunakan Composer agar mudah diintegrasikan ke dalam infrastruktur produksi.

## Daftar Isi
- [Arsitektur & Implementasi](#arsitektur--implementasi)
- [Prasyarat Sistem](#prasyarat-sistem)
- [Instalasi & Setup](#instalasi--setup)
- [Konfigurasi Lingkungan](#konfigurasi-lingkungan)
- [Menjalankan & Eksekusi](#menjalankan--eksekusi)
- [Fitur Aplikasi](#fitur-aplikasi)
- [Dokumentasi REST API](#dokumentasi-rest-api)
- [Keamanan](#keamanan)
- [Cara Penggunaan Dashboard](#cara-penggunaan-dashboard)
- [Penerapan / Deployment](#penerapan--deployment)
- [Pemecahan Masalah](#pemecahan-masalah)
- [Mutu Kode & Quality Gate](#mutu-kode--quality-gate)
- [Lisensi](#lisensi)

## Arsitektur & Implementasi
SRP terdiri atas beberapa entry point utama:

- `index.php` sebagai dashboard administratif dengan dukungan PWA (`manifest.webmanifest`, `sw.js.php`, dan `offline.php`).
- `api.php` yang mengekspos REST API v1 (lihat [openapi.yaml](openapi.yaml)) menggunakan routing berbasis path sederhana.
- `data.php`, `decision.php`, dan `realtime.php` menyediakan data tambahan untuk dashboard.
- `_bootstrap.php` memuat konfigurasi lingkungan, koneksi PDO, helper keamanan, serta registrasi autoloader Composer.

Seluruh model dan utilitas disimpan di direktori `src/`, sedangkan pengujian berada di `tests/`. File konfigurasi mutu seperti `phpunit.xml.dist`, `phpstan.neon.dist`, dan `phpcs.xml.dist` memastikan standar kualitas terpenuhi.

## Prasyarat Sistem
- PHP 8.3 dengan ekstensi `pdo`, `json`, dan `curl`.
- Composer 2.7 atau lebih baru.
- MySQL 8.0 (atau MariaDB kompatibel JSON) dengan akses `CREATE`, `ALTER`, dan `SELECT`.
- Server web (Apache/Nginx) yang mengarahkan dokumen root ke direktori proyek ini serta mengizinkan penulisan file cache di `var/`.

## Instalasi & Setup
1. **Kloning repositori**
   ```bash
   git clone <repository-url>
   cd comp
   ```
2. **Pasang dependensi PHP**
   ```bash
   composer install --no-interaction --prefer-dist
   ```
3. **Jalankan wizard instalasi** menggunakan perintah berikut untuk membuat `.env` dan mengisi kredensial database/API secara aman:
   ```bash
   php bin/install \
       --db-host=localhost \
       --db-port=3306 \
       --db-name=smart_redirect \
       --db-user=srp \
       --db-pass='secret'
   ```
   Opsi tambahan tersedia di `php bin/install --help`. Jalankan ulang dengan `--force` bila ingin menimpa konfigurasi yang ada.
4. **Setel izin file** sehingga user web server dapat menulis ke `var/` dan membaca `vendor/`.
5. **Konfigurasikan virtual host** untuk mengarahkan permintaan API ke `api.php` dan dashboard ke `index.php`. Pada Nginx, gunakan blok `try_files` untuk menangani service worker.

## Konfigurasi Lingkungan
- Gunakan file `.env` yang dihasilkan wizard untuk mendefinisikan variabel seperti `DATABASE_URL`, `API_KEY`, `APP_ENV`, dan `APP_DEBUG`.
- Pastikan `APP_DEBUG` bernilai `0` di lingkungan produksi.
- Untuk koneksi database, driver PDO akan mengatur `PDO::ATTR_EMULATE_PREPARES=false` demi keamanan.
- Tambahkan konfigurasi caching atau logging tambahan melalui konstanta environment yang dibaca di `_bootstrap.php`.

## Menjalankan & Eksekusi
- **Dashboard**: akses `https://<host>/index.php` melalui browser modern untuk pengalaman PWA.
- **REST API**: arahkan permintaan ke `https://<host>/api.php/v1/...` sesuai dokumentasi di bawah.
- **Monitoring Real-time**: halaman `realtime.php` memberikan feed klik menggunakan long polling endpoint `clicks`.
- **Testing & linting**: gunakan skrip Composer pada bagian [Mutu Kode](#mutu-kode--quality-gate).

## Fitur Aplikasi
- Manajemen redirect berdasarkan negara dan parameter eksperimen A/B.
- Pelacakan klik dan statistik periode berjalan secara real-time.
- Dashboard PWA yang dapat dipasang di perangkat desktop maupun mobile.
- API kesehatan layanan (`health`) untuk integrasi monitoring.
- Service worker untuk mode offline dan precache konten penting.

## Dokumentasi REST API
Seluruh endpoint berada di bawah prefix `/api.php/v1` dan membutuhkan header `X-API-Key` kecuali disebutkan sebaliknya. Spesifikasi rinci tersedia pada [openapi.yaml](openapi.yaml).

| Endpoint | Metode | Deskripsi | Parameter Utama | Respons Utama |
|----------|--------|-----------|-----------------|----------------|
| `/health` | `GET` | Pemeriksaan liveness. | – | `200 OK` dengan status layanan. |
| `/decision` | `POST` | Menghasilkan keputusan redirect berbasis negara/A/B testing. | Body JSON: `click_id`, `country_code`, `user_agent`, `ip_address`, `user_lp`. | `200 OK` dengan tujuan redirect dan metadata percobaan. |
| `/config` | `GET` | Mengambil konfigurasi baca-saja untuk klien. | – | `200 OK` dengan konfigurasi saat ini. |
| `/stats` | `GET` | Statistik agregat dalam jendela menit. | Query `window` (1-60, default 15). | `200 OK` dengan metrik performa. |
| `/clicks` | `GET` | Long polling klik baru setelah ID tertentu. | Query `after_id` (>=0), `timeout` (1-25 detik). | `200 OK` dengan daftar klik terbaru. |

Setiap respons menggunakan konten JSON dan header keamanan (`Content-Security-Policy`, `X-Frame-Options`, dll.) yang diset di `api.php`.

## Keamanan
- Autentikasi API menggunakan header `X-API-Key` dengan kunci yang disimpan di `.env`.
- Input divalidasi ketat sesuai pola pada [openapi.yaml](openapi.yaml); data dilewatkan ke PDO prepared statements untuk mencegah SQL injection.
- Output HTML pada dashboard diekspose melalui helper yang melakukan escaping agar aman terhadap XSS.
- Service worker dan manifest memaksa HTTPS untuk menghindari mixed content.
- Untuk form interaktif, sertakan token CSRF yang disediakan bootstrap dan validasi setiap permintaan.
- Rutin rotasi API key dan pembatasan IP disarankan untuk lingkungan produksi.

## Cara Penggunaan Dashboard
1. Login menggunakan kredensial admin (atur melalui modul autentikasi Anda).
2. Konfigurasi aturan redirect melalui menu pengaturan yang memanfaatkan endpoint `config` dan `decision`.
3. Pantau hasil eksperimen pada halaman statistik atau gunakan `realtime.php` untuk feed klik.
4. Unduh laporan atau sinkronkan data melalui integrasi yang memanggil endpoint `stats`.

## Penerapan / Deployment
- Gunakan pipeline CI/CD untuk menjalankan gerbang mutu (`composer quality`) sebelum rilis.
- Terapkan server web dengan TLS dan HTTP/2, serta aktifkan kompresi `gzip`/`brotli` untuk aset `assets/`.
- Pastikan direktori `var/` dan `vendor/` diperlakukan sesuai kebijakan backup dan permission.
- Gunakan variabel environment pada platform hosting (Docker/Kubernetes) untuk menginjeksikan kredensial aman.

## Pemecahan Masalah
| Gejala | Penyebab Umum | Solusi |
|--------|---------------|--------|
| API mengembalikan `401 Unauthorized`. | Header `X-API-Key` hilang atau salah. | Periksa nilai `API_KEY` pada `.env` dan middleware autentikasi. |
| Gagal konek database saat instalasi. | Kredensial salah atau user tidak memiliki hak `CREATE`. | Pastikan parameter wizard benar dan hak akses database memadai. |
| Service worker tidak memuat. | Server tidak menyajikan header `Service-Worker-Allowed`. | Tambahkan konfigurasi server agar mengizinkan scope root. |
| Dashboard tidak memperbarui data real-time. | Long polling `clicks` diblokir firewall atau time-out terlalu pendek. | Buka port yang relevan dan gunakan parameter `timeout` default. |
| `composer quality` gagal. | Pelanggaran standar kode atau pengujian gagal. | Jalankan skrip secara individual (`test`, `analyse`, `lint`) untuk mengidentifikasi masalah. |

## Mutu Kode & Quality Gate
Composer menyediakan skrip berikut untuk menjaga mutu:

- `composer test` menjalankan PHPUnit dengan kegagalan pada peringatan.
- `composer analyse` menjalankan PHPStan level maksimum (default 8).
- `composer lint` menjalankan PHPCS dan php-cs-fixer (dry-run) dengan standar PSR-12.
- `composer quality` menjalankan seluruh gerbang mutu di atas secara berurutan.

Seluruh skrip diharapkan bebas dari peringatan sebelum kode digabungkan ke cabang utama.

## Lisensi
Hak cipta privat. Konsultasikan pemilik repositori untuk penggunaan ulang.
