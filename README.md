# Smart Expense - Wealth Management Dashboard & Mobile App

A full-stack financial management solution featuring a modern Laravel web application (Livewire/Volt/Filament Admin) and a companion Flutter mobile app with premium UI & dark mode.

---

## 🚀 Panduan Menjalankan Sistem

### Bagian 1: Menjalankan Laravel Backend & API

Laravel backend bertindak sebagai penyedia web dashboard sekaligus API server untuk aplikasi Flutter (menggunakan Laravel Sanctum).

#### 1. Setup Awal Laravel
1. Buka terminal di folder Laravel `c:\laragon\www\Manajemen_Keuanganv1`.
2. Install dependensi composer & npm:
   ```bash
   composer install --ignore-platform-reqs
   npm install
   ```
3. Salin file `.env.example` ke `.env` (jika belum ada) dan konfigurasi database MySQL Anda:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=manajemen_keuangan
   DB_USERNAME=root
   DB_PASSWORD=
   ```
4. Generate key & jalankan migrasi database:
   ```bash
   php artisan key:generate
   php artisan migrate:fresh --seed
   ```
   *(Seeder otomatis membuat akun user bawaan dan kategori)*.

#### 2. Menjalankan Server Backend
Agar server Laravel dapat diakses oleh aplikasi Flutter (baik lewat emulator maupun HP fisik di jaringan LAN yang sama), jalankan server dengan binding IP lokal komputer Anda atau `0.0.0.0`:

1. Cari tahu IP lokal komputer Anda menggunakan `ipconfig` di command prompt (misal: `192.168.1.10`).
2. Jalankan Laravel server menggunakan host IP tersebut:
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```
3. Di terminal terpisah, jalankan Vite untuk aset web:
   ```bash
   npm run dev
   ```

---

### Bagian 2: Menjalankan Aplikasi Flutter Mobile

Aplikasi mobile dibuat dengan Flutter menggunakan state management **Provider** dan HTTP client **Dio** untuk integrasi dengan Laravel API.

#### 1. Setup Awal Flutter
1. Buka terminal di folder Flutter `d:\Flutter_belajar\manajemen_keuangan`.
2. Dapatkan paket dependensi yang dibutuhkan:
   ```bash
   flutter pub get
   ```

#### 2. Konfigurasi Base URL API
Sebelum menjalankan aplikasi, pastikan aplikasi mengarah ke alamat IP server Laravel Anda yang aktif:

1. Buka file `lib/config/app_config.dart` di project Flutter.
2. Ubah `baseUrl` sesuai server Anda:
   - Jika Anda menggunakan **Android Emulator**: Gunakan `http://10.0.2.2:8000/api` (IP khusus emulator Android untuk mengakses localhost komputer).
   - Jika Anda menggunakan **iOS Simulator**: Gunakan `http://localhost:8000/api`.
   - Jika Anda menggunakan **HP Fisik (Real Device)**: Gunakan IP lokal komputer Anda, misalnya `http://192.168.1.10:8000/api`. *Pastikan HP dan komputer terhubung ke jaringan Wi-Fi yang sama.*

#### 3. Menjalankan Aplikasi
Hubungkan emulator atau perangkat HP Anda, lalu jalankan perintah:
```bash
flutter run
```

---

## 🔐 Akun Uji Coba Default

Gunakan akun di bawah ini untuk masuk melalui Web maupun Aplikasi Flutter:

*   **Akun Regular (Khusus Dashboard Web & Mobile App)**:
    *   Email: `professional@example.com`
    *   Password: `password`
*   **Akun Administrator (Akses Web, Admin Panel `/admin` & Mobile App)**:
    *   Email: `admin@example.com`
    *   Password: `password`

---

## 🎨 Fitur Aplikasi Mobile (Flutter)
- **Otentikasi Aman**: Login, register, dan auto-login token-based menggunakan Laravel Sanctum & secure storage.
- **Ringkasan Finansial**: Dashboard indah dengan kartu saldo, pemasukan, pengeluaran, target tabungan, dan Skor Kesehatan Finansial.
- **AI Financial Tips**: Tips alokasi finansial cerdas yang dipersonalisasi berbasis model AI (Groq/LLaMA) lengkap dengan caching.
- **Manajemen Transaksi (CRUD)**: Menambah, mengubah, dan menghapus pengeluaran/pemasukan dengan swipe-to-delete.
- **Anggaran Kategori (Budgets)**: Kontrol pengeluaran per kategori dengan progress bar indikator. Notifikasi otomatis jika pemakaian mencapai 80%.
- **Target Tabungan (Savings Goals)**: Kelola target impian Anda beserta pelacakan progres menabung.
- **Laporan Keuangan Terpadu**: Visualisasi grafik donat fl_chart dan opsi ekspor berkas laporan (PDF/Excel) langsung ke browser.
- **Tema Gelap & Terang**: Dukungan tema gelap eksklusif bergaya fintech modern.
- **Bahasa Indonesia**: Seluruh UI telah disesuaikan dalam Bahasa Indonesia.

