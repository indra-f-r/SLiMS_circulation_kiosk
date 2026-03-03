# Circulation Kiosk – SLiMS Plugin

Plugin kustom untuk SLiMS (Senayan Library Management System) yang menyediakan layanan Sirkulasi Mandiri (Self-Service) dalam mode Kiosk Fullscreen tanpa template OPAC.

Dirancang untuk perpustakaan sekolah dengan kebutuhan transaksi menengah hingga tinggi.

---

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

---

## Struktur File
circulation_kiosk.plugin.php
self_extend_kiosk.php
self_return_kiosk.php

---

## Instalasi

1. Unduh keseluruhan isi folder
2. Ekstrak hasil unduhan  ke folder `/plugins/`
3. Generate TOKEN
4. Edit file circulation_kiosk.plugin.php, ganti baris $TOKEN = 'YOUR TOKEN'; dengan token hasil generate
5. Login sebagai Super Admin
6. Aktifkan melalui menu System → Plugins
7. Akses melalui:

index.php?p=kiosk_extend&key=YOUR_TOKEN
index.php?p=kiosk_return&key=YOUR_TOKEN

---

## Menggunakan Field Bawaan SLiMS

- loan.renewed
- loan.due_date
- loan.is_return
- mst_member_type.loan_periode
- system_log

Tidak memerlukan perubahan struktur database.

---

## Author

Original base: Erwan Setyo Budi  
Modified & Extended: Indra Febriana Rulliawan


