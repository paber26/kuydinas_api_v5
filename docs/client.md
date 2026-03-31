# Dokumentasi Fitur Client

Project client adalah aplikasi Vue untuk peserta Kuy Dinas. Fokus utamanya adalah autentikasi user, pembelian atau penukaran tryout, pengerjaan tryout, dan melihat hasil.

## Modul utama

### 1. Autentikasi user

Lokasi utama:

- `src/views/Auth/Login.vue`
- `src/views/Auth/Register.vue`
- `src/views/Auth/GoogleCallback.vue`
- `src/router/index.js`

Fitur:

- login user dengan kredensial biasa
- register akun baru
- login dengan Google
- route guard berbasis token di `localStorage`
- redirect otomatis ke dashboard jika user sudah login

### 2. Dashboard user

Lokasi utama:

- `src/views/Dashboard/Dashboard.vue`
- `src/components/Dashboard/*`

Fitur:

- menampilkan ringkasan progress user
- menampilkan tryout terakhir
- menampilkan learning path
- menampilkan promo tryout
- menampilkan support card dan statistik utama

Data dashboard diambil dari backend melalui service dashboard.

### 3. Profil user

Lokasi utama:

- `src/views/Profile/Profile.vue`

Fitur:

- menampilkan data profil user
- mendukung pembaruan data profil melalui API user

### 4. Promo tryout dan katalog tryout

Lokasi utama:

- `src/views/PromoTryout/Promotryout.vue`
- `src/views/PromoTryout/TryoutGuide.vue`
- `src/components/PromoTryout/*`

Fitur:

- daftar tryout yang tersedia untuk user
- filter dan presentasi promo tryout
- halaman panduan sebelum user memulai tryout
- alur registrasi tryout sebelum pengerjaan

### 5. Pengerjaan tryout

Lokasi utama:

- `src/views/PengerjaanTryout/PengerjaanTryout.vue`
- `src/views/PengerjaanTryout/TryoutPreparation.vue`
- `src/views/PengerjaanTryout/TryoutSession.vue`

Fitur:

- halaman daftar tryout yang diikuti user
- halaman persiapan tryout
- sesi pengerjaan tryout dengan timer
- navigasi soal per kategori
- status soal: belum dijawab, terjawab, sedang dibuka, dan ragu-ragu
- autosave jawaban ke backend
- submit hasil tryout
- pengaturan ukuran font saat membaca soal
- render rich text HTML dari bank soal, termasuk gambar dari Laravel storage

### 6. Hasil tryout

Lokasi utama:

- `src/views/PengerjaanTryout/TryoutResult.vue`

Fitur:

- menampilkan hasil tryout per soal
- menampilkan jawaban user dan jawaban benar
- menampilkan pembahasan
- menampilkan skor dan status benar atau salah
- render HTML soal, opsi, pembahasan, dan gambar dari storage backend

### 7. Peringkat

Lokasi utama:

- `src/views/Peringkat/Peringkat.vue`

Fitur:

- melihat ranking tryout
- melihat posisi user dalam peringkat

### 8. Dompet dan top up

Lokasi utama:

- `src/views/Dompet/Dompet.vue`
- `src/views/Dompet/TopupPackages.vue`

Fitur:

- melihat saldo koin user
- melihat riwayat transaksi wallet dan top up
- menampilkan paket top up aktif
- membuat transaksi top up
- mendukung integrasi pembayaran Midtrans dari backend
- menukar koin dengan tryout premium
- menampilkan daftar tryout premium yang bisa diredeem

### 9. Materi SKD

Lokasi utama:

- `src/views/MateriSkd/Materiskd.vue`

Fitur:

- halaman materi atau konten pembelajaran terkait SKD

## Catatan teknis

- Router memakai `createWebHistory`
- Guard auth client ada di `src/router/index.js`
- Token akses disimpan di `localStorage`
- Konten soal sekarang mendukung rich text dan gambar dari endpoint storage Laravel

## Dependensi backend utama

Client bergantung pada fitur API berikut:

- autentikasi user
- dashboard summary
- daftar tryout dan detail tryout
- register, start, autosave, submit tryout
- hasil tryout dan ranking
- wallet, top up, redeem tryout
