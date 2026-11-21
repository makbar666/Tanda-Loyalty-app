# Rancangan POS dan integrasi Tanda

Status: integrasi perolehan poin, penukaran poin dengan reservasi, dan refund penuh telah diimplementasikan pada 20 November 2026.

## Keputusan

Bangun POS sebagai proyek Laravel tersendiri di folder saudara `sistem-point`, misalnya `sistem-kasir`. POS dan Tanda memiliki database, migrasi, akun layanan, proses deployment, dan paket penjualan masing-masing. Integrasi Tanda bersifat opsional melalui API versi 1. POS tetap dapat dipakai tanpa Tanda; Tanda tetap dapat dipakai bersama POS lain atau checkout manualnya sendiri.

Jangan memakai tabel Tanda secara langsung dari POS. Jangan menyalin ledger atau rumus poin ke POS. Tanda menjadi pemilik member, aturan, saldo, dan mutasi poin. POS menjadi pemilik katalog, harga jual, stok, keranjang, pembayaran, penjualan, retur, dan laporan penjualan. Keduanya menyimpan referensi transaksi pihak lain untuk rekonsiliasi.

## Batas data

| Data/proses | Pemilik | Catatan |
|---|---|---|
| Produk, SKU, kategori, harga, pajak, diskon | POS | Harga dan aturan yang dipakai disimpan sebagai snapshot item penjualan. |
| Stok dan pergerakan stok | POS | Stok berubah sekali saat penjualan final atau retur berhasil. |
| Penjualan, pembayaran, struk, refund uang | POS | ID penjualan POS stabil dan unik dalam satu bisnis. |
| Identitas member, aturan, lot, ledger poin | Tanda | POS hanya menyimpan ID member Tanda dan snapshot informasi untuk struk. |
| Outlet dan staf | Masing-masing | Buat pemetaan ID outlet; autentikasi kasir POS tidak memakai sesi web Tanda. |

## Kontrak integrasi minimum

- Kredensial API terikat pada satu `business_id`, punya izin spesifik, dan dikirim melalui HTTPS. Tanda menentukan tenant dari kredensial, bukan dari `business_id` yang dikirim POS.
- POS memetakan outlet lokal ke outlet Tanda. Permintaan untuk outlet yang tidak dipetakan ditolak.
- Pencarian/pendaftaran member dan pembacaan saldo dilakukan melalui API Tanda, dengan akses data seminimal mungkin.
- Preview poin mengirim item, kuantitas, harga, diskon, serta status eligible. Hasilnya perkiraan; Tanda menghitung ulang saat finalisasi.
- Finalisasi mengirim `external_sale_id`, versi payload, item dan diskon yang sudah dibayar, metode pembayaran, `paid_at`, serta `idempotency_key` stabil. Tanda memberi satu hasil untuk retry yang sama dan menolak ID penjualan sama dengan isi berbeda.
- Refund POS atas penjualan yang terhubung harus mengirim ID refund stabil ke Tanda setelah refund uang berhasil. Retry tidak boleh membalik poin dua kali.
- POS menyimpan status sinkronisasi `pending`, `synced`, atau `failed`, percobaan terakhir, pesan galat, dan ID transaksi Tanda. Job antrean mengulang kegagalan sementara; owner mendapat daftar yang perlu ditangani.

## Urutan checkout

1. Kasir menyusun keranjang di POS. POS menghitung tagihan dan, bila integrasi aktif serta member dipilih, meminta preview ke Tanda.
2. POS memfinalkan pembayaran dan penjualan sekali. POS mencatat stok, pembayaran, dan tugas sinkronisasi loyalty dalam satu transaksi database lokal.
3. Worker mengirim transaksi berbayar ke Tanda memakai ID penjualan yang tetap. Tanda mencatat earn satu kali. Struk menampilkan poin sebagai *menunggu sinkronisasi* sampai Tanda mengonfirmasi.
4. Jika Tanda tidak tersedia, penjualan tanpa redeem tetap selesai dan tugas sinkronisasi diulang. Kegagalan permanen masuk daftar rekonsiliasi.

Redeem kini memakai endpoint reservasi, konfirmasi melalui finalisasi penjualan, dan pelepasan saat pembayaran gagal. Tanda mengurangi saldo tersedia saat reservasi sehingga dua kasir tidak dapat memakai poin yang sama. POS menyimpan draft sebelum meminta reservasi; jika gagal, stok dikembalikan. Reservasi tetap tertahan sampai konfirmasi atau pelepasan eksplisit; scheduler POS mengulang kegagalan dan membersihkan draft terhenti. Untuk operasional nyata, proses scheduler kedua aplikasi harus aktif dan transaksi QRIS yang masuk status `payment_review` tetap memerlukan rekonsiliasi manual sebelum barang diserahkan.

## Tahap pembangunan

1. POS mandiri: bisnis/outlet, staf dan hak akses, produk/SKU, stok awal dan penyesuaian, checkout, pembayaran manual, struk, refund penuh, laporan penjualan harian. Gunakan integer rupiah dan jejak audit.
2. API Tanda versi 1: autentikasi layanan, pemetaan outlet, member, preview, finalisasi earn berbayar, refund loyalty, ID eksternal unik dan pemeriksaan payload retry.
3. Adapter POS: konfigurasi integrasi opsional, outbox lokal, worker retry, status loyalty di transaksi/struk, layar rekonsiliasi.
4. Redeem: reservasi dan pembatalan di Tanda, alur pembayaran POS, penanganan timeout, uji dua kasir serentak dan refund.

## Kondisi kode saat keputusan dibuat

Tanda sudah memiliki checkout sederhana dan `PointService` dengan idempotency key. API `/api/v1/pos` kini memakai token layanan, dan `pos_sale_links` memetakan ID penjualan POS ke transaksi Tanda. POS tidak menulis langsung ke database Tanda. Reservasi poin dan verifikasi pembayaran otomatis belum tersedia; operator masih mengonfirmasi pembayaran secara manual. Checkout manual Tanda tetap tersedia bagi pelanggan yang hanya membeli Tanda.
