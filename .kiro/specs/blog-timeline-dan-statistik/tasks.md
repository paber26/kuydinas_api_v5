# Tasks: Blog Timeline dan Statistik

## Task 1: Buat PublicTryoutController di Laravel

- [x] 1.1 Buat file `app/Http/Controllers/Api/PublicTryoutController.php` dengan method `index()` yang:
    - Query tryout dengan `status = 'publish'` menggunakan `withCount('registrations')`
    - Hitung `status_label` (upcoming/active/ended) berdasarkan `free_start_date` dan `free_valid_until`
    - Filter out tryout dengan `status_label = 'ended'` yang berakhir lebih dari 7 hari lalu
    - Return hanya field publik: `id`, `title`, `type`, `duration`, `free_start_date`, `free_valid_until`, `status_label`, `quota`, `registrations_count`
    - Wrap response dalam Laravel Cache dengan TTL 5 menit (key: `public_tryouts`)

- [x] 1.2 Buat file `app/Http/Controllers/Api/PublicStatsController.php` dengan method `index()` yang:
    - Hitung `total_registrations` dari `TryoutRegistration::count()`
    - Hitung `total_completed` dari `TryoutRegistration::where('status', 'completed')->count()`
    - Hitung `total_users` dari `User::count()`
    - Wrap response dalam Laravel Cache dengan TTL 10 menit (key: `public_stats`)
    - Return hanya tiga angka agregat tanpa data PII

- [x] 1.3 Daftarkan kedua route baru di `routes/api.php` sebagai route publik (di luar middleware auth):

    ```php
    Route::middleware('throttle:30,1')->group(function () {
        Route::get('/public/tryouts', [PublicTryoutController::class, 'index']);
        Route::get('/public/stats', [PublicStatsController::class, 'index']);
    });
    ```

- [x] 1.4 Verifikasi konfigurasi CORS di `config/cors.php` mengizinkan origin blog (`kuydinas.id` dan `localhost` untuk dev)

## Task 2: Tambah Section Timeline Tryout di Blog HTML

- [x] 2.1 Tambahkan section HTML baru `#timeline-tryout` di antara section social proof dan section fitur di `kuydinas_blog_v5/index.html`, dengan:
    - Container dengan class `reveal` untuk animasi scroll
    - Heading section dengan label "Timeline Tryout"
    - Grid container `#timeline-tryout-grid` untuk card dinamis
    - Skeleton loading placeholder (3 card abu-abu) yang ditampilkan saat fetch berlangsung
    - Section diberi `id="timeline-tryout"` dan awalnya `style="display:none"` (ditampilkan via JS setelah data ada)

- [x] 2.2 Buat fungsi JavaScript `buildTryoutCard(tryout)` yang menghasilkan HTML string card dengan:
    - Badge status: "Sedang Berlangsung" (warna mint/hijau) atau "Akan Datang" (warna gold/kuning)
    - Judul tryout
    - Badge tipe: "Gratis" (warna tide) atau "Premium" (warna coral)
    - Rentang tanggal (jika ada `free_start_date` atau `free_valid_until`)
    - Durasi dalam menit
    - Tombol CTA "Ikut Sekarang" yang mengarah ke `https://tryout.kuydinas.id`
    - Styling konsisten dengan tema blog (ink, mist, tide, mint, coral, gold, sky)

- [x] 2.3 Buat fungsi `renderTimelineSection(tryouts)` yang:
    - Filter tryout dengan `status_label !== 'ended'`
    - Jika array kosong: sembunyikan section
    - Jika ada data: render card ke `#timeline-tryout-grid`, sembunyikan skeleton, tampilkan section

## Task 3: Update Counter Statistik di Blog HTML

- [x] 3.1 Tambahkan attribute `data-live-key="total_registrations"` pada elemen counter "Peserta latihan" yang sudah ada di `kuydinas_blog_v5/index.html`

- [x] 3.2 Buat fungsi JavaScript `updateCountersWithLiveData(stats)` yang:
    - Cari elemen dengan `data-live-key="total_registrations"`
    - Update nilai `data-counter` attribute dengan `stats.total_registrations`
    - Dipanggil sebelum `startCounterAnimations()`

## Task 4: Implementasi Fetch Logic di Blog HTML

- [x] 4.1 Buat fungsi `fetchWithTimeout(url, ms)` yang:
    - Menggunakan `AbortController` untuk timeout
    - Return Promise yang resolve dengan parsed JSON
    - Reject jika timeout atau network error

- [x] 4.2 Buat fungsi async `initBlogDynamicData()` yang:
    - Gunakan `Promise.allSettled` untuk fetch paralel ke `/api/public/tryouts` dan `/api/public/stats`
    - Handle setiap result secara independen (satu gagal tidak mempengaruhi yang lain)
    - Panggil `renderTimelineSection` jika tryouts berhasil
    - Panggil `updateCountersWithLiveData` jika stats berhasil
    - Selalu panggil `startCounterAnimations()` di akhir (baik berhasil maupun gagal)

- [x] 4.3 Konfigurasi API base URL sebagai konstanta di atas script:

    ```javascript
    const API_BASE = "https://api.kuydinas.id"; // atau sesuai env
    ```

- [x] 4.4 Panggil `initBlogDynamicData()` di event `DOMContentLoaded` (sebelum atau menggantikan inisialisasi counter yang ada)

## Task 5: Refactor Counter Animation

- [x] 5.1 Ekstrak logika counter animation yang sudah ada menjadi fungsi `startCounterAnimations()` yang bisa dipanggil secara eksplisit (bukan langsung di IntersectionObserver setup)

- [x] 5.2 Pastikan `startCounterAnimations()` membaca nilai `data-counter` terbaru saat dipanggil (setelah `updateCountersWithLiveData` sudah mengupdate nilainya)

## Task 6: Buat Shared Navbar Component

- [x] 6.1 Buat file `kuydinas_blog_v5/assets/navbar.js` yang berisi fungsi `injectNavbar(currentPage)`:
    - Parameter `currentPage` ∈ `{"index", "jadwal", "statistik"}`
    - Render navbar HTML dengan link: Fitur (`index.html#fitur`), Jadwal Tryout (`jadwal.html`), Statistik (`statistik.html`), FAQ (`index.html#faq`)
    - Highlight link aktif berdasarkan `currentPage` dengan class `text-coral font-bold`
    - Sertakan tombol CTA "Mulai Tryout" yang selalu tampil
    - Sertakan hamburger button untuk mobile (toggle class `hidden` pada mobile menu)
    - Mobile menu berisi semua link + CTA dalam dropdown

- [x] 6.2 Update `kuydinas_blog_v5/index.html` untuk menggunakan shared navbar:
    - Tambahkan `<script src="assets/navbar.js"></script>` di `<head>`
    - Panggil `injectNavbar('index')` di `DOMContentLoaded`
    - Hapus/replace navbar HTML yang sudah ada dengan `<header id="main-header"></header>` sebagai mount point
    - Tambahkan link "Jadwal Tryout" dan "Statistik" ke navbar (via navbar.js)

## Task 7: Buat Halaman `jadwal.html`

- [x] 7.1 Buat file `kuydinas_blog_v5/jadwal.html` dengan struktur HTML lengkap:
    - `<head>` dengan SEO tags:
        - `<title>Jadwal Tryout CPNS 2025 | Kuy Dinas</title>`
        - `<meta name="description" content="Lihat jadwal tryout CPNS SKD terbaru di Kuy Dinas. Tryout gratis dan premium dengan simulasi CAT realistis, pembahasan lengkap, dan ranking nasional.">`
        - `<link rel="canonical" href="https://kuydinas.id/jadwal.html">`
        - Open Graph tags: `og:title`, `og:description`, `og:url`, `og:image`, `og:type`
        - Google Analytics tag (G-PP3FBJCGV6) — sama persis dengan index.html
        - Tailwind CSS CDN + konfigurasi warna tema (ink, mist, tide, mint, coral, gold, sky)
        - Google Fonts (Plus Jakarta Sans)
    - `<header id="main-header"></header>` — mount point untuk shared navbar
    - Hero section dengan `<h1>Jadwal Tryout CPNS Kuy Dinas</h1>` dan subtitle
    - Filter tab bar: "Semua" | "Aktif" | "Akan Datang" (default: Semua)
    - Grid container `#jadwal-tryout-grid` untuk card dinamis
    - Skeleton loading placeholder (3 card abu-abu)
    - Empty state message (tersembunyi by default): "Belum ada tryout aktif saat ini..."
    - CTA banner bawah: "Daftar Sekarang" → `https://tryout.kuydinas.id`
    - Footer dengan internal links ke index.html dan statistik.html

- [x] 7.2 Tambahkan JavaScript di `jadwal.html` untuk:
    - Panggil `injectNavbar('jadwal')` di `DOMContentLoaded`
    - Fetch data dari `/api/public/tryouts` dengan `fetchWithTimeout`
    - Render semua tryout (tidak dibatasi 3 item) ke `#jadwal-tryout-grid`
    - Implementasi filter tab: klik tab memfilter card yang tampil tanpa re-fetch
    - Tampilkan empty state jika tidak ada tryout setelah filter
    - GA event tracking: `cta_click` saat klik "Ikut Sekarang", `filter_click` saat klik tab filter

- [x] 7.3 Inject JSON-LD Event schema via JavaScript setelah data API diterima:
    ```javascript
    // Untuk setiap tryout yang ditampilkan, inject ke <head>
    const schema = {
        "@context": "https://schema.org",
        "@type": "Event",
        name: tryout.title,
        startDate: tryout.free_start_date,
        endDate: tryout.free_valid_until,
        eventStatus: "https://schema.org/EventScheduled",
        eventAttendanceMode: "https://schema.org/OnlineEventAttendanceMode",
        organizer: {
            "@type": "Organization",
            name: "Kuy Dinas",
            url: "https://kuydinas.id",
        },
    };
    ```

## Task 8: Buat Halaman `statistik.html`

- [x] 8.1 Buat file `kuydinas_blog_v5/statistik.html` dengan struktur HTML lengkap:
    - `<head>` dengan SEO tags:
        - `<title>Statistik Platform Tryout CPNS | Kuy Dinas</title>`
        - `<meta name="description" content="Lebih dari 120.000 peserta sudah latihan di Kuy Dinas. Lihat statistik lengkap platform tryout CPNS terpercaya dengan simulasi CAT realistis.">`
        - `<link rel="canonical" href="https://kuydinas.id/statistik.html">`
        - Open Graph tags: `og:title`, `og:description`, `og:url`, `og:image`, `og:type`
        - JSON-LD Organization schema (statis, tidak perlu JS inject):
            ```json
            {
                "@context": "https://schema.org",
                "@type": "Organization",
                "name": "Kuy Dinas",
                "url": "https://kuydinas.id",
                "description": "Platform tryout CPNS dengan simulasi CAT realistis"
            }
            ```
        - Google Analytics tag (G-PP3FBJCGV6)
        - Tailwind CSS CDN + konfigurasi warna tema
        - Google Fonts (Plus Jakarta Sans)
    - `<header id="main-header"></header>` — mount point untuk shared navbar
    - Hero section dengan `<h1>Statistik Platform Kuy Dinas</h1>` dan subtitle
    - Stats grid (3 kolom): Total Peserta, Tryout Selesai, User Terdaftar — dengan `data-counter` dan `data-live-key`
    - Section statistik tambahan: passing grade rate (statis), bank soal (statis)
    - Section testimonial (minimal 3 testimonial)
    - CTA banner bawah
    - Footer dengan internal links ke index.html dan jadwal.html

- [x] 8.2 Tambahkan JavaScript di `statistik.html` untuk:
    - Panggil `injectNavbar('statistik')` di `DOMContentLoaded`
    - Fetch data dari `/api/public/stats` dengan `fetchWithTimeout`
    - Update counter dengan data live (`updateCountersWithLiveData`)
    - Jalankan animasi counter (`startCounterAnimations`)
    - GA event tracking: `stats_viewed` saat stats section masuk viewport (IntersectionObserver, satu kali)

## Task 9: Update `index.html` — Teaser Section dan Navbar

- [x] 9.1 Update navbar di `index.html`:
    - Ganti navbar HTML yang ada dengan `<header id="main-header"></header>`
    - Tambahkan `<script src="assets/navbar.js"></script>` di `<head>`
    - Panggil `injectNavbar('index')` di `DOMContentLoaded`

- [x] 9.2 Tambahkan section teaser jadwal tryout di `index.html` (setelah section social proof, sebelum section fitur):
    - Heading: "Tryout Aktif & Mendatang"
    - Grid container `#index-jadwal-preview` untuk maksimal 3 card tryout
    - Skeleton loading placeholder
    - Link "Lihat Semua Jadwal →" ke `jadwal.html`
    - Section tersembunyi by default, tampil via JS jika ada data

- [x] 9.3 Tambahkan section teaser statistik di `index.html` (setelah section teaser jadwal):
    - Heading: "Platform yang Sudah Dipercaya"
    - 3 angka statistik dengan `data-counter` dan `data-live-key`
    - Link "Lihat Statistik Lengkap →" ke `statistik.html`
    - GA event tracking: `internal_link_click` saat klik kedua link teaser

- [x] 9.4 Update `initBlogDynamicData()` di `index.html` untuk:
    - Fetch tryouts dan render ke `#index-jadwal-preview` (max 3 item)
    - Fetch stats dan update counter di section teaser statistik
    - GA event tracking untuk link "Lihat Semua Jadwal" dan "Lihat Statistik Lengkap"

## Task 10: SEO Implementation Per Halaman

- [x] 10.1 Verifikasi dan lengkapi SEO tags di `index.html`:
    - Pastikan `<title>` sudah ada dan deskriptif
    - Tambahkan `<link rel="canonical" href="https://kuydinas.id/">` jika belum ada
    - Tambahkan Open Graph tags jika belum ada: `og:title`, `og:description`, `og:url`, `og:image`, `og:type`
    - Tambahkan `<meta name="robots" content="index, follow">` jika belum ada
    - Verifikasi heading hierarchy: tepat satu `<h1>`, diikuti `<h2>` dan `<h3>` secara hierarkis

- [x] 10.2 Verifikasi SEO tags di `jadwal.html` (sudah dibuat di Task 7.1):
    - Pastikan `<title>` mengandung kata kunci "Jadwal Tryout CPNS"
    - Pastikan canonical URL benar: `https://kuydinas.id/jadwal.html`
    - Pastikan tepat satu `<h1>` di halaman

- [x] 10.3 Verifikasi SEO tags di `statistik.html` (sudah dibuat di Task 8.1):
    - Pastikan `<title>` mengandung kata kunci "Statistik" atau "Platform Tryout CPNS"
    - Pastikan canonical URL benar: `https://kuydinas.id/statistik.html`
    - Pastikan tepat satu `<h1>` di halaman

## Task 11: Testing

- [-] 11.1 Tulis PHPUnit test untuk `PublicTryoutController`:
    - Test HTTP 200 tanpa token
    - Test hanya tryout `status = 'publish'` yang muncul
    - Test tidak ada field sensitif dalam response
    - Test `status_label` untuk berbagai kombinasi tanggal

- [x] 11.2 Tulis PHPUnit test untuk `PublicStatsController`:
    - Test HTTP 200 tanpa token
    - Test struktur response (tiga field integer)
    - Test `total_completed <= total_registrations`

- [ ] 11.3 Test manual di browser untuk semua halaman:
    - Buka `index.html`: verifikasi teaser jadwal dan statistik muncul, navbar berfungsi
    - Buka `jadwal.html`: verifikasi semua tryout tampil, filter tab berfungsi, GA events ter-fire
    - Buka `statistik.html`: verifikasi counter live berfungsi, GA event `stats_viewed` ter-fire
    - Matikan API: verifikasi semua halaman tetap berfungsi dengan data statis
    - Verifikasi tampilan mobile di semua halaman (Chrome DevTools responsive mode, 375px)
    - Verifikasi hamburger menu berfungsi di mobile
    - Verifikasi internal links antar halaman berfungsi

- [ ] 11.4 Test SEO:
    - Validasi JSON-LD structured data di `jadwal.html` via Google Rich Results Test
    - Validasi Open Graph tags via Facebook Sharing Debugger atau og:debugger
    - Verifikasi canonical URL di setiap halaman
    - Verifikasi heading hierarchy (tidak ada skip level h1 → h3)
