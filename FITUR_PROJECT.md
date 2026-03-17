# Ringkasan Fitur Project

Dokumen ini merangkum fitur yang saat ini sudah terlihat dari codebase backend `be-kuy`.

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
