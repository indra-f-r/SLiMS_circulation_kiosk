# Circulation Kiosk – SLiMS Plugin

Plugin kustom untuk SLiMS (Senayan Library Management System) yang menyediakan layanan Perpanjangan & Pengembalian Mandiri (Self-Service) dalam mode Kiosk Fullscreen tanpa template OPAC.

Dirancang untuk perpustakaan sekolah dengan PC Stand Alone + Barcode Scanner

---


## Fitur

- Fullscreen kiosk tanpa header dan footer OPAC
- Self Extend (Perpanjangan Mandiri)
<img width="1920" height="1080" alt="image" src="https://github.com/user-attachments/assets/99672b03-eb49-452e-a8d6-21f725c762c8" />
- Self Return (Pengembalian Mandiri)
<img width="1920" height="1080" alt="image" src="https://github.com/user-attachments/assets/0a122624-5743-43b2-bb7e-b147118ac154" />
- Perpanjangan maksimal 1 kali (field `loan.renewed`)
- Perhitungan jatuh tempo berdasarkan `mst_member_type.loan_periode`
- Logging otomatis ke `system_log`
- Akses berbasis token (`?key=TOKEN`), Token dapat di generate melalui token generator, misal https://openreplay.com/tools/token-generator/
- Plugin dapat diaktifkan / dinonaktifkan dari System → Plugins
- Auto reset dengan countdown 5 detik
<img width="1920" height="1080" alt="image" src="https://github.com/user-attachments/assets/750c78ae-e153-4d72-8031-953e267810ac" />
- Mode kiosk: blokir klik kanan, ESC, dan F11 dengan menggunakan password
<img width="460" height="207" alt="image" src="https://github.com/user-attachments/assets/91b4b94c-3638-4bd0-a0d6-1de9910a642d" />
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
5. Edit file self_extend_kiosk.php / self_extend_kiosk.php, cari field KIOSK_PASSWORD=YOUR_PASSWORD, silakan ganti YOUR_PASSWORD dengan password yang diinginkan untuk keuluar dari state fullscreen 
6. Simpan File
7. Login sebagai Super Admin
8. Aktifkan melalui menu System → Plugins
9. Akses melalui:
- index.php?p=kiosk_extend&key=YOUR_TOKEN
- index.php?p=kiosk_return&key=YOUR_TOKEN

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


