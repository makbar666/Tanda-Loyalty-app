# Analisis sistem poin toko dan membership

Tanggal pemeriksaan: 20 November 2026 (Asia/Singapore)  
Objek: Poindo, Cazh POS, Qasir, Qasio  
Metode: MCP REA `capture_browser_scenario` pada halaman publik, disertai pembacaan halaman produk dan dokumentasi resmi. Bukti REA mentah tersimpan di [`rea-evidence.json`](./rea-evidence.json).

## Ringkasan

Keempat produk memakai pendekatan yang berbeda. **Poindo** berfokus pada jaringan loyalty lintas merchant dengan identitas nomor WhatsApp. **Cazh POS** menanamkan membership, poin, kartu digital, dan kampanye dalam POS multi-outlet. **Qasir** memberi mekanisme poin yang paling rinci dalam dokumentasi publik: ambang belanja, kelipatan, nilai poin dalam rupiah, penukaran saat checkout, dan pelaporan. **Qasio** menawarkan POS yang dibuat sesuai bisnis dengan CRM, kartu member digital, poin, voucher, dan promosi WhatsApp; detail ini berasal dari halaman pemasaran/blog, bukan akun operasional yang diuji.

Temuan tentang operasi di balik login adalah **klaim/dokumentasi vendor**, belum verifikasi langsung terhadap transaksi sungguhan. Contoh angka dan tampilan pada halaman pemasaran diperlakukan sebagai ilustrasi, bukan saldo atau transaksi nyata.

## Perbandingan fitur

| Dimensi | Poindo | Cazh POS | Qasir | Qasio |
|---|---|---|---|---|
| Posisi produk | Platform loyalty lintas merchant | POS dengan loyalty terintegrasi | POS dengan poin dan diskon membership | POS custom dengan loyalty dan CRM |
| Identitas member | Nomor WhatsApp; portal customer disebutkan | Kartu member digital dalam aplikasi pelanggan | Data pelanggan di aplikasi; nama dan nomor HP wajib saat membuat pelanggan | Nama, nomor HP, kartu member digital; blog menyebut scan nomor/QR member |
| Perolehan poin | Kasir input nomor WhatsApp dan nominal belanja; aturan konversi ditetapkan merchant | Menu Membership → Point Rules → Set ratio | Minimum pembelian dan opsi kelipatan; pelanggan dipilih saat transaksi | Poin diklaim otomatis setelah identitas member dipindai saat transaksi |
| Penukaran | Potongan pembayaran atau reward; diklaim bisa lintas merchant | Benefit dan poin disebut, prosedur penukaran tidak terinci di halaman yang diperiksa | Tombol Redeem Poin saat pembayaran; jumlah poin bisa dipilih sampai saldo tersedia | Penukaran di kasir menjadi diskon disebut dalam blog |
| Masa berlaku poin | Dapat diatur; ada pemberitahuan menjelang kedaluwarsa | Tidak ditemukan aturan poin kedaluwarsa yang spesifik | Tidak ditemukan aturan poin kedaluwarsa dalam panduan poin yang diperiksa | Blog menyebut batas kedaluwarsa dapat dikonfigurasi |
| Promosi/member | Segmentasi berdasarkan riwayat, pesan WhatsApp, analitik kampanye | Broadcast ke segmen member | Diskon membership nominal/persen, jadwal, minimum belanja, per outlet | Voucher/promo, WhatsApp blast, CRM dan riwayat pelanggan |
| Laporan | Dashboard transaksi, poin beredar, kunjungan ulang; klaim ekspor Excel/PDF | Laporan gabungan outlet; tutorial ekspor Excel dan filter kasir | Saldo pelanggan, poin ditukar, laporan diskon/redeem yang dapat diekspor | Data member, total belanja dan kunjungan terakhir menurut blog |
| Cakupan outlet/merchant | Lintas merchant dengan klaim settlement antar merchant | Multi-outlet | Diskon per outlet; outlet tambahan tersedia | Multi-outlet dan role owner/manajer/kasir |

Sumber matriks: [Poindo fitur](https://poindo.id/fitur), [Poindo cara kerja](https://poindo.id/cara-kerja), [Cazh POS](https://cazh.id/id/pos), [tutorial Cazh](https://cazh.id/en/pos/tutorial), [panduan poin Qasir](https://bantuan.qasir.id/id/articles/5615102-fitur-poin-loyalitas), [panduan diskon Qasir](https://bantuan.qasir.id/id/articles/4141365-fitur-tambahan-kelola-diskon), [Qasio](https://qasio.id/), [artikel loyalty Qasio](https://qasio.id/blog/program-loyalitas-pelanggan-untuk-bisnis-kecil).

## 1. Poindo

**Alur yang dijelaskan vendor:** merchant mendaftar, menetapkan konversi belanja menjadi poin dan reward; kasir memasukkan nomor WhatsApp serta nominal transaksi; sistem menghitung poin dan mengirim notifikasi. Pelanggan dapat mengecek saldo via chat dan meminta penukaran poin di kasir. Halaman fitur menyebut masa berlaku yang dapat diatur, pengingat kedaluwarsa, promosi tersegmentasi, dan settlement poin lintas merchant. [Cara kerja](https://poindo.id/cara-kerja) · [Fitur](https://poindo.id/fitur).

**Nilai pembeda:** identitas member ringan dan jaringan reward lintas merchant. Halaman publik memisahkan jalur masuk merchant dan customer. REA menemukan tautan `/register-merchant`, `/merchants`, `/fitur`, dan `/cara-kerja`; halaman beranda juga menampilkan contoh saldo/riwayat. Contoh tersebut adalah konten pemasaran. Tombol “Lihat Demo (2 Menit)” pada pemeriksaan REA mengarah ke `/coming-soon`, sehingga demo operasional belum tersedia dari jalur itu. Bukti REA: `ev_26cfe701cd15980d75c5589be4e6a2ce4accc41bdb0249d5239ed092863976ae`.

**Pertanyaan terbuka:** bagaimana poin yang diterbitkan satu merchant dibiayai saat ditukar di merchant lain; apakah saldo global atau per merchant; prosedur refund, pembatalan, dan koreksi poin; serta kontrol penyalahgunaan nomor WhatsApp. Klaim settlement ada di halaman fitur, tetapi formula dan aturan keuangannya tidak dijelaskan publik.

## 2. Cazh POS

**Alur yang tersedia publik:** Cazh menjual POS, membership dan toko online dalam satu aplikasi. Fitur loyalty mencakup poin, benefit, dan kartu member digital. Tutorial memberi petunjuk konfigurasi `Membership → Point Rules → Set ratio`, serta broadcast `Membership → Broadcast → Write message → Choose segment`. Halaman retail menyebut kartu member di aplikasi pelanggan dan promosi visual. [Cazh POS](https://cazh.id/id/pos) · [Tutorial](https://cazh.id/en/pos/tutorial) · [Retail](https://cazh.id/id/pos/solusi/retail).

**Nilai pembeda:** integrasi kasir, inventori, pembayaran, multi-outlet, dan membership. Pada halaman POS yang diperiksa, membership tercantum dalam paket Pro; paket gratis hanya mencantumkan kasir tunggal, QRIS/e-wallet, dan laporan dasar. Harga/ketentuan paket bersifat dinamis dan perlu dicek kembali saat keputusan pembelian. REA pada beranda mengamati tautan menuju `/en/pos` serta ringkasan “POS, membership, and online store in a single app”. Bukti REA: `ev_78744df2494040422f425356258bcd707ca16aa324d373f288b977bd044c721d`.

**Pertanyaan terbuka:** identitas utama member, batas penukaran, nilai tukar, masa berlaku, cakupan poin lintas outlet, dampak refund terhadap saldo, dan laporan kewajiban poin tidak terinci dalam halaman yang diperiksa.

## 3. Qasir

**Alur poin terdokumentasi:** pemilik membuka menu Pelanggan, mengatur poin berdasar minimum transaksi dan pilihan kelipatan, lalu mengaktifkannya. Saat transaksi, kasir memilih pelanggan. Poin bernilai rupiah menurut panduan (contoh 5.000 poin = Rp5.000). Saat pembayaran, kasir menyalakan Redeem Poin dan memilih jumlah penukaran. Saldo dan poin yang ditukar muncul pada struk, detail transaksi, dashboard, dan laporan yang dapat diekspor. Fitur poin berhenti dapat digunakan saat langganan Pro berakhir menurut panduan tersebut. [Panduan poin loyalitas](https://bantuan.qasir.id/id/articles/5615102-fitur-poin-loyalitas).

**Membership terpisah dari poin:** Qasir juga memiliki diskon membership. Pemilik dapat memberi nama diskon, nilai persen/nominal, tanggal mulai/akhir, dan minimum pembelian. Pengaturan diskon berlaku per outlet, dan laporan pemakaiannya dapat difilter/ekspor. Panduan diskon menyebut fitur ini pada Pro Plus. [Panduan kelola diskon](https://bantuan.qasir.id/id/articles/4141365-fitur-tambahan-kelola-diskon). Panduan pelanggan menyebut nama dan nomor HP wajib saat menambah pelanggan; pemilik juga dapat mewajibkan pelanggan dipilih sebelum kasir melanjutkan transaksi. [Panduan pelanggan](https://bantuan.qasir.id/id/articles/1887766-tentang-fitur-pelanggan).

**Catatan paket:** panduan lama poin menyebut “Qasir Pro”, sementara halaman paket saat pemeriksaan menampilkan pembagian Pro/Pro Plus dan tabel fitur yang sulit dibaca sebagai teks. Karena itu, kelayakan fitur poin pada paket tertentu perlu konfirmasi saat implementasi atau pembelian. REA beranda Qasir membuktikan halaman publik aktif dan menautkan harga/pusat bantuan, bukan pengoperasian aplikasi kasir. Bukti REA: `ev_aa7f9d6b744a2c11567c01937a63149a760f8d57e5af853e0a51a2ca01e23bfa`.

**Pertanyaan terbuka:** kapan poin tepatnya dikreditkan (saat pembayaran atau setelah finalisasi), aturan pengembalian barang, stacking dengan diskon membership, masa berlaku poin, dan lingkup saldo antar outlet.

## 4. Qasio

**Fitur yang teramati pada situs:** setelah REA mengklik tab “Pelanggan”, halaman menampilkan CRM pelanggan, riwayat transaksi per pelanggan, Program Poin & Loyalty, Voucher & Promo, WhatsApp Blast, aplikasi owner, dan notifikasi reservasi. Ini membuktikan isi antarmuka pemasaran berubah ketika tab dipilih; belum membuktikan modul kasir di balik login. Bukti REA: `ev_2e7784e2051953e4c82afb728db6cc92ac3e626ec371bf682049eb2b087fa541` (tab Pelanggan) dan `ev_399886f37868e6ac3527960a55cf91d291c3101b503344a9156225677c33074b` (beranda).

**Alur yang diklaim artikel resmi:** pelanggan didaftarkan dengan nama/nomor HP, kartu member digital dibuat, identitas dipindai saat transaksi, poin terkumpul otomatis, dan poin dapat ditukar sebagai diskon di kasir. Owner diklaim dapat melihat aktivitas member serta mengatur rasio perolehan, nilai tukar, dan masa kedaluwarsa. Situs utama menawarkan sistem POS yang dibangun sesuai kebutuhan bisnis, bukan satu konfigurasi baku untuk semua klien. [Artikel program loyalitas](https://qasio.id/blog/program-loyalitas-pelanggan-untuk-bisnis-kecil) · [Situs Qasio](https://qasio.id/).

**Pertanyaan terbuka:** spesifikasi produk final bergantung pada proyek custom; perilaku aktual pendaftaran, scan, penukaran, dan laporan harus diuji pada instans calon klien. Klaim model pembayaran sekali dan kepemilikan data pada situs bukan bukti kondisi kontrak atau arsitektur penyimpanan data.

## Rekomendasi rancangan bila membangun sistem poin toko

Berikut **usulan desain**, bukan fitur yang telah diverifikasi pada semua produk:

1. **Identitas member:** nomor HP/WhatsApp unik per tenant; izin komunikasi promo dipisah dari persetujuan pendaftaran; opsi QR/kartu digital untuk checkout cepat.
2. **Mesin aturan:** definisikan basis perolehan (subtotal atau total setelah diskon/pajak), ambang minimum, kelipatan, pembulatan, pengecualian produk, periode kampanye, serta prioritas antaraturan. Simpan versi aturan yang dipakai pada setiap transaksi.
3. **Buku besar poin:** catat setiap perubahan sebagai entri kredit/debit/expire/reversal yang mengacu ke transaksi asal. Saldo dihitung dari entri atau proyeksi yang dapat direkonsiliasi; cegah kredit ganda melalui idempotency key transaksi.
4. **Penukaran:** tampilkan saldo, nilai rupiah, batas maksimal, masa berlaku, dan konfirmasi pelanggan; gunakan operasi atomik agar dua kasir tidak dapat menghabiskan saldo yang sama. Cantumkan poin dipakai dan saldo akhir pada struk.
5. **Refund:** balikkan poin hasil transaksi yang direfund, serta pulihkan poin yang pernah dipakai sesuai kebijakan yang eksplisit. Catat siapa yang melakukan koreksi manual dan alasannya.
6. **Multi-outlet dan lintas merchant:** putuskan sejak awal apakah saldo berlaku per outlet, per brand, atau di seluruh jaringan. Jika lintas merchant, tetapkan ledger kewajiban, nilai tukar yang konsisten, fee/settlement, batas eksposur, dan rekonsiliasi periodik.
7. **CRM dan laporan:** ukur member aktif, tingkat transaksi teridentifikasi, poin diterbitkan/ditukar/kedaluwarsa, kewajiban poin beredar, kunjungan ulang, dan performa kampanye. Pisahkan data nyata dari contoh pada dashboard.
8. **Kontrol operasional:** hak akses kasir dan owner, log audit, rate limit penukaran, verifikasi identitas pada penukaran bernilai tinggi, serta ekspor data pelanggan sesuai izin.

Prioritas MVP untuk satu toko: daftar member → pilih member saat checkout → aturan perolehan sederhana → ledger poin → redeem → struk dan riwayat → laporan dasar. Multi-outlet, kampanye, dan settlement lintas merchant dapat menyusul setelah alur inti teruji.

## Batas pemeriksaan

- REA membuka empat beranda publik dan mengklik tab “Pelanggan” di Qasio. Tidak ada kredensial merchant/customer yang diberikan, sehingga tidak dilakukan transaksi nyata atau inspeksi dashboard privat.
- Halaman resmi dan pusat bantuan adalah sumber klaim fitur. Materi promosi tidak selalu menggambarkan konfigurasi atau perilaku semua akun.
- Tidak ada API privat, kode sumber, ataupun paket aplikasi seluler yang diperiksa. Detail algoritma, skema database, perlindungan fraud, dan akuntansi poin tidak dapat disimpulkan dari permukaan publik.
- Evidence ID di atas mengacu ke bundle REA lokal. Tautan sumber adalah halaman vendor yang dapat berubah setelah tanggal pemeriksaan.
