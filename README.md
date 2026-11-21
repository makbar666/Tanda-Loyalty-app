# Tanda

Sistem poin toko dan membership berbasis Laravel 12, MySQL, Tailwind CSS 4, dan Alpine.js. Rancangan produk, alur, analisis vendor, dan bukti REA berada di [`docs`](docs).

## Menjalankan aplikasi

Prasyarat: PHP 8.2+ dengan `pdo_mysql`, Composer, Node.js, dan MySQL. Buat database kosong bernama `tanda`, lalu isi kredensial MySQL di `.env`.

```bash
composer install
npm install
npm run build
php artisan key:generate
php artisan migrate
php artisan loyalty:install
php artisan serve
```

Perintah `loyalty:install` meminta nama bisnis, outlet pertama, email owner, dan kata sandi owner. Perintah ini membuat aturan contoh dari PRD: 1 poin per Rp10.000, nilai tukar Rp100, minimum redeem 10 poin, batas 50% transaksi, masa berlaku 365 hari, serta persetujuan manajer mulai 100 poin. Ubah angka di halaman **Program poin** sebelum transaksi nyata.

Jalankan scheduler untuk menutup lot yang kedaluwarsa:

```bash
php artisan schedule:work
```

Di server, jalankan `php artisan schedule:run` setiap menit lewat cron atau Task Scheduler.

## Alur yang tersedia

- Owner membuat outlet, staf, dan versi aturan poin baru; melihat dashboard dan audit.
- Kasir mencari atau mendaftarkan member, lalu mencatat transaksi yang **sudah dibayar**. Checkout tanpa member tetap tersedia.
- Redeem divalidasi ulang saat finalisasi, mengambil lot yang paling cepat kedaluwarsa, dan memakai transaksi database. Kunci idempoten mencegah pencatatan ulang pada retry.
- Manajer dan owner mencatat refund penuh setelah dana dikembalikan. Jika poin hasil transaksi sudah dipakai, selisih menjadi utang poin yang ditutup oleh perolehan berikutnya.
- Owner dapat mengoreksi poin dengan alasan; seluruh mutasi bisa ditelusuri di ledger member.
- Member dapat masuk ke portal mandiri lewat kode sekali pakai, melihat saldo, poin yang akan kedaluwarsa, dan riwayat perubahan poin. Tautan portal tersedia di detail member staf dan berlaku untuk seluruh member dalam bisnis yang sama.

## Portal member

Portal berada di `/portal/{id_bisnis}`. Member masuk memakai nomor HP terdaftar. Nomor yang tidak ditemukan mendapat respons umum; kode berlaku 5 menit, satu kali pakai, dibatasi 5 percobaan dan 5 permintaan per 10 menit per nomor. Saldo dan riwayat diambil langsung dari data poin Tanda; akses antar bisnis dipisahkan.

Untuk pengembangan lokal, `MEMBER_OTP_DRIVER=log` menulis kode ke `storage/logs/laravel.log`. Jangan gunakan driver ini pada produksi. Pengiriman produksi memakai WhatsApp Cloud API: atur `MEMBER_OTP_DRIVER=whatsapp`, `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_OTP_TEMPLATE`, dan `WHATSAPP_OTP_LANGUAGE` di `.env`. Buat dan setujui template **authentication** dengan satu parameter kode pada akun WhatsApp Business Anda. Konfigurasi template dan kredensial provider belum tersedia di repositori; pengiriman WhatsApp nyata belum diuji.

## Batas implementasi saat ini

Integrasi POS eksternal tersedia untuk pencarian member, pencatatan perolehan poin dari penjualan lunas, reservasi dan konfirmasi penukaran poin, serta pembalikan poin setelah refund penuh. Owner membuat token pada halaman **Koneksi POS**. POS menyimpan ID penjualan stabil dan mengirim ulang dengan kunci yang sama; Tanda menolak isi transaksi yang berbeda untuk ID yang telah dipakai.

Qazir dapat memeriksa kesesuaian transaksi melalui endpoint token `POST /api/v1/pos/reconciliation`. Tanda mengembalikan transaksi dan reservasi yang terhubung pada token POS tersebut, maksimal 100 ID per permintaan. Pemeriksaan dan riwayat hasil berada di halaman **Integrasi Tanda → Rekonsiliasi poin** pada Qazir, karena Qazir juga mengetahui penjualan yang belum pernah diterima Tanda.


Pembayaran pada checkout Tanda dikonfirmasi manual oleh kasir. Persetujuan penukaran besar dilakukan dengan masuk sebagai manajer/owner. Pengiriman WhatsApp portal memerlukan konfigurasi provider; diskon membership dan ekspor laporan masih tahap berikutnya sesuai PRD. Seluruh nominal memakai integer rupiah.

## Verifikasi

```bash
php artisan test --filter=PointFlowTest
```

Pengujian menggunakan SQLite dalam memori agar cepat; konfigurasi aplikasi utama memakai MySQL. Pada lingkungan Windows ini ekstensi SQLite belum diaktifkan di `php.ini`, sehingga pengujian dijalankan langsung dengan `php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit --filter PointFlowTest`.
