# Dokumentasi Fitur API

Project API adalah backend Laravel untuk seluruh ekosistem Kuy Dinas. API ini melayani autentikasi, bank soal, tryout, wallet, ranking, dan integrasi pembayaran.

## Area fitur utama

### 1. Health check

Route utama:

- `GET /api/ping`

Fitur:

- memastikan API dapat diakses dari client atau admin

### 2. Autentikasi user

Controller utama:

- `App\Http\Controllers\Api\User\UserAuthController`
- `App\Http\Controllers\Api\User\GoogleAuthController`

Route utama:

- `POST /api/user/register`
- `POST /api/user/login`
- `GET /api/user/google/redirect`
- `GET /api/user/google/callback`
- `GET /api/user/me`
- `PUT /api/user/profile`
- `POST /api/user/logout`

Fitur:

- register user
- login user
- login Google untuk user
- ambil profil user aktif
- update profil user
- logout berbasis Sanctum

### 3. Autentikasi admin

Controller utama:

- `App\Http\Controllers\Api\AuthController`
- `App\Http\Controllers\Api\User\GoogleAuthController`

Route utama:

- `POST /api/admin/login`
- `GET /api/admin/google/redirect`
- `GET /api/admin/google/callback`
- `GET /api/admin/me`
- `POST /api/admin/logout`

Fitur:

- login admin
- login Google untuk admin
- ambil data admin aktif
- logout admin

### 4. Dashboard user

Controller utama:

- `App\Http\Controllers\Api\DashboardController`

Route utama:

- `GET /api/dashboard/summary`

Fitur:

- ringkasan dashboard user
- data statistik, tryout terakhir, tryout aktif, learning path, dan promo

### 5. Tryout untuk user

Controller utama:

- `App\Http\Controllers\Api\User\TryoutController`
- `App\Http\Controllers\Api\TryoutRegistrationController`
- `App\Http\Controllers\Api\TryoutController`
- `App\Http\Controllers\Api\TryoutResultController`
- `App\Http\Controllers\Api\RankingController`

Route utama:

- `GET /api/tryouts`
- `GET /api/tryouts/{id}`
- `POST /api/tryouts/{id}/register`
- `POST /api/tryouts/{id}/start`
- `POST /api/tryouts/{id}/autosave`
- `POST /api/tryouts/{id}/submit`
- `GET /api/tryouts/{id}/remaining-time`
- `GET /api/tryouts/{id}/result`
- `GET /api/tryouts/{id}/ranking`
- `GET /api/tryouts/{id}/my-rank`
- `GET /api/history`

Fitur:

- daftar tryout yang tersedia untuk user
- detail tryout
- registrasi tryout
- start sesi tryout
- autosave jawaban selama pengerjaan
- submit hasil tryout
- sisa waktu tryout
- hasil tryout per user
- ranking dan posisi user
- riwayat tryout user

### 6. Masa berlaku tryout gratis

Model dan controller terkait:

- `App\Models\Tryout`
- `App\Models\TryoutRegistration`
- `App\Http\Controllers\Api\TryoutController`
- `App\Http\Controllers\Api\TryoutRegistrationController`

Fitur:

- tryout gratis memiliki `free_valid_days`
- saat user registrasi tryout gratis, backend menyimpan `expires_at`
- backend menolak start jika masa berlaku habis
- registrasi ulang tryout gratis dapat memperbarui masa aktif jika implementasi mengizinkan

### 7. Bank soal admin

Controller utama:

- `App\Http\Controllers\Api\SoalController`
- `App\Http\Controllers\Api\AdminUploadController`

Route utama:

- `GET /api/admin/soal`
- `POST /api/admin/soal`
- `GET /api/admin/soal/{id}`
- `PUT /api/admin/soal/{id}`
- `DELETE /api/admin/soal/{id}`
- `POST /api/admin/uploads/images`

Fitur:

- CRUD soal untuk `TWK`, `TIU`, `TKP`
- validasi opsi jawaban dan skor TKP
- rich text HTML untuk pertanyaan, opsi, dan pembahasan
- upload gambar ke storage Laravel melalui endpoint admin
- konversi otomatis embedded image base64 menjadi file storage saat create atau update
- pembatasan ukuran gambar per file

### 8. Builder tryout admin

Controller utama:

- `App\Http\Controllers\Api\TryoutController`

Route utama:

- `GET /api/admin/tryouts`
- `GET /api/admin/tryouts/{id}`
- `POST /api/admin/tryouts`
- `PUT /api/admin/tryouts/{id}`
- `DELETE /api/admin/tryouts/{id}`
- `POST /api/admin/tryouts/{id}/attach`
- `DELETE /api/admin/tryouts/{id}/detach/{soalId}`
- `PUT /api/admin/tryouts/{id}/reorder`
- `POST /api/admin/tryouts/{id}/publish`

Fitur:

- CRUD paket tryout
- target jumlah soal per kategori
- tipe gratis dan premium
- harga, diskon, kuota, dan durasi
- attach dan detach soal ke tryout
- reorder soal
- validasi komposisi sebelum publish

### 9. Wallet dan top up

Controller utama:

- `App\Http\Controllers\Api\WalletController`
- `App\Http\Controllers\Api\PaymentController`
- `App\Http\Controllers\Api\TopupPackageController`
- `App\Http\Controllers\Api\AdminTopupTransactionController`

Route utama:

- `GET /api/wallet`
- `GET /api/wallet/topup-packages`
- `POST /api/wallet/topup/create`
- `GET /api/wallet/topup/{id}`
- `POST /api/wallet/topup/{id}/sync`
- `GET /api/wallet/redeemable-tryouts`
- `POST /api/wallet/redeem-tryout/{id}`
- `POST /api/payments/midtrans/webhook`
- `GET /api/admin/topup-packages`
- `POST /api/admin/topup-packages`
- `PUT /api/admin/topup-packages/{id}`
- `DELETE /api/admin/topup-packages/{id}`

Fitur:

- saldo koin user
- gabungan riwayat transaksi wallet dan transaksi top up
- daftar paket top up aktif
- pembuatan transaksi top up
- integrasi pembayaran Midtrans Snap
- sinkronisasi status top up
- penukaran koin menjadi akses tryout premium
- webhook pembayaran dari Midtrans
- pengelolaan paket top up dari admin

### 10. Ranking

Controller utama:

- `App\Http\Controllers\Api\RankingController`

Fitur:

- daftar ranking peserta per tryout
- posisi ranking user yang sedang login

### 11. User management admin

Controller utama:

- `App\Http\Controllers\Api\AdminUserController`

Route utama:

- `GET /api/admin/users`
- `PATCH /api/admin/users/{id}`
- `GET /api/admin/users/count`
- `GET /api/admin/users/active-count`

Fitur:

- daftar akun user
- update data atau status user
- statistik jumlah user total dan aktif

## Middleware penting

Middleware utama:

- `auth:sanctum`
- `admin`
- `user`
- `ForceCorsHeaders`

Fungsi:

- autentikasi API berbasis Sanctum
- pembatasan akses role admin dan user
- penguatan header CORS untuk kebutuhan frontend lokal

## Model inti

Model yang menjadi pusat relasi:

- `User`
- `Soal`
- `Tryout`
- `TryoutRegistration`
- `TryoutResult`
- `TopupPackage`
- `TopupTransaction`
- `WalletTransaction`

## Catatan operasional

- Storage gambar soal memakai disk `public`
- Akses file gambar mengandalkan symlink `public/storage`
- Pembayaran top up terhubung ke Midtrans melalui service `MidtransSnapService`
- API dipakai bersama oleh dua frontend terpisah: client dan admin
