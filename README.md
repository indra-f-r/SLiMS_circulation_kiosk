# 📚 SLiMS Kiosk Mandiri: Layanan Pengembalian & Perpanjangan
Sebuah Plugin antarmuka Layanan Sirkulasi Mandiri untuk **SLiMS (Senayan Library Management System)**. Plugin ini memisahkan dan mengoptimalkan dua layanan utama perpustakaan: **Pengembalian Mandiri (Self-Return)** dan **Perpanjangan Mandiri (Self-Extend)** ke dalam antarmuka *State-based* yang modern, ramah layar sentuh (*touch-friendly*), dan dilengkapi dengan *Token Authentication* untuk keamanan akses Kiosk.

<img width="1600" height="900" alt="image" src="https://github.com/user-attachments/assets/d7b5139e-7e60-49b2-9e06-9da6f5449a50" />

<img width="1600" height="900" alt="image" src="https://github.com/user-attachments/assets/0e342505-851d-4f48-9df8-ef227d41a4f5" />

**Versi:** 1.8.0  
**Penulis:** Indra Febriana Rulliawan (indra.f.rulliawan@gmail.com | digilib.wacanateknologi.id)  
**Repositori:** [SLiMS_circulation_kiosk](https://github.com/indra-f-r/SLiMS_circulation_kiosk)

## ✨ Fitur Unggulan
* 🔐 **Token Authentication**: Akses halaman kiosk dilindungi oleh parameter Token unik. URL tidak bisa ditebak atau diakses sembarangan oleh pemustaka dari perangkat pribadi mereka.
* ⚙️ **Auto-Initialize Database**: Saat plugin diaktifkan, sistem akan otomatis memeriksa dan membuat tabel `book_review` di *database* jika belum ada, tanpa merusak data yang sudah ada.
* 🚀 **State-Based UI**: Transisi antarmuka yang mulus. Layar Scan Member dan Layar Scan Buku dipisah agar pemustaka tidak salah memasukkan ID ke kolom yang keliru.
* 🛡️ **Anti Double-Scan (Cut-off)**: Mengunci kolom *input* secara dinamis dalam hitungan milidetik saat sistem memproses *request*, mencegah *error* atau beban *database* ganda.
* 🧹 **Super Cleaner & Safe Query**: Membersihkan input karakter "gaib" dari mesin *scanner* (Regex), serta menggunakan `LEFT JOIN` agar aplikasi tidak *crash* meskipun master data katalog terhapus oleh admin.
* ⏱️ **Visual Auto-Reset Timer**: Hitung mundur otomatis (30/45 detik) yang terlihat di layar untuk mereset sesi jika pemustaka tiba-tiba pergi dari mesin Kiosk.
* 🎯 **Persistent Auto-Focus**: Mencegah *Virtual Keyboard* muncul mengganggu. Kursor akan otomatis kembali ke dalam kolom *input* yang benar meskipun layar sentuh tidak sengaja tersentuh.
* ⭐ **Dynamic Pop-Up Review (Khusus Pengembalian)**: Pemustaka dapat memberikan *rating* bintang (1-5) dan melaporkan kondisi fisik buku yang dikembalikan dengan pilihan teks yang dinamis.
* 🔒 **Kiosk Protection**: Memaksa mode *Fullscreen* otomatis dan mencegah pemustaka keluar dari antarmuka kiosk (Membutuhkan *Password* untuk *Exit*).

## 🔄 Alur Penggunaan (User Flow)

### 1. Alur Kiosk Pengembalian (Self-Return)
1. **Scan Kartu**: Pemustaka men-scan kartu anggota.
2. **Cek Pinjaman**: Sistem menampilkan Nama, ID, dan daftar rinci judul buku yang masih dipinjam.
   <img width="1600" height="900" alt="image" src="https://github.com/user-attachments/assets/3bb267d6-7552-4871-b1c1-a52f8ec338e4" />
3. **Scan Buku**: Pemustaka men-scan *barcode* buku.
4. **Validasi & Denda**: Sistem otomatis menghitung denda jika terlambat (mengabaikan hari libur nasional & akhir pekan).
5. **Review Kondisi**: Muncul pop-up *rating* 1-5 bintang untuk menilai fisik buku.
<img width="1600" height="900" alt="image" src="https://github.com/user-attachments/assets/27b5fd35-d8a7-4114-98e3-19e616c5cbde" />
<img width="1600" height="900" alt="image" src="https://github.com/user-attachments/assets/d347d1ef-8c30-4bf9-b117-c7a6212a3d33" />


6. **Selesai**: Sesi mereset otomatis dalam 15 detik atau jika ditekan tombol selesai.

### 2. Alur Kiosk Perpanjangan (Self-Extend)
1. **Scan Kartu**: Pemustaka men-scan kartu anggota.
2. **Cek Pinjaman**: Sistem menampilkan daftar buku yang dipinjam beserta tanggal jatuh tempo saat ini.
   <img width="1600" height="900" alt="image" src="https://github.com/user-attachments/assets/84a53b62-a1fa-4fe9-abb3-2424b0d0f432" />

4. **Scan Buku**: Pemustaka men-scan buku yang ingin diperpanjang.
5. **Validasi Syarat**: Sistem menolak perpanjangan jika buku sudah *Overdue* (lewat jatuh tempo) atau sudah mencapai limit maksimal perpanjangan (*renewed*).
6. **Perpanjangan Sukses**: Tanggal jatuh tempo baru ditambahkan dan ditampilkan di layar.

## 🛠️ Instalasi & Konfigurasi

### 1. Persiapan File
1. *Clone* atau unduh *source code* dari repositori ini.
2. Buat folder baru bernama `SLiMS_circulation_kiosk` di dalam folder `plugins` SLiMS Anda.  
   *(Contoh path: `C:\laragon\www\slims\plugins\SLiMS_circulation_kiosk\`)*
3. Pastikan file `kiosk.plugin.php`, `self_return_kiosk.php`, dan `self_extend_kiosk.php` berada di dalam folder tersebut.

### 2. Konfigurasi Token Keamanan
Aplikasi ini membutuhkan *Security Token* untuk mencegah akses tanpa izin.
1. Hasilkan token acak yang aman. Anda bisa menggunakan *tools* seperti [OpenReplay Token Generator](https://openreplay.com/tools/token-generator/).
   > ⚠️ **SANGAT PENTING:** Gunakan hanya kombinasi huruf dan angka (Alphanumeric). **JANGAN menggunakan karakter spesial** (`!@#$%^&*` dsb) agar URL parameter tidak bermasalah (URL *Broken*).
2. Buka file `kiosk.plugin.php` menggunakan *Text Editor*.
3. Cari variabel `$TOKEN` dan masukkan token yang sudah Anda hasilkan.
   ```php
   $TOKEN = 'MasukkanTokenAndaDisiniTanpaKarakterSpesial123';
   ```

### 3. Aktivasi Plugin SLiMS
Agar plugin dapat berjalan dan tabel `book_review` otomatis terbuat, Anda harus mendaftarkannya pada sistem SLiMS:
1. Masuk ke halaman **Admin SLiMS** > **Sistem** > **Plugin**.
2. Aktifkan plugin **Layanan Pengembalian dan Perpanjangan Mandiri**.

### 4. Akses Laman Kiosk
Buka *browser* pada mesin Kiosk/Komputer Perpustakaan Anda dan gunakan format URL berikut (sesuaikan domain dan token Anda):

* **Laman Kiosk Perpanjangan:**
  ```text
  http://[domain-slims-anda]/?p=kiosk_extend&key=[TOKEN_ANDA]
  ```
* **Laman Kiosk Pengembalian:**
  ```text
  http://[domain-slims-anda]/?p=kiosk_return&key=[TOKEN_ANDA]
  ```
> **Tip:** Jadikan URL tersebut sebagai *Bookmark* atau *Shortcut Browser Start-up* di komputer Kiosk Anda. Layar akan otomatis meminta izin *Fullscreen*.

## 📂 Struktur Folder
```text
slims_root/
├── ...
└── plugins/
    └── SLiMS_circulation_kiosk/
        ├── kiosk.plugin.php         # Router, Database Initializer & Interceptor Plugin
        ├── self_return_kiosk.php    # UI & Logika Pengembalian Mandiri
        └── self_extend_kiosk.php    # UI & Logika Perpanjangan Mandiri
```

## ⚙️ Pengaturan Lanjutan (Tweak)
Jika Anda ingin menyesuaikan beberapa parameter UI/UX, silakan buka file `self_return_kiosk.php` atau `self_extend_kiosk.php` dengan *text editor*:
* **Password Keluar Mode Kiosk:** Cari `const KIOSK_PASSWORD = "[YOUR_PASSWORD";` dan ubah sesuai standar keamanan perpustakaan Anda.
* **Durasi Timer Visual:** Cari `startVisualTimer(45, 'cdState2');`. Ubah `45` menjadi durasi detik yang Anda inginkan.
* **Biaya Denda (Khusus Return):** Cari baris `$fine=$late*1000;`. Ubah angka `1000` dengan tarif denda harian (dalam Rupiah) yang berlaku di perpustakaan Anda.

## ⚠️ Troubleshooting (Masalah Umum)
* **Kursor Hilang saat Layar Disentuh / Virtual Keyboard Muncul:** Ini adalah respon bawaan *Touchscreen* Windows/Android. Fitur *Auto-focus* di script akan langsung menarik kursor kembali. Namun, jika *Virtual Keyboard* muncul dan menutupi layar, silakan matikan fitur *Virtual Keyboard* di pengaturan OS perangkat keras Anda (Kiosk ini didesain menggunakan *Barcode Scanner* Fisik).
* **Tabel Book Review Tidak Terbuat:** Pastikan *user database* SLiMS Anda memiliki hak akses `CREATE TABLE`. Fitur ini dieksekusi sekali saat *plugin* dimuat di SLiMS.

## 🤝 Kontribusi & Dukungan
Jika Anda menemukan *bug* atau memiliki ide penambahan fitur (seperti panel admin untuk mengelola hasil *review* buku), silakan buat *Pull Request* atau laporkan di kolom *Issues* repositori GitHub ini. Mari bersama bangun ekosistem SLiMS yang lebih baik!

## 📄 Lisensi
Di distribusikan di bawah lisensi GNU GPL v3.

---
*Dikembangkan dengan ☕ untuk mempermudah tugas Pustakawan dan memanjakan Pemustaka.* 🚀

***
