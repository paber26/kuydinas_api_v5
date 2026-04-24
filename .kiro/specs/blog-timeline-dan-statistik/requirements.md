# Requirements: Blog Timeline dan Statistik

## Requirement 1: Endpoint Publik Daftar Tryout

### User Story

Sebagai pengunjung blog, saya ingin melihat daftar tryout yang sedang aktif atau akan datang, sehingga saya tahu kapan bisa ikut tryout tanpa harus login terlebih dahulu.

### Acceptance Criteria

1. **GIVEN** pengunjung mengakses `GET /api/public/tryouts` tanpa token autentikasi, **WHEN** request dikirim, **THEN** server mengembalikan HTTP 200 dengan array tryout yang sudah dipublish.

2. **GIVEN** ada tryout dengan `status = 'publish'` di database, **WHEN** endpoint dipanggil, **THEN** hanya tryout dengan `status = 'publish'` yang muncul dalam response (tryout draft tidak ikut).

3. **GIVEN** response tryout publik, **WHEN** data diperiksa, **THEN** tidak ada field `price`, `discount`, `info_ig`, `info_wa`, data soal, atau data user individual dalam response.

4. **GIVEN** tryout free dengan `free_start_date` di masa depan, **WHEN** endpoint dipanggil, **THEN** `status_label` tryout tersebut adalah `"upcoming"`.

5. **GIVEN** tryout free dengan `free_valid_until` di masa lalu (lebih dari 7 hari), **WHEN** endpoint dipanggil, **THEN** tryout tersebut tidak muncul dalam response.

6. **GIVEN** tryout free tanpa `free_start_date` dan tanpa `free_valid_until`, **WHEN** endpoint dipanggil, **THEN** `status_label` adalah `"active"`.

7. **GIVEN** tryout premium/regular dengan `status = 'publish'`, **WHEN** endpoint dipanggil, **THEN** `status_label` adalah `"active"`.

8. **GIVEN** response berhasil, **WHEN** data diperiksa, **THEN** setiap tryout memiliki field: `id`, `title`, `type`, `duration`, `free_start_date`, `free_valid_until`, `status_label`, `quota`, `registrations_count`.

### Correctness Properties

- **Property 1.A**: ∀ tryout ∈ response.data: tryout.status_label ∈ {"upcoming", "active", "ended"}
- **Property 1.B**: ∀ tryout ∈ response.data: tryout tidak memiliki field price, discount, info_ig, info_wa
- **Property 1.C**: ∀ tryout dengan free_start_date > now: tryout.status_label = "upcoming"
- **Property 1.D**: ∀ tryout dengan free_valid_until < now.subDays(7): tryout tidak ada dalam response

---

## Requirement 2: Endpoint Publik Statistik Platform

### User Story

Sebagai pengunjung blog, saya ingin melihat berapa banyak orang yang sudah mendaftar dan mengerjakan tryout, sehingga saya merasa yakin platform ini sudah dipakai banyak orang (social proof).

### Acceptance Criteria

1. **GIVEN** pengunjung mengakses `GET /api/public/stats` tanpa token autentikasi, **WHEN** request dikirim, **THEN** server mengembalikan HTTP 200 dengan objek statistik.

2. **GIVEN** response stats, **WHEN** data diperiksa, **THEN** response memiliki field `total_registrations`, `total_completed`, dan `total_users` yang semuanya adalah integer non-negatif.

3. **GIVEN** data di database, **WHEN** endpoint dipanggil, **THEN** `total_completed` selalu lebih kecil atau sama dengan `total_registrations`.

4. **GIVEN** response stats, **WHEN** data diperiksa, **THEN** tidak ada data PII (nama, email, atau identifier user individual) dalam response.

5. **GIVEN** request pertama ke endpoint, **WHEN** request kedua dikirim dalam 10 menit, **THEN** response kedua menggunakan data yang di-cache (tidak query ulang ke database).

### Correctness Properties

- **Property 2.A**: response.data.total_completed ≤ response.data.total_registrations
- **Property 2.B**: ∀ field ∈ {total_registrations, total_completed, total_users}: field ≥ 0
- **Property 2.C**: response tidak mengandung field name, email, atau user_id individual

---

## Requirement 3: Rate Limiting dan Keamanan Endpoint Publik

### User Story

Sebagai pengelola platform, saya ingin endpoint publik dilindungi dari penyalahgunaan, sehingga server tidak kelebihan beban akibat scraping atau request berlebihan.

### Acceptance Criteria

1. **GIVEN** satu IP mengirim lebih dari 30 request dalam 1 menit ke `/api/public/tryouts` atau `/api/public/stats`, **WHEN** request ke-31 dikirim, **THEN** server mengembalikan HTTP 429 Too Many Requests.

2. **GIVEN** request ke endpoint publik dari origin blog (`kuydinas.id`), **WHEN** response diterima, **THEN** header `Access-Control-Allow-Origin` ada dan mengizinkan origin tersebut.

3. **GIVEN** request ke endpoint publik dari origin yang tidak diizinkan, **WHEN** response diterima, **THEN** CORS header tidak mengizinkan origin tersebut.

4. **GIVEN** endpoint publik, **WHEN** diakses, **THEN** tidak ada informasi internal sistem (stack trace, query SQL, nama tabel) yang terekspos dalam response error.

---

## Requirement 4: Section Timeline Tryout di Blog

### User Story

Sebagai pengunjung blog, saya ingin melihat jadwal tryout mendatang dan aktif dalam tampilan yang menarik, sehingga saya tahu kapan harus kembali atau langsung ikut tryout.

### Acceptance Criteria

1. **GIVEN** halaman blog dimuat dan API berhasil diakses, **WHEN** ada tryout aktif atau mendatang, **THEN** section timeline ditampilkan dengan card per tryout yang memuat: judul, tipe (gratis/premium), tanggal, durasi, dan status badge.

2. **GIVEN** halaman blog dimuat dan API berhasil diakses, **WHEN** tidak ada tryout aktif atau mendatang (array kosong), **THEN** section timeline disembunyikan (`display: none`) dan tidak ada error yang ditampilkan.

3. **GIVEN** halaman blog dimuat dan API tidak dapat diakses (network error atau timeout >3 detik), **WHEN** fetch gagal, **THEN** section timeline disembunyikan dan halaman tetap berfungsi normal tanpa error.

4. **GIVEN** tryout dengan `status_label = 'upcoming'`, **WHEN** card dirender, **THEN** badge menampilkan teks "Akan Datang" dengan warna yang berbeda dari badge "Sedang Berlangsung".

5. **GIVEN** tryout dengan `status_label = 'active'`, **WHEN** card dirender, **THEN** badge menampilkan teks "Sedang Berlangsung" dan tombol CTA mengarah ke `https://tryout.kuydinas.id`.

6. **GIVEN** section timeline dirender, **WHEN** dilihat di mobile (lebar < 640px), **THEN** layout tetap readable dan tidak overflow horizontal.

7. **GIVEN** halaman blog dimuat, **WHEN** fetch sedang berlangsung, **THEN** skeleton loading ditampilkan di posisi section timeline.

---

## Requirement 5: Counter Statistik Live di Blog

### User Story

Sebagai pengunjung blog, saya ingin melihat angka peserta yang akurat dan terkini, sehingga social proof yang ditampilkan terasa nyata dan meyakinkan.

### Acceptance Criteria

1. **GIVEN** halaman blog dimuat dan API stats berhasil diakses, **WHEN** data diterima, **THEN** counter "Peserta latihan" diupdate dengan nilai `total_registrations` dari API sebelum animasi counter berjalan.

2. **GIVEN** halaman blog dimuat dan API stats gagal atau timeout, **WHEN** fetch tidak berhasil, **THEN** counter menggunakan nilai statis dari attribute `data-counter` yang sudah ada (120.000, 3.200, 94%) dan animasi tetap berjalan normal.

3. **GIVEN** data live dari API, **WHEN** counter diupdate, **THEN** animasi counter (ease-out cubic) tetap berjalan dari 0 ke nilai baru, bukan langsung menampilkan angka akhir.

4. **GIVEN** nilai `total_registrations` dari API, **WHEN** ditampilkan, **THEN** angka diformat dengan separator ribuan Indonesia (contoh: `125.430+`).

5. **GIVEN** counter "Bank soal terkurasi" dan "Target lolos passing grade", **WHEN** halaman dimuat, **THEN** kedua counter ini tetap menggunakan nilai statis (tidak ada endpoint live untuk data ini).

---

## Requirement 6: Konfigurasi Cache dan Performa

### User Story

Sebagai pengelola platform, saya ingin endpoint publik tidak membebani database secara berlebihan, sehingga performa API untuk user yang login tidak terganggu.

### Acceptance Criteria

1. **GIVEN** endpoint `/api/public/tryouts` dipanggil, **WHEN** cache belum ada atau sudah expired, **THEN** query ke database dijalankan dan hasilnya disimpan di cache dengan TTL 5 menit.

2. **GIVEN** endpoint `/api/public/stats` dipanggil, **WHEN** cache belum ada atau sudah expired, **THEN** query ke database dijalankan dan hasilnya disimpan di cache dengan TTL 10 menit.

3. **GIVEN** cache aktif, **WHEN** endpoint dipanggil berulang kali dalam window TTL, **THEN** tidak ada query database tambahan yang dijalankan.

4. **GIVEN** query untuk `/api/public/tryouts`, **WHEN** dieksekusi, **THEN** menggunakan `withCount('registrations')` (single JOIN) bukan loop N+1 query.

---

## Requirement 7: Multi-Halaman dengan Navigasi

### User Story

Sebagai pengunjung blog, saya ingin bisa berpindah antar halaman (landing, jadwal, statistik) dengan mudah, sehingga saya bisa menemukan informasi yang saya cari tanpa kebingungan.

### Acceptance Criteria

1. **GIVEN** pengunjung berada di halaman manapun (index.html, jadwal.html, statistik.html), **WHEN** melihat navbar, **THEN** navbar menampilkan link: Fitur, Jadwal Tryout, Statistik, FAQ, dan tombol CTA "Mulai Tryout".

2. **GIVEN** pengunjung berada di halaman tertentu, **WHEN** melihat navbar, **THEN** link yang sesuai dengan halaman aktif di-highlight (warna berbeda dari link lain).

3. **GIVEN** pengunjung mengakses blog dari perangkat mobile (lebar < 640px), **WHEN** melihat navbar, **THEN** link navigasi tersembunyi dan digantikan hamburger menu yang bisa di-toggle.

4. **GIVEN** pengunjung mengklik hamburger menu di mobile, **WHEN** menu terbuka, **THEN** semua link navigasi dan CTA "Mulai Tryout" tampil dalam dropdown.

5. **GIVEN** halaman `index.html`, **WHEN** dimuat, **THEN** terdapat section teaser jadwal tryout yang menampilkan maksimal 3 tryout terbaru dengan link "Lihat Semua Jadwal →" ke `jadwal.html`.

6. **GIVEN** halaman `index.html`, **WHEN** dimuat, **THEN** terdapat section teaser statistik yang menampilkan 3 angka utama dengan link "Lihat Statistik Lengkap →" ke `statistik.html`.

7. **GIVEN** semua halaman blog, **WHEN** diperiksa, **THEN** terdapat internal linking yang menghubungkan antar halaman (navbar + in-content links).

### Correctness Properties

- **Property 7.A**: ∀ halaman ∈ {index.html, jadwal.html, statistik.html}: navbar memiliki link ke semua halaman lain
- **Property 7.B**: ∀ halaman: link aktif di navbar sesuai dengan halaman yang sedang dibuka

---

## Requirement 8: SEO Per Halaman

### User Story

Sebagai pengelola platform, saya ingin setiap halaman memiliki SEO yang dioptimalkan, sehingga traffic organik dari Google meningkat dan setiap halaman bisa diindeks secara terpisah.

### Acceptance Criteria

1. **GIVEN** halaman `index.html`, **WHEN** diperiksa meta tags-nya, **THEN** memiliki `<title>` unik, `<meta name="description">`, canonical URL, dan Open Graph tags (og:title, og:description, og:url, og:image).

2. **GIVEN** halaman `jadwal.html`, **WHEN** diperiksa meta tags-nya, **THEN** memiliki `<title>` yang mengandung kata kunci "Jadwal Tryout CPNS", meta description yang relevan, canonical URL `https://kuydinas.id/jadwal.html`, dan Open Graph tags.

3. **GIVEN** halaman `statistik.html`, **WHEN** diperiksa meta tags-nya, **THEN** memiliki `<title>` yang mengandung kata kunci "Statistik" atau "Platform Tryout CPNS", meta description yang relevan, canonical URL `https://kuydinas.id/statistik.html`, dan Open Graph tags.

4. **GIVEN** halaman `jadwal.html`, **WHEN** diperiksa structured data-nya, **THEN** terdapat JSON-LD dengan schema `Event` untuk setiap tryout yang ditampilkan (di-inject via JavaScript setelah data API diterima).

5. **GIVEN** halaman `statistik.html`, **WHEN** diperiksa structured data-nya, **THEN** terdapat JSON-LD dengan schema `Organization` yang mendeskripsikan Kuy Dinas.

6. **GIVEN** semua halaman, **WHEN** diperiksa heading hierarchy-nya, **THEN** terdapat tepat satu `<h1>` per halaman, diikuti `<h2>` dan `<h3>` secara hierarkis tanpa skip level.

7. **GIVEN** semua halaman, **WHEN** diperiksa, **THEN** terdapat `<meta name="robots" content="index, follow">` atau tidak ada robots meta (default index).

### Correctness Properties

- **Property 8.A**: ∀ halaman: `<title>` unik dan berbeda antar halaman
- **Property 8.B**: ∀ halaman: terdapat tepat satu `<h1>` element
- **Property 8.C**: ∀ halaman: canonical URL sesuai dengan URL halaman tersebut

---

## Requirement 9: Google Analytics Event Tracking

### User Story

Sebagai pengelola platform, saya ingin melacak perilaku pengguna di semua halaman, sehingga saya bisa mengoptimalkan konversi dan memahami halaman mana yang paling efektif.

### Acceptance Criteria

1. **GIVEN** semua halaman (index.html, jadwal.html, statistik.html), **WHEN** halaman dimuat, **THEN** Google Analytics tag (G-PP3FBJCGV6) ter-load dan pageview ter-track secara otomatis.

2. **GIVEN** pengunjung di `jadwal.html` mengklik tombol "Ikut Sekarang" pada card tryout, **WHEN** klik terjadi, **THEN** GA event `cta_click` ter-fire dengan parameter: `event_category: 'jadwal'`, `event_label: <judul tryout>`, `tryout_id: <id>`, `tryout_type: <free|premium>`.

3. **GIVEN** pengunjung di `jadwal.html` mengklik tab filter (Semua/Aktif/Akan Datang), **WHEN** klik terjadi, **THEN** GA event `filter_click` ter-fire dengan parameter: `event_category: 'jadwal'`, `event_label: <nilai filter>`.

4. **GIVEN** pengunjung di `statistik.html` men-scroll ke section statistik, **WHEN** section masuk viewport, **THEN** GA event `stats_viewed` ter-fire (satu kali per session).

5. **GIVEN** pengunjung di `index.html` mengklik link "Lihat Semua Jadwal" atau "Lihat Statistik Lengkap", **WHEN** klik terjadi, **THEN** GA event `internal_link_click` ter-fire dengan `event_label` yang sesuai.

6. **GIVEN** Google Analytics gagal dimuat (ad blocker, network error), **WHEN** event tracking dipanggil, **THEN** halaman tetap berfungsi normal tanpa error JavaScript (defensive check `typeof gtag !== 'undefined'`).

### Correctness Properties

- **Property 9.A**: ∀ halaman: GA script (G-PP3FBJCGV6) ada di `<head>` sebelum konten lain
- **Property 9.B**: ∀ event tracking call: ada pengecekan `typeof gtag !== 'undefined'` sebelum fire event

---

## Requirement 10: Halaman `jadwal.html`

### User Story

Sebagai pengunjung blog yang ingin tahu jadwal tryout, saya ingin ada halaman khusus yang menampilkan semua tryout aktif dan mendatang secara lengkap, sehingga saya bisa merencanakan kapan akan ikut tryout.

### Acceptance Criteria

1. **GIVEN** pengunjung membuka `jadwal.html`, **WHEN** halaman dimuat, **THEN** halaman menampilkan semua tryout aktif dan mendatang dari API (tidak dibatasi 3 item seperti di index.html).

2. **GIVEN** halaman `jadwal.html` dengan data tryout, **WHEN** pengunjung mengklik tab filter "Aktif", **THEN** hanya tryout dengan `status_label = 'active'` yang ditampilkan.

3. **GIVEN** halaman `jadwal.html` dengan data tryout, **WHEN** pengunjung mengklik tab filter "Akan Datang", **THEN** hanya tryout dengan `status_label = 'upcoming'` yang ditampilkan.

4. **GIVEN** halaman `jadwal.html` dengan data tryout, **WHEN** pengunjung mengklik tab filter "Semua", **THEN** semua tryout (active + upcoming) ditampilkan.

5. **GIVEN** tidak ada tryout aktif atau mendatang, **WHEN** `jadwal.html` dimuat, **THEN** halaman menampilkan pesan informatif "Belum ada tryout aktif saat ini. Pantau terus untuk update jadwal terbaru!" (bukan halaman kosong).

6. **GIVEN** card tryout di `jadwal.html`, **WHEN** dilihat, **THEN** menampilkan informasi lengkap: judul, tipe, tanggal mulai dan berakhir, durasi, kuota (jika ada), jumlah pendaftar, status badge, dan tombol CTA.

### Correctness Properties

- **Property 10.A**: ∀ tryout yang ditampilkan di jadwal.html: `status_label ∈ {'active', 'upcoming'}`

---

## Requirement 11: Halaman `statistik.html`

### User Story

Sebagai pengunjung blog yang ingin tahu seberapa besar platform ini, saya ingin ada halaman khusus yang menampilkan statistik lengkap, sehingga saya merasa yakin platform ini sudah terpercaya.

### Acceptance Criteria

1. **GIVEN** pengunjung membuka `statistik.html`, **WHEN** halaman dimuat, **THEN** halaman menampilkan minimal tiga angka statistik: total peserta, total tryout selesai, dan total user terdaftar.

2. **GIVEN** API stats berhasil diakses, **WHEN** data diterima, **THEN** angka statistik diupdate dengan data live sebelum animasi counter berjalan.

3. **GIVEN** API stats gagal, **WHEN** fetch tidak berhasil, **THEN** halaman menampilkan nilai statis fallback dan animasi counter tetap berjalan.

4. **GIVEN** halaman `statistik.html`, **WHEN** dilihat, **THEN** terdapat section testimonial atau social proof tambahan (minimal 3 testimonial).

5. **GIVEN** halaman `statistik.html`, **WHEN** dilihat di mobile, **THEN** layout stats grid responsif dan tidak overflow horizontal.

### Correctness Properties

- **Property 11.A**: ∀ angka statistik yang ditampilkan: nilai ≥ 0 dan diformat dengan separator ribuan
