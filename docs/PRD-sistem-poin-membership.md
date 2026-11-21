# PRD — Sistem Poin Toko dan Membership

Versi: 1.0 (draft implementasi)  
Tanggal: 20 November 2026  
Dasar riset: [analisis REA](./analisis-sistem-poin-membership.md) dan [bundle bukti REA](./rea-evidence.json)

## 1. Ringkasan produk

Sistem ini membantu satu bisnis mengenali pelanggan, memberi poin dari transaksi yang sudah dibayar, dan menukar poin menjadi potongan belanja pada kunjungan berikutnya. Pemilik mengelola aturan, melihat kewajiban poin dan aktivitas member; kasir mengidentifikasi member serta memproses transaksi; pelanggan melihat saldo dan riwayat melalui halaman member. Saldo berlaku di seluruh outlet milik **bisnis yang sama**.

PRD ini adalah **rancangan produk**, bukan salinan perilaku empat layanan yang diteliti. Poindo memberi inspirasi identifikasi nomor WhatsApp, Cazh dan Qasio memberi contoh integrasi POS/CRM, sedangkan dokumentasi Qasir memberi contoh aturan minimum belanja, kelipatan, redeem di checkout, serta struk dan laporan. Detail yang tidak terbukti dari akses publik ditetapkan di sini sebagai keputusan desain yang dapat diubah sebelum pengembangan.

### Masalah yang diselesaikan

- Kasir sering tidak menghubungkan transaksi dengan pelanggan; riwayat kunjungan sulit diukur.
- Perhitungan poin manual rawan lupa, salah hitung, dan penukaran ganda.
- Pelanggan tidak tahu saldo, nilai poin, atau batas kedaluwarsanya.
- Pemilik tidak punya laporan poin yang diterbitkan, digunakan, kedaluwarsa, dan masih menjadi kewajiban bisnis.

### Sasaran

1. Kasir dapat mendaftarkan/menemukan member dan menyelesaikan checkout tanpa langkah tambahan yang panjang.
2. Kredit, debit, kedaluwarsa, dan pembalikan poin dapat ditelusuri ke transaksi asal.
3. Saldo yang dilihat kasir, pelanggan, dan pemilik konsisten.
4. Pemilik dapat mengubah aturan untuk transaksi **mendatang** tanpa mengubah histori.
5. Penukaran tidak dapat melebihi saldo atau diproses dua kali.

### Di luar MVP

- Penukaran lintas bisnis/merchant dan settlement antar merchant.
- Tier Silver/Gold, stamp, cashback uang elektronik, katalog hadiah fisik.
- WhatsApp blast, automasi kampanye, dan aplikasi seluler khusus pelanggan.
- Transaksi offline dan redeem tanpa koneksi.
- Refund parsial, split tender kompleks, dan migrasi saldo dari sistem lama.

## 2. Pengguna dan hak akses

| Peran | Kebutuhan | Hak utama |
|---|---|---|
| Owner/admin bisnis | Mengatur program dan mengaudit hasil | Kelola outlet, aturan, staf; lihat seluruh laporan; koreksi poin dengan alasan |
| Manajer outlet | Mengawasi operasional outlet | Lihat transaksi/member outlet; proses refund sesuai izin owner |
| Kasir | Checkout cepat dan akurat | Cari/daftar member; lihat saldo; redeem; finalisasi transaksi; lihat riwayat transaksi sendiri/outlet |
| Pelanggan/member | Mengetahui manfaat dan histori | Lihat saldo, nilai tukar, poin akan kedaluwarsa, riwayat; kelola izin komunikasi |

Setiap akses data dibatasi `business_id`; kasir dan manajer juga dibatasi outlet yang ditugaskan. Nomor HP tidak menjadi bukti kepemilikan akun saat redeem bernilai besar; mekanisme verifikasi ditentukan pada bagian keamanan.

## 3. Keputusan produk dan asumsi awal

| Kode | Keputusan MVP | Alasan |
|---|---|---|
| K-01 | Satu akun member per nomor HP yang telah dinormalisasi **di dalam satu bisnis** | Memudahkan pencarian dan mencegah duplikat lokal |
| K-02 | Saldo poin bersama untuk semua outlet bisnis tersebut | Pengalaman member konsisten saat pindah cabang |
| K-03 | Poin hanya dikreditkan setelah transaksi berstatus `paid` | Mencegah poin dari keranjang/pesanan batal |
| K-04 | Penukaran hanya online dan atomik saat finalisasi pembayaran | Mencegah saldo dipakai dua kasir bersamaan |
| K-05 | Perubahan aturan memakai versi dan mulai berlaku pada waktu yang ditetapkan | Histori tetap dapat diaudit |
| K-06 | Poin yang paling cepat kedaluwarsa dipakai lebih dulu | Mengurangi poin hangus tanpa sengaja |
| K-07 | Hanya refund penuh dalam MVP | Pembalikan poin lebih jelas; refund parsial menyusul |

### Aturan default untuk prototipe

Angka ini adalah **contoh konfigurasi**, bukan fakta dari vendor: setiap Rp10.000 belanja yang memenuhi syarat menghasilkan 1 poin; 1 poin bernilai Rp100 saat redeem; minimum redeem 10 poin; maksimum redeem 50% dari nilai belanja yang memenuhi syarat; poin berlaku 365 hari sejak transaksi lunas. Owner dapat mengganti angka sebelum program diaktifkan.

**Basis belanja yang memenuhi syarat** = subtotal produk yang diikutkan program − diskon produk − diskon transaksi − nilai potongan poin. Pajak, biaya layanan, ongkir, tip, dan produk yang dikecualikan tidak menghasilkan poin. Perolehan = `floor(basis / kelipatan_rupiah) × poin_per_kelipatan`. Bila basis di bawah minimum belanja, perolehan 0. Penukaran harus memenuhi minimum, saldo tersedia, batas 50%, dan tidak melebihi tagihan produk setelah diskon. Semua uang disimpan sebagai integer rupiah; tidak memakai floating point.

Contoh: subtotal produk eligible Rp120.000, tanpa diskon, redeem 20 poin senilai Rp2.000. Basis poin Rp118.000. Dengan aturan contoh, pelanggan mendapat 11 poin setelah pembayaran lunas. Perhitungan dan versi aturan tercetak di detail transaksi.

## 4. Kebutuhan fungsional

Prioritas `P0` wajib untuk MVP, `P1` setelah MVP, `P2` opsi berikutnya.

| ID | Prioritas | Kebutuhan | Kriteria penerimaan ringkas |
|---|---|---|---|
| F-01 | P0 | Daftar member dari kasir dengan nama dan nomor HP | Nomor dinormalisasi; duplikat dalam bisnis diarahkan ke member yang ada; persetujuan promo dicatat terpisah |
| F-02 | P0 | Cari member lewat nomor HP atau kode member/QR | Hasil menampilkan nama tersamar seperlunya, saldo, dan status; pencarian tidak membuka data bisnis lain |
| F-03 | P0 | Atur program poin | Owner mengisi rasio, minimum belanja, nilai redeem, minimum/maksimum redeem, masa berlaku, produk eligible, dan tanggal efektif |
| F-04 | P0 | Simulasi aturan sebelum aktif | Owner melihat contoh earn/redeem untuk nominal tertentu dan menyetujui versi aturan |
| F-05 | P0 | Kredit poin otomatis | Satu transaksi `paid` menghasilkan paling banyak satu event kredit; poin dan expiry tercatat dengan versi aturan |
| F-06 | P0 | Redeem di checkout | Kasir melihat saldo dan rupiah setara; sistem menolak jumlah di luar batas; debit dilakukan atomik dengan pembayaran |
| F-07 | P0 | Struk dan detail transaksi | Tampilkan member, poin dipakai, poin diperoleh, saldo setelah transaksi, serta ID transaksi |
| F-08 | P0 | Riwayat member dan ledger | Semua earn, redeem, expire, refund, dan koreksi memiliki waktu, nilai, sumber, operator, alasan bila perlu |
| F-09 | P0 | Kedaluwarsa poin | Job terjadwal menutup lot kedaluwarsa sekali saja; saldo dan laporan berubah konsisten |
| F-10 | P0 | Refund penuh | Refund membalik poin yang diperoleh dan mengembalikan poin yang pernah dipakai sesuai kebijakan expiry; tidak boleh diproses dua kali |
| F-11 | P0 | Dashboard owner | Tampilkan member aktif, transaksi member, poin diterbitkan, digunakan, expired, saldo beredar, dan estimasi nilai rupiah |
| F-12 | P0 | Hak akses dan audit | Owner mengatur peran; tindakan sensitif menyimpan aktor, waktu, outlet, perubahan sebelum/sesudah |
| F-13 | P1 | Portal member via OTP | Pelanggan melihat saldo, riwayat, nilai tukar, dan poin mendekati expiry tanpa akses data member lain |
| F-14 | P1 | Diskon khusus member | Atur nominal/persen, periode, minimum transaksi, outlet, dan aturan kombinasi dengan poin |
| F-15 | P1 | Ekspor laporan | Filter periode/outlet lalu ekspor CSV/XLSX dengan definisi metrik yang sama dengan dashboard |
| F-16 | P1 | Notifikasi transaksional WhatsApp | Kirim earn/redeem/expiry sesuai izin dan ketersediaan kanal; kegagalan pesan tidak membatalkan transaksi |
| F-17 | P2 | Segmentasi dan kampanye | Target berdasar kunjungan/aktivitas dengan izin promo dan pelacakan hasil |
| F-18 | P2 | Multi-merchant | Ledger dan settlement terpisah, setelah aturan bisnis lintas merchant disepakati |

## 5. Alur inti

Rincian langkah, diagram, dan pengecualian tersedia di [alur-operasional-sistem-poin.md](./alur-operasional-sistem-poin.md).

1. **Aktivasi:** owner membuat aturan → simulasi → aktifkan versi baru.
2. **Daftar/temukan member:** kasir meminta nomor HP → cari → pilih atau daftar → tampilkan saldo.
3. **Earn:** kasir membuat transaksi → sistem menghitung perkiraan poin → pembayaran lunas → ledger kredit tercatat → struk terbit.
4. **Redeem:** kasir memilih member → memilih jumlah poin → sistem menampilkan potongan → pembayaran dikonfirmasi → debit dan kredit hasil transaksi dicatat bersama.
5. **Refund:** operator berizin memilih transaksi lunas → konfirmasi refund penuh → transaksi dibalik satu kali → poin hasil pembelian ditarik dan poin yang dipakai dikembalikan.

## 6. Aturan bisnis dan kasus tepi

| Kasus | Perilaku yang diwajibkan |
|---|---|
| Dua kasir menukar poin member yang sama | Hanya satu operasi yang dapat memakai saldo yang sama; operasi kedua menghitung ulang atau ditolak dengan saldo terbaru |
| Submit pembayaran berulang akibat jaringan | `idempotency_key` dan `external_transaction_id` menghasilkan satu transaksi, satu debit, satu kredit |
| Pembayaran gagal setelah reservasi poin | Reservasi dibatalkan dan saldo tersedia pulih; tidak ada ledger redeem final |
| Refund setelah poin hasil transaksi sudah dipakai | Refund tetap berjalan; ledger boleh mencatat utang poin, saldo tersedia dibatasi minimum 0, perolehan berikutnya menutup utang sebelum bisa redeem |
| Poin hasil redeem direfund setelah masa aktif aslinya habis | Lot dikembalikan dengan tanggal expiry asal; bagian yang sudah expired tidak menjadi saldo tersedia |
| Nomor HP berubah | Hanya owner atau pelanggan terverifikasi yang mengubah; histori dan ID member tidak berubah |
| Poin kedaluwarsa saat checkout | Validasi ulang tepat saat finalisasi, bukan mengandalkan saldo yang tampil beberapa menit lalu |
| Perubahan aturan di tengah transaksi | Transaksi memakai versi aturan yang berlaku pada saat pembayaran final; preview diperbarui jika versi berubah |
| Diskon dan poin | Urutan: harga item → diskon item → diskon transaksi/member → redeem poin → pajak/biaya sesuai konfigurasi POS; perolehan memakai basis eligible setelah redeem |
| Produk retur atau refund parsial | Tidak tersedia pada MVP; kasir diarahkan ke refund penuh dan transaksi pengganti |

## 7. Data inti dan integrasi

Entitas minimum: `business`, `outlet`, `staff_user`, `member`, `member_consent`, `point_rule_version`, `transaction`, `transaction_item`, `payment`, `point_lot`, `point_ledger_entry`, `point_redemption`, `audit_log`, `notification_job`.

Setiap ledger entry memiliki `business_id`, `member_id`, `type`, `points_delta`, `source_transaction_id`, `rule_version_id`, `occurred_at`, `expires_at` bila relevan, dan `idempotency_key`. Transaksi menyimpan snapshot nominal perhitungan agar hasil lama tetap dapat direproduksi setelah aturan berubah. `point_lot` melacak asal dan expiry poin untuk konsumsi FIFO; saldo tersedia merupakan hasil yang dapat direkonsiliasi dengan ledger.

Integrasi POS minimum: upsert member, preview earn/redeem, finalize paid transaction, refund, dan baca saldo/riwayat. Finalisasi harus transactional di server. Jika POS eksternal digunakan, adapter harus menyediakan ID transaksi stabil, status pembayaran final, identitas outlet, daftar item, diskon, serta webhook refund. POS yang tidak memberi status final tidak boleh mengkredit poin otomatis.

## 8. Keamanan, privasi, dan reliabilitas

- Pemisahan tenant wajib pada query, API, ekspor, dan pekerjaan terjadwal.
- Data nomor HP dibatasi akses; komunikasi pemasaran hanya untuk member yang setuju. Persetujuan menyimpan versi teks, waktu, dan sumber.
- Redeem di atas ambang yang diatur owner memerlukan OTP atau persetujuan manajer; kasir tidak dapat melakukan koreksi saldo sendiri.
- Semua mutasi poin menggunakan transaksi database, optimistic/row locking yang sesuai, dan idempotency key.
- Log audit tidak boleh dapat diubah oleh kasir; data sensitif tidak dicetak lengkap di struk.
- Target layanan MVP: checkout loyalty p95 < 2 detik di jaringan normal; saldo baru terlihat setelah pembayaran final; pekerjaan expiry selesai setiap hari dan dapat dijalankan ulang tanpa duplikasi.
- Saat layanan loyalty tidak tersedia, checkout tanpa redeem tetap dapat berjalan sesuai kebijakan bisnis; poin pending diproses hanya dari transaksi lunas yang memiliki ID unik dan perlu ditandai jelas pada struk.

## 9. Metrik keberhasilan

| Metrik | Definisi | Sasaran pilot 8 minggu (hipotesis) |
|---|---|---|
| Transaksi teridentifikasi | Transaksi berbayar dengan member / seluruh transaksi berbayar | ≥ 40% |
| Repeat purchase 30 hari | Member yang kembali bertransaksi dalam 30 hari / member bertransaksi periode awal | Naik ≥ 10% terhadap baseline |
| Error ledger | Selisih saldo proyeksi vs rekalkulasi ledger | 0 transaksi tanpa resolusi |
| Duplikasi kredit/debit | Event poin ganda untuk satu aksi bisnis | 0 |
| Keberhasilan redeem | Redeem sukses / percobaan redeem valid | ≥ 99% di luar gangguan pembayaran |
| Pemakaian poin | Poin ditukar / poin diterbitkan pada kohort yang sudah memenuhi syarat | Dipantau; sasaran ditentukan setelah baseline |

Angka pilot adalah sasaran internal yang perlu disesuaikan dengan frekuensi belanja dan margin bisnis. Jangan menafsirkan kenaikan repeat purchase sebagai dampak kausal program tanpa kelompok pembanding.

## 10. Rilis dan kriteria selesai

**Rilis 1 — pilot satu bisnis:** F-01 s.d. F-12, satu atau beberapa outlet, laporan owner, refund penuh, dan audit. **Rilis 1.1:** portal member, diskon membership, ekspor, dan notifikasi. **Rilis 2:** kampanye, refund parsial, tier, serta evaluasi lintas merchant.

MVP siap pilot jika: seluruh skenario P0 pada dokumen alur lulus; saldo ledger dan dashboard cocok pada data uji; dua checkout serentak tidak membuat saldo negatif tersedia; retry pembayaran/refund tidak menggandakan poin; izin peran dan tenant diuji; aturan serta nilai tukar ditampilkan jelas kepada kasir dan pelanggan.

## 11. Keputusan yang perlu disahkan pemilik bisnis

1. Nilai earn dan redeem, minimum belanja, batas maksimum redeem, masa berlaku, serta produk yang dikecualikan.
2. Apakah poin berlaku bersama di semua outlet (asumsi PRD: ya).
3. Apakah diskon member boleh digabung dengan redeem (asumsi: ya, dengan urutan tetap).
4. Ambang redeem yang memerlukan OTP/persetujuan manajer.
5. Kanal pelanggan untuk cek saldo pada pilot: halaman web, WhatsApp, atau keduanya.
6. Kebijakan checkout ketika layanan loyalty sedang tidak tersedia.

Keputusan di atas tidak menghalangi pembuatan desain teknis dan prototipe; nilainya perlu ditetapkan sebelum transaksi nyata dijalankan.

## 12. Keterlacakan ke riset

- [Poindo: cara kerja dan fitur](https://poindo.id/cara-kerja) — identifikasi via WhatsApp, aturan poin, reward, lintas merchant.
- [Cazh POS dan tutorial](https://cazh.id/id/pos) — integrasi POS/member, kartu digital, Point Rules, broadcast.
- [Qasir: poin loyalitas](https://bantuan.qasir.id/id/articles/5615102-fitur-poin-loyalitas) — minimum/kelipatan, redeem checkout, struk, laporan.
- [Qasir: diskon membership](https://bantuan.qasir.id/id/articles/4141365-fitur-tambahan-kelola-diskon) — diskon member dengan periode dan minimum belanja.
- [Qasio: program loyalitas](https://qasio.id/blog/program-loyalitas-pelanggan-untuk-bisnis-kecil) — klaim kartu member digital, poin otomatis, CRM; interaksi tab pemasaran tersimpan pada evidence REA `ev_2e7784e2051953e4c82afb728db6cc92ac3e626ec371bf682049eb2b087fa541`.
