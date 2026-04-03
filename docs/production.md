# Setup Production

Dokumen ini merangkum setup production minimum untuk backend Laravel dan frontend user Kuydinas agar flow auth, verifikasi email, login Google, dan reset password berjalan stabil.

## 1. Backend env

Gunakan template:

- `.env.production.example`

Nilai penting yang wajib diisi:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://apikuy.kuydinas.id`
- `DB_*` sesuai database production
- `QUEUE_CONNECTION=database`
- `CACHE_STORE=database`
- `SESSION_DRIVER=database`
- `MAIL_MAILER=resend`
- `MAIL_FROM_ADDRESS` memakai sender/domain yang sudah diverifikasi di Resend
- `RESEND_API_KEY` memakai API key production yang valid
- `GOOGLE_CLIENT_ID`
- `GOOGLE_CLIENT_SECRET`
- `GOOGLE_REDIRECT_URI=https://apikuy.kuydinas.id/api/user/auth/google/callback`
- `FRONTEND_USER_URL=https://tryout.kuydinas.id`
- `FRONTEND_ADMIN_URL=https://kuymin.kuydinas.id`

## 2. Frontend env

Di repo client, gunakan template:

- `../kuydinas_client_v5/.env.production.example`

Nilai penting:

- `VITE_API_BASE_URL=https://apikuy.kuydinas.id/api`
- `VITE_USER_APP_URL=https://tryout.kuydinas.id`
- `VITE_ADMIN_APP_URL=https://kuymin.kuydinas.id`
- `VITE_MIDTRANS_CLIENT_KEY`
- `VITE_MIDTRANS_SNAP_URL=https://app.midtrans.com/snap/snap.js`

## 3. Resend

Untuk testing lokal Anda masih bisa memakai `onboarding@resend.dev`. Untuk production, gunakan sender dari domain sendiri.

Langkah minimum:

1. Tambahkan domain di Resend.
2. Ikuti DNS verification yang diminta Resend.
3. Buat API key production.
4. Isi `MAIL_FROM_ADDRESS` dengan alamat dari domain terverifikasi, misalnya `no-reply@kuydinas.id`.
5. Isi `RESEND_API_KEY` di backend production.

## 4. Google Login

Di Google Cloud Console:

1. Tambahkan origin frontend production.
2. Tambahkan redirect URI backend production.

Nilai redirect URI user yang dipakai backend:

- `https://apikuy.kuydinas.id/api/user/auth/google/callback`

Jika ada admin app terpisah, siapkan juga redirect URI admin yang sesuai.

## 5. Command backend setelah deploy

Jalankan di server backend:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Jika Anda memakai queue database untuk email atau job lain, jalankan worker juga.

Contoh:

```bash
php artisan queue:work --tries=3 --timeout=120
```

## 6. Command frontend

Di repo client:

```bash
npm install
npm run build
```

Hasil build ada di folder `dist/` dan siap dipasang ke web server atau static hosting.

## 7. Checklist auth production

Sebelum go-live, uji end-to-end:

1. Register manual berhasil.
2. Email verifikasi masuk ke inbox.
3. Link verifikasi membuka app yang benar.
4. Login manual berhasil.
5. Login Google langsung dianggap verified.
6. Forgot password untuk akun manual berhasil kirim email.
7. Forgot password untuk akun Google menampilkan arahan login Google.
8. Ubah email di profil memicu verifikasi ulang.

## 8. Catatan keamanan

- Jangan commit `.env` production ke repository.
- Rotate secret lama yang sempat dipakai saat testing.
- Gunakan sender domain Resend milik sendiri, bukan sender testing, untuk production.
- Pastikan `APP_DEBUG=false`.
