# Indofilter ERP

Indofilter ERP adalah aplikasi ERP internal berbasis web untuk membantu perusahaan mengelola data master, dokumen operasional, partner, produk, laporan, dan berkas pendukung dalam satu tempat.

Aplikasi ini dirancang untuk kebutuhan operasional perusahaan dagang dan manufaktur skala kecil sampai menengah, terutama proses administrasi penjualan, pembelian, inventaris, serta pengelolaan dokumen bisnis.

## Preview
### Manajemen Dokumen
<img width="1733" height="907" alt="ChatGPT Image Sep 22, 2026, 06_32_29 PM" src="https://github.com/user-attachments/assets/b47d515c-561e-4b60-89da-6869507fa135" />

### Preview Detail Dokumen
<img width="1341" height="980" alt="preview_dokumen_sensored" src="https://github.com/user-attachments/assets/e95cc96a-5b49-4807-a4c1-62067c3d4c1f" />


## Ringkasan Fitur

### Dashboard

- Ringkasan statistik operasional.
- Ikhtisar dokumen dan aktivitas yang tersimpan di sistem.
- Titik masuk utama untuk memantau kondisi data aplikasi.

### Manajemen Dokumen

Dokumen dapat dibuat, dilihat, diperbarui, dikonfirmasi, dibatalkan, dan diekspor.

Jenis dokumen yang didukung:

- Quotation.
- Proforma Invoice.
- Invoice.
- Purchase Order.
- Delivery Slip atau surat jalan.
- Delivery Address atau alamat surat.

Kemampuan dokumen meliputi:

- Penomoran dokumen otomatis berdasarkan tipe dan perusahaan.
- Pengelolaan item, kuantitas, harga, diskon, pajak, dan total dokumen.
- Status dokumen dan alur konfirmasi atau pembatalan.
- Export dokumen ke format DOCX menggunakan template.
- Upload dan download file Purchase Order masuk.
- Upload, download, dan hapus dokumen pendukung.
- Pemeriksaan file lokal yang telah dibuat untuk dokumen.
- Pembacaan informasi tertentu dari PDF melalui integrasi Gemini API apabila dikonfigurasi.

### Master Data

- **Perusahaan**: identitas perusahaan, nomor telepon, rekening bank, format dokumen, dan konfigurasi penyimpanan.
- **Partner**: pelanggan, vendor, alamat, kontak, dan informasi rekening bank.
- **Produk**: kode, nama, satuan, harga, variasi, dan informasi pendukung produk.
- **Pengguna**: pengelolaan akun yang dapat mengakses aplikasi.

Riwayat produk dan partner tersedia untuk membantu melihat penggunaan data tersebut pada dokumen sebelumnya.

### Laporan

- Ringkasan statistik dokumen.
- Penyaringan data laporan berdasarkan parameter yang tersedia.
- Pengambilan data laporan melalui API untuk ditampilkan pada antarmuka aplikasi.

### Pencarian Global

Pencarian global membantu menemukan data lintas modul, termasuk dokumen, partner, produk, dan entitas terkait lainnya.

### Pengaturan dan Penyimpanan

- Pengaturan aplikasi yang disimpan melalui modul settings.
- Pengaturan drive letter untuk kebutuhan penyimpanan lokal di lingkungan Windows.
- Integrasi Google Drive untuk menguji koneksi, mengunggah dokumen, mengelompokkan file berdasarkan tipe dokumen dan perusahaan, serta menghapus file yang tersimpan.

## Alur Kerja Umum

1. Masukkan perusahaan, partner, dan produk melalui menu **Master Data**.
2. Buat dokumen dari modul yang sesuai, misalnya quotation atau purchase order.
3. Lengkapi item, harga, alamat, informasi pajak, dan berkas pendukung.
4. Simpan dokumen dan periksa nomor dokumen yang dibuat otomatis.
5. Konfirmasi dokumen ketika data sudah final atau batalkan jika transaksi tidak dilanjutkan.
6. Export dokumen ke DOCX dan simpan berkas ke lokasi lokal atau Google Drive jika integrasi tersedia.
7. Gunakan dashboard dan laporan untuk memantau dokumen yang tersimpan.

## Teknologi

### Backend

- PHP 8.2 atau lebih baru.
- Laravel 12.
- Laravel Sanctum untuk autentikasi API.
- Laravel Eloquent dan database migrations.
- PHPWord untuk pembuatan dokumen DOCX.
- Google API Client untuk integrasi Google Drive.

### Frontend

- Vue 3.
- Vue Router.
- Pinia.
- Axios.
- Tailwind CSS 4.
- Vite 7.

### Database dan Penyimpanan

- SQLite digunakan sebagai konfigurasi default untuk pengembangan lokal.
- Laravel juga dapat dikonfigurasi menggunakan MySQL, PostgreSQL, atau database lain yang didukung Laravel.
- File upload disimpan melalui Laravel Filesystem dan dapat diarahkan ke penyimpanan lokal atau layanan cloud.

## Persyaratan Sistem

- PHP 8.2 atau lebih baru.
- Composer.
- Node.js dan npm.
- Ekstensi PHP yang diperlukan Laravel, termasuk PDO dan driver database yang digunakan.
- Git untuk mengambil source code.
- Web server atau PHP development server.

Untuk integrasi Google Drive, siapkan project Google Cloud, kredensial service account, dan folder tujuan yang sesuai.

## Instalasi Pengembangan

### 1. Ambil source code

```bash
git clone https://github.com/lukman754/indofilter-erp.git
cd indofilter-erp
```

### 2. Install dependensi PHP

```bash
composer install
```

### 3. Install dependensi frontend

```bash
npm install
```

### 4. Siapkan environment

Linux, macOS, atau Git Bash:

```bash
cp .env.example .env
php artisan key:generate
```

Windows PowerShell:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Jangan commit file `.env`. File tersebut dapat berisi application key, kredensial database, API key, kredensial email, dan konfigurasi penyimpanan.

### 5. Siapkan database

Konfigurasi default menggunakan SQLite.

Linux, macOS, atau Git Bash:

```bash
touch database/database.sqlite
php artisan migrate
```

Windows PowerShell:

```powershell
New-Item database/database.sqlite -ItemType File
php artisan migrate
```

Jika file database sudah ada, cukup jalankan:

```bash
php artisan migrate
```

### 6. Siapkan penyimpanan publik

```bash
php artisan storage:link
```

### 7. Jalankan aplikasi

Untuk development, jalankan backend dan Vite pada terminal terpisah:

```bash
php artisan serve
npm run dev
```

Aplikasi tersedia di `http://localhost:8000`.

Sebagai alternatif, Composer menyediakan perintah gabungan untuk server aplikasi, queue listener, dan Vite:

```bash
composer run dev
```

### 8. Build frontend untuk deployment

```bash
npm run build
```

## Konfigurasi Environment

Variabel penting tersedia pada `.env.example`.

| Variabel           | Kegunaan                                                                 |
| ------------------ | ------------------------------------------------------------------------ |
| `APP_NAME`         | Nama aplikasi yang ditampilkan pada konfigurasi Laravel.                 |
| `APP_ENV`          | Environment aplikasi, misalnya `local` atau `production`.                |
| `APP_KEY`          | Kunci enkripsi aplikasi Laravel. Buat dengan `php artisan key:generate`. |
| `APP_DEBUG`        | Aktifkan hanya saat development. Gunakan `false` di production.          |
| `APP_URL`          | URL utama aplikasi.                                                      |
| `DB_CONNECTION`    | Driver database yang digunakan.                                          |
| `DB_DATABASE`      | Lokasi SQLite atau nama database.                                        |
| `FILESYSTEM_DISK`  | Disk penyimpanan file Laravel.                                           |
| `QUEUE_CONNECTION` | Driver queue untuk pekerjaan latar belakang.                             |
| `GEMINI_API_KEY`   | API key Gemini untuk fitur parsing PDF, jika digunakan.                  |
| `AWS_*`            | Konfigurasi penyimpanan kompatibel S3, jika digunakan.                   |

Nilai rahasia tidak boleh ditulis langsung ke source code atau di-commit ke repository.

## Integrasi Google Drive

Integrasi Google Drive bersifat opsional. Konfigurasi dilakukan melalui menu **Pengaturan** setelah aplikasi berjalan.

Alur umum integrasi:

1. Buat project dan service account pada Google Cloud.
2. Aktifkan Google Drive API.
3. Siapkan kredensial JSON service account.
4. Bagikan folder Google Drive kepada email service account dengan izin yang sesuai.
5. Masukkan kredensial dan folder tujuan melalui halaman pengaturan aplikasi.
6. Uji koneksi sebelum menyimpan konfigurasi produksi.

Kredensial Google Drive harus diperlakukan sebagai secret. Jangan menyimpan file JSON kredensial di repository publik.

## Struktur Direktori

```text
app/
  Http/Controllers/       Controller API aplikasi
  Models/                 Model Eloquent
  Services/               Service penomoran dokumen dan Google Drive
bootstrap/                Bootstrap Laravel
config/                   Konfigurasi aplikasi
database/
  migrations/             Struktur database
  seeders/                Seeder database
docs/                     Dokumentasi tambahan dan blueprint
lang/                     File terjemahan
public/                   Entry point dan asset publik hasil build
resources/
  css/                    Stylesheet
  js/                     Aplikasi Vue dan halaman frontend
routes/                   Route web dan API
templates/                Template DOCX dokumen
storage/                  File runtime, upload, cache, dan log
```

## API Utama

API aplikasi tersedia di bawah prefix `/api`.

| Area        | Endpoint utama                                         |
| ----------- | ------------------------------------------------------ |
| Autentikasi | `POST /api/login`, `POST /api/logout`, `GET /api/user` |
| Pengguna    | `/api/users`                                           |
| Perusahaan  | `/api/companies`                                       |
| Partner     | `/api/partners`                                        |
| Produk      | `/api/products`                                        |
| Dokumen     | `/api/documents`                                       |
| Dashboard   | `GET /api/dashboard/stats`                             |
| Laporan     | `GET /api/reports`, `GET /api/reports/stats`           |
| Pencarian   | `GET /api/global-search`                               |
| Pengaturan  | `GET /api/settings`, `POST /api/settings`              |

Endpoint dokumen juga mencakup pembuatan nomor, dependencies form, export DOCX, konfirmasi, pembatalan, upload Purchase Order, dan pengelolaan dokumen pendukung.

## Keamanan dan Privasi Data

Sebelum aplikasi digunakan pada production:

- Pastikan `.env`, database SQLite, log, file upload, dan kredensial pihak ketiga tidak terlacak git.
- Gunakan `APP_DEBUG=false` pada production.
- Gunakan HTTPS dan batasi akses server ke pengguna yang berwenang.
- Gunakan password kuat dan rotasi secret apabila pernah terekspos.
- Batasi permission folder `storage` dan `bootstrap/cache` sesuai kebutuhan server.
- Jangan menyimpan data pelanggan, rekening bank, dokumen transaksi, atau file perusahaan nyata sebagai contoh di repository publik.
- Tinjau seluruh template DOCX sebelum membuat repository public karena metadata dan isi dokumen dapat memuat identitas perusahaan.
- Atur backup database dan file upload secara terpisah dari source code.
- Jangan mengunggah kredensial Google Drive, Gemini, AWS, email, atau token API ke GitHub.
- Periksa git history apabila secret pernah ter-commit. Menghapus file pada commit terbaru tidak menghapus secret dari history lama; secret yang terekspos harus segera dicabut dan diganti.

Repository ini berisi template dokumen dan konfigurasi aplikasi. Pemilik deployment bertanggung jawab memastikan template, data seed, file upload, dan konfigurasi lokal tidak mengandung data rahasia sebelum repository dipublikasikan.

## Status Pengembangan

Fokus aplikasi saat ini adalah administrasi dokumen dan master data untuk operasi internal. Beberapa komponen backend dapat berkembang lebih cepat daripada halaman frontend, sehingga endpoint atau model yang tersedia di source code belum tentu sudah menjadi workflow UI yang lengkap.

Pengujian otomatis perlu ditambahkan kembali sebelum aplikasi digunakan dalam proses bisnis kritis atau deployment production.

