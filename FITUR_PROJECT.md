# Ringkasan Fitur Project

Dokumen ini merangkum fitur yang saat ini sudah terlihat dari codebase backend `kuydinas_api_v5`.

## Gambaran Umum

Project ini adalah backend Laravel 12 untuk sistem tryout berbasis API. Fokus utama aplikasinya mencakup:

- autentikasi user dan admin
- manajemen bank soal
- manajemen tryout
- pengerjaan tryout oleh user
- hasil dan ranking tryout
- wallet koin
- top up via Midtrans

Autentikasi API menggunakan Laravel Sanctum, dan login Google menggunakan Laravel Socialite.

## Modul Fitur yang Sudah Ada

### 1. Autentikasi User

Fitur yang tersedia:

- registrasi user dengan email dan password
- login user dengan validasi akun aktif
- pembatasan role agar endpoint user hanya dipakai akun `user`
- endpoint profil user login (`/user/me`)
- verifikasi email (link verifikasi dari backend) dan kirim ulang email verifikasi
- forgot password dan reset password (khusus akun manual yang emailnya sudah terverifikasi)
- logout user
- pembuatan token API menggunakan Sanctum
- pencatatan device login ke tabel `user_devices`
- update `last_login`

Endpoint terkait ada di grup `/api/user`.

### 2. Autentikasi Admin

Fitur yang tersedia:

- login admin dengan email dan password
- validasi akun aktif
- pembatasan role agar endpoint admin hanya dipakai akun `admin`
- endpoint profil admin login (`/admin/me`)
- logout admin
- pembuatan token API admin menggunakan Sanctum
- pencatatan device login

Endpoint terkait ada di grup `/api/admin`.

### 3. Login Google

Fitur yang tersedia:

- redirect ke Google OAuth
- callback dari Google
- pembuatan atau update user berdasarkan email Google
- generate token login setelah autentikasi sukses
- redirect kembali ke frontend dengan token pada query string

Endpoint Google tersedia untuk user dan admin, tetapi implementasi callback saat ini membuat akun dengan role `user`.

### 4. Manajemen Soal

Fitur yang tersedia untuk admin:

- CRUD soal
- filter daftar soal berdasarkan `status`
- filter daftar soal berdasarkan `category`
- filter soal yang sudah dipakai atau belum dipakai di tryout
- pagination pada daftar soal

Aturan data yang sudah diterapkan:

- kategori soal mendukung `TWK`, `TIU`, dan `TKP`
- `TWK/TIU` wajib memiliki `correct_answer`
- `TKP` wajib memiliki skor pada setiap opsi
- soal yang sudah dipakai di tryout tidak bisa dihapus

Endpoint utama menggunakan resource `/api/admin/soal`.

### 5. Manajemen Tryout oleh Admin

Fitur yang tersedia:

- melihat daftar semua tryout
- melihat detail tryout beserta soal
- membuat tryout baru
- mengubah tryout draft
- menghapus tryout
- publish tryout
- attach soal ke tryout
- detach soal dari tryout
- reorder urutan soal dalam tryout

Atribut tryout yang sudah dipakai di code:

- judul
- durasi
- status `draft/publish`
- tipe `free/premium`
- kuota peserta
- harga
- diskon
- target jumlah soal per kategori
- passing grade per kategori
- masa akses tryout gratis: `free_start_date` dan `free_valid_until` (opsional)
- link informasi tryout gratis: `info_ig` dan `info_wa` (opsional)

Validasi penting:

- tryout yang sudah `publish` tidak boleh diubah sembarangan
- soal tidak boleh melebihi kuota kategori
- publish hanya bisa dilakukan jika komposisi TWK, TIU, dan TKP sudah sesuai target

### 6. Katalog Tryout untuk User

Fitur yang tersedia:

- melihat daftar tryout yang sudah publish
- melihat detail tryout yang sudah publish
- response sudah disiapkan dengan field tambahan untuk frontend seperti:
    - `subtitle`
    - `category`
    - `isFree`
    - `questionCount`
    - `seatsLeft`
    - `highlight`
    - `tag`
    - `level`

### 7. Registrasi Tryout

Fitur yang tersedia:

- user dapat mendaftar ke tryout yang sudah publish
- pencegahan daftar ganda
- pengecekan kuota tryout
- riwayat pendaftaran tryout user

Status registrasi yang dipakai:

- `registered`
- `started`
- `completed`

### 8. Pengerjaan Tryout

Fitur yang tersedia:

- mulai sesi tryout
- mengambil daftar soal sesuai urutan
- menyimpan waktu mulai
- menghitung waktu selesai berdasarkan durasi tryout
- autosave jawaban
- cek sisa waktu pengerjaan
- submit jawaban

Logika penilaian:

- `TWK/TIU` dinilai berdasarkan kecocokan dengan `correct_answer`
- `TKP` dinilai berdasarkan skor pada opsi yang dipilih
- jumlah jawaban benar juga disimpan pada `correct_answer`

### 9. Hasil Tryout

Fitur yang tersedia:

- melihat hasil tryout milik user yang login
- menampilkan:
    - skor
    - jumlah jawaban benar
    - jawaban yang tersimpan
    - waktu selesai

### 10. Ranking Tryout

Fitur yang tersedia:

- leaderboard tryout
- rank user pada tryout tertentu
- cache ranking untuk mengurangi query berulang
- cache ranking dibersihkan saat user submit hasil tryout

### 11. Wallet Koin

Fitur yang tersedia:

- melihat saldo koin user
- melihat histori transaksi wallet
- melihat daftar tryout premium yang bisa ditukar
- redeem tryout premium menggunakan saldo koin

Perilaku yang sudah diterapkan:

- saldo user dikurangi saat redeem tryout
- transaksi wallet dicatat
- registrasi tryout dibuat otomatis setelah redeem berhasil

### 12. Top Up via Midtrans

Fitur yang tersedia:

- melihat daftar paket top up aktif
- membuat transaksi top up
- generate `snap_token` dan `redirect_url` dari Midtrans Snap
- melihat detail transaksi top up
- webhook Midtrans untuk update status pembayaran
- kredit saldo koin user setelah pembayaran sukses
- pencatatan transaksi top up ke wallet

Status pembayaran yang terlihat di code:

- `pending`
- `paid`
- `failed`
- `cancelled`
- `expired`

### Pembaruan Terbaru (Maret–April 2026)

- Endpoint sinkronisasi manual status Midtrans:
    - `POST /api/wallet/topup/{id}/sync` untuk menarik status transaksi langsung dari API Midtrans dan mengkredit koin jika status sukses (`settlement/capture`). Lihat [WalletController.php](file:///Users/marchelinoraco/Documents/2026/kuy/kuydinas_api_v5/app/Http/Controllers/Api/WalletController.php#L236-L319) dan [api.php](file:///Users/marchelinoraco/Documents/2026/kuy/kuydinas_api_v5/routes/api.php#L95-L140).
- Verifikasi signature webhook Midtrans dan pengecekan nominal:
    - Webhook memverifikasi `signature_key` dan menyamakan `gross_amount` sebelum memproses kredit koin. Lihat [PaymentController.php](file:///Users/marchelinoraco/Documents/2026/kuy/kuydinas_api_v5/app/Http/Controllers/Api/PaymentController.php#L22-L244).
- Pemetaan status dan idempoten kredit:
    - Status Midtrans dipetakan ke status lokal (`paid/pending/failed/...`) dan sistem memastikan kredit koin hanya terjadi sekali. Lihat [PaymentController.php](file:///Users/marchelinoraco/Documents/2026/kuy/kuydinas_api_v5/app/Http/Controllers/Api/PaymentController.php#L117-L244).
- Layanan status Midtrans Snap:
    - Penambahan fungsi `getTransactionStatus` untuk memanggil `v2/{order_id}/status`. Lihat [MidtransSnapService.php](file:///Users/marchelinoraco/Documents/2026/kuy/kuydinas_api_v5/app/Services/MidtransSnapService.php#L29-L37).
- Konfigurasi CORS produksi:
    - Domain frontend yang diizinkan dan pengaturan CORS ada di [cors.php](file:///Users/marchelinoraco/Documents/2026/kuy/kuydinas_api_v5/config/cors.php).
- Callback Snap:
    - `wallet.topup_finish_url` diambil dari `.env` melalui [wallet.php](file:///Users/marchelinoraco/Documents/2026/kuy/kuydinas_api_v5/config/wallet.php).
- Masa akses tryout gratis dan link informasi:
    - Penambahan `free_start_date`, `free_valid_until`, `info_ig`, dan `info_wa` pada tryout untuk mengatur kapan akses gratis dibuka/ditutup serta link panduan. Lihat [Tryout.php](file:///Users/marchelinoraco/Documents/2026/kuy/kuydinas_api_v5/app/Models/Tryout.php#L7-L33) dan [TryoutController.php](file:///Users/marchelinoraco/Documents/2026/kuy/kuydinas_api_v5/app/Http/Controllers/Api/TryoutController.php#L69-L119).
- Profil user lebih lengkap:
    - Penambahan field kontak dan domisili user (WhatsApp, provinsi/kabupaten/kecamatan) serta endpoint proxy wilayah. Lihat [UserAuthController.php](file:///Users/marchelinoraco/Documents/2026/kuy/kuydinas_api_v5/app/Http/Controllers/Api/User/UserAuthController.php#L135-L381) dan [RegionController.php](file:///Users/marchelinoraco/Documents/2026/kuy/kuydinas_api_v5/app/Http/Controllers/Api/RegionController.php#L1-L59).

## Entitas Data yang Sudah Dipakai

Beberapa model utama yang sudah ada:

- `User`
- `UserDevice`
- `Soal`
- `Tryout`
- `TryoutRegistration`
- `TryoutResult`
- `TopupPackage`
- `TopupTransaction`
- `WalletTransaction`

## Catatan Teknis

Ada beberapa fitur yang sudah dipakai di controller/model tetapi belum sepenuhnya tercermin di migration Laravel bawaan project. Dari struktur repo, sebagian kebutuhan schema tambahan masih disediakan lewat file manual:

- `database/manual_sql/wallet.sql`
- `database/manual_sql/midtrans_topup.sql`

Artinya, untuk fitur wallet, top up, dan sebagian atribut user/tryout berjalan penuh, database kemungkinan masih membutuhkan setup tambahan di luar migration default.

## Kesimpulan

Secara fitur, project ini sudah mencakup alur backend tryout yang cukup lengkap:

- autentikasi user/admin
- bank soal
- lifecycle tryout dari draft sampai publish
- registrasi dan pengerjaan tryout
- hasil dan ranking
- monetisasi melalui wallet koin dan Midtrans

Dokumen ini merangkum fitur berdasarkan code yang sudah ada saat ini, bukan berdasarkan roadmap atau rencana fitur berikutnya.
