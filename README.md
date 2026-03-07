# Circulation Kiosk – SLiMS Plugin

Plugin kustom untuk SLiMS (Senayan Library Management System) yang menyediakan layanan Perpanjangan & Pengembalian Mandiri (Self-Service) dalam mode Kiosk Fullscreen tanpa template OPAC.

Dirancang untuk perpustakaan sekolah dengan PC Stand Alone + Barcode Scanner

---
<img width="1600" height="900" alt="image" src="https://github.com/user-attachments/assets/b6d62fdb-7356-41da-bc52-eb0af8876b3d" />
<img width="1600" height="900" alt="image" src="https://github.com/user-attachments/assets/d2387d48-03ec-4897-bcc1-be66f576b223" />

## Fitur

- Fullscreen kiosk tanpa header dan footer OPAC
- Self Extend (Perpanjangan Mandiri)
- Self Return (Pengembalian Mandiri)
- Perpanjangan maksimal 1 kali (field `loan.renewed`)
- Perhitungan jatuh tempo berdasarkan `mst_member_type.loan_periode`
- Logging otomatis ke `system_log`
- Akses berbasis token (`?key=TOKEN`), Token dapat di generate melalui token generator, misal https://openreplay.com/tools/token-generator/
- Plugin dapat diaktifkan / dinonaktifkan dari System → Plugins
- Auto reset dengan countdown 5 detik
- Mode kiosk: blokir klik kanan, ESC, dan F11
- Auto focus pada field Scan kartu Anggota

---

## Struktur File
- kiosk.plugin.php
- self_extend_kiosk.php
- self_return_kiosk.php

---

## Instalasi

1. Unduh keseluruhan isi folder
2. Ekstrak hasil unduhan  ke folder `/plugins/circulation_kiosk`
3. Generate TOKEN
4. Edit file circulation_kiosk.plugin.php, ganti Nilai 'YOUR TOKEN' pada baris $TOKEN = 'YOUR TOKEN'; dengan token hasil generate
5. Simpan File
6. Login sebagai Super Admin
7. Aktifkan melalui menu System → Plugins
8. Akses melalui:
index.php?p=kiosk_extend&key=YOUR_TOKEN
index.php?p=kiosk_return&key=YOUR_TOKEN

---

## Menggunakan Field Bawaan SLiMS

- loan.renewed
- loan.due_date
- loan.is_return
- mst_member_type.loan_periode
- system_log
- Tidak memerlukan perubahan struktur database.

---
## Penggunaan Untuk Pengembalian
1. Pemustaka membawa Kartu dan buku yang akan di pinjam
2. scan QR code/Barcode pada kartu anggota atau ketik Member ID dan tekan enter
3. di layar akan terlihat nama, ID Anggota dan banyak pinjaman, auto countdown 5 detik dan reset ke field scan
4. Scan QRCode/Barcode pada Buku, akan terlihat status peminjaman
5. Jika terlambat akan di hitung otomatis jumlah denda yang harus dibayar, auto countdown 5 detik dan reset ke field Scan Member ID
6. Ulangi Langkah 4 & 5 untuk buku lainnya
7. Transaksi Selesai

## Penggunaan Untuk Perpanjangan
1. Pemustaka membawa Kartu dan buku yang di pinjam
2. scan QR code/Barcode pada kartu anggota atau ketik Member ID dan tekan enter
3. di layar akan terlihat nama, ID Anggota dan banyak pinjaman, auto countdown 5 detik dan reset ke field scan
4. Scan QRCode/Barcode pada Buku, akan terlihat status peminjaman
5. Jika masih dalam durasi peminjaman maka akan di tambah sesuai dengan aturan peminjaman sesuai jenis keanggotaan, dihitung dari akhir peminjaman BUKAN tanggal Perpanjangan 
6. Jika terlambat akan muncul peringatan dan otomatis muncul jumlah denda yang harus dibayar, dan dan muncul notifikasi untuk menyelesaikan denda ke petugas, auto countdown 5 detik dan reset ke field Scan Member ID
8. Ulangi Langkah 4 s.d 6 untuk buku lainnya
9. Transaksi Selesai

## Author

Original base: Erwan Setyo Budi  
Modified & Extended: Indra Febriana Rulliawan


