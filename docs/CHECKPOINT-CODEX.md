# Checkpoint proyek — Tanda

Dokumen ini merangkum kondisi workspace agar pekerjaan bisa dilanjutkan di sesi Codex lain.

## Workspace

`D:\Koding-client\analisis-rea\sistem-point`

## Produk yang sudah dibuat

Tanda adalah sistem loyalty toko dan membership berbasis Laravel 12, Tailwind CSS 4, dan Alpine.js. Dokumen riset dan kebutuhan awal berada di folder `docs`:

- `PRD-sistem-poin-membership.md`
- `analisis-sistem-poin-membership.md`
- `alur-operasional-sistem-poin.md`
- `rea-evidence.json`

Fitur inti Tanda yang sudah diimplementasikan:

- Login dengan peran owner, manager, dan cashier.
- Member, pencarian member, serta pilihan persetujuan promosi.
- Checkout penjualan sederhana, perhitungan earn/redeem poin, dan pencatatan pembayaran.
- Pencegahan transaksi ganda dengan idempotency key dan transaksi database.
- Aturan poin berversi, saldo berbasis lot, FIFO untuk redeem, dan expiry terjadwal.
- Refund penuh, koreksi poin oleh owner, dan audit log.
- Dashboard, daftar transaksi, detail transaksi/struk cetak, pengelolaan staf, serta outlet.

## Status runtime terakhir

- Aplikasi terakhir dijalankan di `http://127.0.0.1:8000`.
- Login owner lokal: `owner@tanda.local`.
- Kata sandi owner lokal tidak disimpan di dokumen. Reset melalui akun owner bila diperlukan.
- Scheduler expiry dijalankan terpisah dengan `php artisan schedule:work`.
- Database runtime terakhir memakai SQLite agar aplikasi bisa dijalankan. File databasenya `database/database.sqlite`.
- Build frontend sudah dibuat di `public/build`.
- Pengujian terakhir: 8 lulus (34 assertions); build Vite berhasil.

## Menjalankan di sesi baru

Dari PowerShell pada folder workspace:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Untuk menjalankan expiry di terminal lain:

```powershell
php artisan schedule:work
```

Periksa `.env` untuk driver database yang sedang aktif. Pada akhir sesi sebelumnya `.env` memakai `DB_CONNECTION=sqlite`. Ekstensi PHP `pdo_sqlite` dan `pdo_mysql` diaktifkan di `php.ini`; backup sebelum perubahan bernama `php.ini.tanda-backup`.

## Status MySQL

MySQL belum berhasil dinyalakan. Docker tersedia, tetapi `docker run mysql:8.4` gagal mengunduh image karena DNS ke `auth.docker.io` gagal. Jangan menganggap runtime SQLite sebagai MySQL. `.env.example` berisi konfigurasi MySQL; sebelum beralih, siapkan server MySQL, buat database `tanda`, atur kredensial `.env`, lalu jalankan migrasi.

## Status integrasi POS

Pembaruan 20 November 2026: proyek POS mandiri telah dibuat di folder saudara `..\sistem-kasir`. Halaman **Koneksi POS** di Tanda menerbitkan token API; halaman **Integrasi Tanda** di kasir menguji token, memetakan outlet, menampilkan status, dan mengulang sinkronisasi. Checkout kasir dapat mencari member Tanda, mengirim penjualan lunas untuk earn poin, dan membalik poin setelah refund penuh. Penukaran poin lintas aplikasi kini memakai reservasi di Tanda sebelum transaksi kasir difinalkan. Penjualan tunai mengonfirmasi reservasi segera; QRIS mengonfirmasi setelah Midtrans menyatakan lunas. Pembatalan atau kedaluwarsa QRIS melepaskan poin dan stok. Scheduler POS `loyalty:sync` mengulang sinkronisasi dan pelepasan yang gagal serta membersihkan draft reservasi yang terhenti. Reservasi tidak dilepas otomatis di Tanda agar pembayaran yang sudah lunas tetapi belum tersinkronisasi tidak kehilangan potongan poin.

Pembaruan 21 November 2026: POS memiliki menu **Transfer stok** antar-outlet. Pengguna dengan akses menu dan izin `transfers_manage` dapat mengirim stok dari outlet aktif ke outlet lain yang diizinkan. Stok asal berkurang saat dikirim, stok tujuan bertambah setelah penerimaan dikonfirmasi, dan pembatalan sebelum penerimaan mengembalikan stok asal. Setiap langkah tercatat dalam `stock_movements` dan terhubung ke `stock_transfers`. Migrasi `2026_11_21_000001_create_stock_transfers` sudah diterapkan ke database POS lokal. Pengujian POS terakhir: 28 tes lulus (253 assertions).

Pembaruan lanjutan 21 November 2026: POS memiliki menu **Stok opname** per outlet. Kasir, manager, dan owner dapat memulai opname, menyimpan jumlah fisik (termasuk nol), dan mengajukan hasil. Manager/owner meninjau selisih dan menyetujui koreksi. Sistem menolak persetujuan bila stok outlet berubah sejak opname dimulai, sehingga opname perlu dibatalkan dan diulang. Koreksi tersambung ke `stock_movements` melalui `stock_count_id`. Satu outlet hanya boleh punya satu opname terbuka. Migrasi `2026_11_21_000002_create_stock_counts` sudah diterapkan ke database POS lokal. Pengujian POS terakhir: 32 tes lulus (304 assertions); build Vite berhasil.

Pembaruan motion 21 November 2026: UI POS memakai durasi singkat dan easing bersama untuk perpindahan halaman, kartu, tombol, tabel, modal, toast, dan navigasi. Sidebar desktop dapat diciutkan dan statusnya tersimpan di browser. Loading global ditunda 180 ms agar permintaan cepat tidak membuat overlay berkedip; pagination server menampilkan skeleton ringan jika navigasi lambat. Form HTML menampilkan validasi native di bawah input. `prefers-reduced-motion` mematikan motion. Build Vite dan 32 tes POS lulus; login dan validasi diperiksa di browser lokal tanpa error JavaScript.

Pembaruan desain 21 November 2026: antarmuka POS diperbarui menjadi ruang kerja yang lebih ringkas dengan sidebar hijau gelap, topbar outlet, metrik satu strip, tabel transaksi padat, status berbasis titik warna, tombol/form/kartu konsisten, dan halaman masuk sederhana. Dashboard, penjualan, dan laporan mendapat tata letak baru; komponen bersama menerapkan gaya pada halaman lainnya. Sidebar responsif diperiksa di desktop dan ponsel, termasuk mode ciut dan tombol keluar. Perubahan hanya pada Blade dan CSS; build Vite berhasil dan 32 tes POS lulus (304 assertions).

Pembaruan shift kasir 21 November 2026: POS memiliki menu **Shift kasir** per outlet. Kasir membuka shift dengan modal awal, mencatat uang masuk/keluar beserta keterangan, lalu menutup shift dengan jumlah uang fisik. Sistem menghitung kas seharusnya dan selisih; hasil penutupan disimpan sebagai snapshot. Penjualan tunai menambah kas sebesar nilai tagihan (bukan jumlah diterima sebelum kembalian); refund tunai mengurangi kas shift yang aktif saat refund, sehingga shift lama tidak berubah. Owner/manager dapat memantau shift seluruh kasir di outlet aktif, sedangkan kasir hanya dapat mengakses miliknya. Setelah sebuah outlet mulai memakai shift, pembayaran dan refund tunai mewajibkan shift terbuka; pembayaran non-tunai tetap dapat berjalan. Migrasi `2026_11_21_000003_create_cash_shifts` sudah diterapkan ke database POS lokal. Pengujian POS terakhir: 34 tes lulus (338 assertions).

Pembaruan pembelian 21 November 2026: POS memiliki menu **Pembelian** dan halaman **Pemasok**. Owner/manager dapat mengelola pemasok, membuat pesanan pembelian berisi produk, jumlah, dan harga beli per unit untuk outlet aktif. Pesanan dapat diterima bertahap dengan nomor surat jalan/faktur; setiap penerimaan menambah stok outlet dan mencatat `stock_movements` yang terhubung ke penerimaan. Pengiriman formulir penerimaan berulang dengan kunci yang sama tidak menggandakan stok. Jumlah melebihi sisa ditolak. Sisa pesanan dapat dibatalkan tanpa menghapus barang yang sudah diterima. Riwayat pesanan menyimpan snapshot nama produk, SKU, dan harga beli. Hak akses `purchases_manage` tersedia di Master Peran. Migrasi `2026_11_21_000004_create_purchases` sudah diterapkan ke database POS lokal. Pengujian POS terakhir: 37 tes lulus (391 assertions); Blade views berhasil di-cache.

Pembaruan peringatan stok 21 November 2026: POS memiliki menu **Stok menipis**. Batas minimum dan target disetel per produk/outlet. Dashboard menampilkan jumlah produk yang mencapai batas; halaman alert memperlihatkan stok, jumlah yang masih dalam pesanan pembelian, dan saran jumlah pemesanan. Tombol **Buat pesanan dari saran** mengisi produk dan jumlah di formulir pembelian. Penerima email (maksimal 10 per outlet) dan aktivasi email diatur dari halaman yang sama. Command `stock:alert-low` dijadwalkan setiap hari pada 08:00 Asia/Jakarta secara bawaan dan menyimpan riwayat per outlet/penerima/tanggal agar tidak mengirim dua kali sehari. Migrasi `2026_11_21_000005_create_stock_alerts` telah diterapkan ke database POS lokal. SMTP Gmail kini dikonfigurasi di `.env` lokal yang diabaikan Git; email uji ke alamat pengirim berhasil dikirim melalui SMTP pada 21 November 2026. Kredensial tidak dicatat di dokumen ini. Petunjuk ada di README POS. Pengujian POS terakhir: 40 tes lulus (432 assertions); tes stok alert diulang setelah konfigurasi SMTP dan lulus (3 tes, 41 assertions).

Pembaruan refund QRIS 21 November 2026: dari detail penjualan POS, pengguna berizin dapat mengajukan refund penuh QRIS Midtrans dengan alasan. POS menyimpan `refund_key` yang tetap agar percobaan ulang tidak membuat permintaan baru, serta menyediakan tombol periksa status dan coba lagi saat permintaan gagal. Status penjualan tetap lunas sampai notifikasi atau pemeriksaan status Midtrans memuat refund dengan kunci dan nominal cocok serta `bank_confirmed_at`; setelah itu stok dipulihkan, poin Tanda dibalik, dan status penjualan menjadi refund. Refund yang tidak cocok ditandai untuk pemeriksaan manual. QRIS manual tetap menggunakan alur refund lokal. Rekonsiliasi Midtrans yang sudah terjadwal kini memeriksa refund yang belum selesai. Migrasi `2026_11_21_000006_create_midtrans_refunds` telah diterapkan ke database POS lokal. `php artisan view:cache` berhasil; seluruh pengujian POS lulus: 47 tes (504 assertions). API Midtrans diuji dengan respons simulasi, belum dilakukan refund uang nyata. Kemampuan Refund API pada akun Midtrans mungkin perlu diaktifkan oleh Midtrans sebelum dipakai.

## Catatan teknis

- Stack yang diminta pengguna: Laravel + MySQL + Tailwind CSS + Alpine.js.
- Portal member tersedia melalui `/portal/{id_bisnis}` dengan OTP sekali pakai, saldo, poin terdekat kedaluwarsa, dan riwayat ledger. Driver lokal mencatat OTP ke log hanya di lingkungan `local`/`testing`; produksi perlu WhatsApp Cloud API dan template authentication yang disetujui. Migrasi `2026_11_21_000002_create_member_login_codes` telah diterapkan ke database lokal.
- Sebagian fitur PRD masih tahap lanjutan, termasuk konfigurasi pengiriman WhatsApp nyata, payment gateway pada checkout Tanda, dan ekspor laporan.
- Detail setup dan batas implementasi ada di `README.md`.
