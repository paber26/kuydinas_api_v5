# Dokumentasi Fitur Admin

Project admin adalah panel Vue untuk tim internal Kuy Dinas. Fokusnya adalah pengelolaan bank soal, penyusunan tryout, paket top up, dan akun user.

## Modul utama

### 1. Autentikasi admin

Lokasi utama:

- `src/components/Auth/Login.vue`
- `src/components/Auth/GoogleCallback.vue`
- `src/router/index.js`

Fitur:

- login admin
- login admin dengan Google
- guard route berbasis token dan role
- user non-admin otomatis dibersihkan sesi aksesnya dari panel admin

### 2. Dashboard admin

Lokasi utama:

- `src/components/Dashboard.vue`

Fitur:

- ringkasan cepat aktivitas admin
- pintasan ke modul pengelolaan utama

### 3. Bank soal

Lokasi utama:

- `src/components/BankSoal/BankSoal.vue`
- `src/components/BankSoal/BankSoalCreate.vue`
- `src/components/BankSoal/EditModal.vue`
- `src/components/BankSoal/PreviewModal.vue`
- `src/components/BankSoal/BankSoalEditor.js`

Fitur:

- daftar soal dengan status dan kategori
- tambah soal baru untuk `TWK`, `TIU`, dan `TKP`
- edit dan preview soal
- dukungan rich text untuk pertanyaan, opsi, dan pembahasan
- dukungan upload gambar ke storage Laravel melalui API admin
- resize gambar di dalam CKEditor
- preview konten rich text di form
- validasi kategori dan jawaban benar
- dukungan skor per opsi untuk kategori `TKP`

### 4. Builder tryout

Lokasi utama:

- `src/components/Tryout/TryoutBuilder.vue`
- `src/components/Tryout/TryoutCreate.vue`
- `src/components/Tryout/TryoutDetail.vue`
- `src/components/Tryout/TryoutManage.vue`
- `src/components/Tryout/BankSoalTable.vue`
- `src/components/Tryout/TryoutQuestionTable.vue`
- `src/components/Tryout/CategorySection.vue`
- `src/components/Tryout/CategoryFilter.vue`

Fitur:

- membuat paket tryout baru
- menentukan tipe tryout: gratis atau premium
- mengatur durasi, kuota, harga, diskon, dan target jumlah soal per kategori
- mengatur masa berlaku tryout gratis dalam hitungan hari
- melihat detail tryout dan komposisi soal
- menambahkan soal dari bank soal ke tryout
- melepas soal dari tryout
- reorder urutan soal
- validasi komposisi kategori sebelum publish
- publish dan kelola status tryout

### 5. Manajemen paket top up

Lokasi utama:

- `src/components/Topup/TopupPackages.vue`
- `src/components/Topup/TopupPackageForm.vue`

Fitur:

- melihat daftar paket top up
- membuat dan mengubah paket top up
- mengelola nominal harga, jumlah koin, bonus koin, dan status aktif

### 6. Manajemen akun user

Lokasi utama:

- `src/components/Users/Accounts.vue`

Fitur:

- melihat daftar akun user
- mengubah status atau informasi akun sesuai endpoint admin yang tersedia
- melihat statistik jumlah user

### 7. Komponen utilitas

Lokasi utama:

- `src/components/Toast/BaseToast.vue`
- `src/services/api.js`
- `src/services/topupPackagesApi.js`
- `src/services/adminUsersApi.js`

Fitur:

- toast notifikasi global
- wrapper API untuk request admin
- service khusus untuk top up packages dan akun user

## Catatan teknis

- Route admin diamankan dengan token dan pemeriksaan role `admin`
- Upload gambar bank soal tidak lagi memakai base64 untuk penyimpanan akhir
- Editor rich text admin memakai CKEditor custom dengan integrasi upload Laravel

## Dependensi backend utama

Admin bergantung pada fitur API berikut:

- auth admin dan callback Google
- CRUD soal
- upload gambar soal
- CRUD tryout dan pengelolaan soal dalam tryout
- CRUD paket top up
- daftar dan update akun user
- ringkasan transaksi top up
