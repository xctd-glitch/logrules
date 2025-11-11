# Instalasi Terotomatisasi

Skrip `bin/install` membantu menyiapkan konfigurasi awal dengan aman dan terdokumentasi.

## Langkah Cepat

```bash
composer install --no-interaction --prefer-dist
php bin/install --db-host=localhost --db-port=3306 --db-name=smart_redirect --db-user=srp --db-pass='superSecret!'
```

Skrip akan:

- memvalidasi input (host, port, nama database, pengguna);
- menolak karakter berbahaya dan newline di setiap nilai;
- membungkus rahasia (password, API key) dengan kutip ganda serta escape aman;
- membuat `.env` secara atomik dan mengatur permission `0600`;
- menghasilkan API key acak berbasis `random_bytes()` bila tidak diberikan.

## Opsi CLI

| Opsi | Deskripsi |
| --- | --- |
| `--force` | Menimpa `.env` bila sudah ada. Pastikan Anda sadar konsekuensinya. |
| `--db-host=HOST` | Wajib. Hanya menerima alfanumerik, titik, dash, dan underscore. |
| `--db-port=PORT` | Opsional. Integer 1-65535. Default mengikuti templat. |
| `--db-name=NAME` | Wajib. Hanya menerima alfanumerik dan underscore. |
| `--db-user=USER` | Wajib. Hanya menerima alfanumerik dan underscore. |
| `--db-pass=PASS` | Opsional tapi disarankan. Nilai tidak disimpan ke history shell bila menggunakan `read -s`. |
| `--api-key=KEY` | Opsional. Bila tidak diberikan, skrip menghasilkan kunci baru. |
| `--allow-origin=ORIGIN` | Opsional, dapat diulang. Hanya `http` atau `https`. |
| `--help` | Menampilkan bantuan ringkas. |

Contoh mengizinkan beberapa origin:

```bash
php bin/install --db-host=127.0.0.1 --db-name=smart_redirect --db-user=srp --db-pass='superSecret!' \
    --allow-origin=https://dashboard.example.com --allow-origin=https://partner.example.net
```

## Pasca-Instalasi

1. Setel kredensial database pada server MySQL/MariaDB.
2. Pastikan web server menjalankan PHP 8.3 dengan ekstensi `pdo`, `json`, dan `curl`.
3. Jalankan `composer quality` sebelum deployment untuk memastikan gerbang mutu lolos.

## Troubleshooting

- **`.env` sudah ada** – Jalankan ulang dengan `--force` setelah memastikan backup.
- **Validasi gagal** – Pesan error menjelaskan nilai mana yang ditolak. Sesuaikan input.
- **Permission ditolak** – Pastikan user memiliki akses tulis ke direktori proyek.

Ikuti pedoman keamanan internal sebelum mengunggah kredensial ke sistem rahasia apa pun.
