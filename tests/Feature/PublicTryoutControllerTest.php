<?php

namespace Tests\Feature;

use App\Models\Tryout;
use App\Models\TryoutRegistration;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * **Validates: Requirements 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8**
 *
 * Test suite untuk PublicTryoutController yang memverifikasi:
 * - Endpoint dapat diakses tanpa autentikasi
 * - Hanya tryout dengan status 'publish' yang muncul
 * - Field sensitif tidak terekspos dalam response
 * - status_label dihitung dengan benar berdasarkan tanggal
 * - Tryout yang sudah berakhir >7 hari tidak muncul
 */
class PublicTryoutControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * **Validates: Requirement 1.1**
     * Test bahwa endpoint mengembalikan HTTP 200 tanpa token autentikasi
     */
    public function test_returns_200_without_authentication(): void
    {
        $response = $this->getJson('/api/public/tryouts');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data',
                'meta' => ['cached_at', 'total'],
            ]);
    }

    /**
     * **Validates: Requirement 1.2**
     * Test bahwa hanya tryout dengan status 'publish' yang muncul dalam response
     */
    public function test_only_published_tryouts_are_returned(): void
    {
        // Buat tryout dengan status publish
        $publishedTryout = Tryout::create([
            'title' => 'Tryout Published',
            'duration' => 90,
            'status' => 'publish',
            'type' => 'free',
        ]);

        // Buat tryout dengan status draft
        $draftTryout = Tryout::create([
            'title' => 'Tryout Draft',
            'duration' => 90,
            'status' => 'draft',
            'type' => 'free',
        ]);

        $response = $this->getJson('/api/public/tryouts');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Tryout Published');

        // Verifikasi tryout draft tidak ada dalam response
        $data = $response->json('data');
        $titles = array_column($data, 'title');
        $this->assertNotContains('Tryout Draft', $titles);
    }

    /**
     * **Validates: Requirement 1.3, Property 1.B**
     * Test bahwa field sensitif tidak ada dalam response
     */
    public function test_sensitive_fields_are_not_exposed(): void
    {
        Tryout::create([
            'title' => 'Tryout Test',
            'duration' => 90,
            'status' => 'publish',
            'type' => 'premium',
            'price' => 50000,
            'discount' => 10000,
            'info_ig' => '@kuydinas',
            'info_wa' => '081234567890',
        ]);

        $response = $this->getJson('/api/public/tryouts');

        $response->assertOk();

        $data = $response->json('data.0');

        // Verifikasi field sensitif tidak ada
        $this->assertArrayNotHasKey('price', $data);
        $this->assertArrayNotHasKey('discount', $data);
        $this->assertArrayNotHasKey('info_ig', $data);
        $this->assertArrayNotHasKey('info_wa', $data);

        // Verifikasi field yang seharusnya ada
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('type', $data);
        $this->assertArrayHasKey('duration', $data);
        $this->assertArrayHasKey('status_label', $data);
        $this->assertArrayHasKey('quota', $data);
        $this->assertArrayHasKey('registrations_count', $data);
    }

    /**
     * **Validates: Requirement 1.4, Property 1.C**
     * Test bahwa tryout free dengan free_start_date di masa depan memiliki status_label "upcoming"
     */
    public function test_free_tryout_with_future_start_date_has_upcoming_status(): void
    {
        $futureDate = Carbon::now()->addDays(5);

        Tryout::create([
            'title' => 'Tryout Upcoming',
            'duration' => 90,
            'status' => 'publish',
            'type' => 'free',
            'free_start_date' => $futureDate,
        ]);

        $response = $this->getJson('/api/public/tryouts');

        $response->assertOk()
            ->assertJsonPath('data.0.status_label', 'upcoming');
    }

    /**
     * **Validates: Requirement 1.5, Property 1.D**
     * Test bahwa tryout free dengan free_valid_until di masa lalu (>7 hari) tidak muncul
     */
    public function test_free_tryout_expired_more_than_7_days_is_not_returned(): void
    {
        $expiredDate = Carbon::now()->subDays(10);

        Tryout::create([
            'title' => 'Tryout Expired',
            'duration' => 90,
            'status' => 'publish',
            'type' => 'free',
            'free_valid_until' => $expiredDate,
        ]);

        $response = $this->getJson('/api/public/tryouts');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * **Validates: Requirement 1.5**
     * Test bahwa tryout yang baru berakhir (<7 hari) masih muncul dengan status "ended"
     */
    public function test_free_tryout_expired_within_7_days_is_returned_with_ended_status(): void
    {
        $recentlyExpired = Carbon::now()->subDays(3);

        Tryout::create([
            'title' => 'Tryout Recently Ended',
            'duration' => 90,
            'status' => 'publish',
            'type' => 'free',
            'free_valid_until' => $recentlyExpired,
        ]);

        $response = $this->getJson('/api/public/tryouts');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status_label', 'ended');
    }

    /**
     * **Validates: Requirement 1.6**
     * Test bahwa tryout free tanpa tanggal memiliki status_label "active"
     */
    public function test_free_tryout_without_dates_has_active_status(): void
    {
        Tryout::create([
            'title' => 'Tryout Always Active',
            'duration' => 90,
            'status' => 'publish',
            'type' => 'free',
            'free_start_date' => null,
            'free_valid_until' => null,
        ]);

        $response = $this->getJson('/api/public/tryouts');

        $response->assertOk()
            ->assertJsonPath('data.0.status_label', 'active');
    }

    /**
     * **Validates: Requirement 1.7**
     * Test bahwa tryout premium selalu memiliki status_label "active"
     */
    public function test_premium_tryout_always_has_active_status(): void
    {
        Tryout::create([
            'title' => 'Tryout Premium',
            'duration' => 90,
            'status' => 'publish',
            'type' => 'premium',
        ]);

        $response = $this->getJson('/api/public/tryouts');

        $response->assertOk()
            ->assertJsonPath('data.0.status_label', 'active');
    }

    /**
     * **Validates: Requirement 1.8**
     * Test bahwa response memiliki semua field yang diperlukan
     */
    public function test_response_has_required_fields(): void
    {
        Tryout::create([
            'title' => 'Tryout Complete',
            'duration' => 90,
            'status' => 'publish',
            'type' => 'free',
            'quota' => 100,
            'free_start_date' => Carbon::now()->subDays(1),
            'free_valid_until' => Carbon::now()->addDays(7),
        ]);

        $response = $this->getJson('/api/public/tryouts');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'type',
                        'duration',
                        'free_start_date',
                        'free_valid_until',
                        'status_label',
                        'quota',
                        'registrations_count',
                    ],
                ],
            ]);
    }

    /**
     * **Validates: Property 1.A**
     * Test bahwa semua status_label dalam response adalah nilai yang valid
     */
    public function test_all_status_labels_are_valid(): void
    {
        // Buat berbagai tryout dengan status berbeda
        Tryout::create([
            'title' => 'Upcoming',
            'duration' => 90,
            'status' => 'publish',
            'type' => 'free',
            'free_start_date' => Carbon::now()->addDays(5),
        ]);

        Tryout::create([
            'title' => 'Active',
            'duration' => 90,
            'status' => 'publish',
            'type' => 'free',
        ]);

        Tryout::create([
            'title' => 'Recently Ended',
            'duration' => 90,
            'status' => 'publish',
            'type' => 'free',
            'free_valid_until' => Carbon::now()->subDays(2),
        ]);

        $response = $this->getJson('/api/public/tryouts');

        $response->assertOk();

        $data = $response->json('data');
        $validStatuses = ['upcoming', 'active', 'ended'];

        foreach ($data as $tryout) {
            $this->assertContains(
                $tryout['status_label'],
                $validStatuses,
                "status_label '{$tryout['status_label']}' is not valid"
            );
        }
    }

    /**
     * Test bahwa registrations_count dihitung dengan benar
     */
    public function test_registrations_count_is_accurate(): void
    {
        $tryout = Tryout::create([
            'title' => 'Tryout with Registrations',
            'duration' => 90,
            'status' => 'publish',
            'type' => 'free',
        ]);

        // Buat 3 registrasi
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        TryoutRegistration::create([
            'user_id' => $user1->id,
            'tryout_id' => $tryout->id,
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        TryoutRegistration::create([
            'user_id' => $user2->id,
            'tryout_id' => $tryout->id,
            'status' => 'completed',
            'registered_at' => now(),
        ]);

        TryoutRegistration::create([
            'user_id' => $user3->id,
            'tryout_id' => $tryout->id,
            'status' => 'in_progress',
            'registered_at' => now(),
        ]);

        $response = $this->getJson('/api/public/tryouts');

        $response->assertOk()
            ->assertJsonPath('data.0.registrations_count', 3);
    }

    /**
     * Test bahwa response di-cache dengan benar
     */
    public function test_response_is_cached(): void
    {
        Tryout::create([
            'title' => 'Tryout Cached',
            'duration' => 90,
            'status' => 'publish',
            'type' => 'free',
        ]);

        // Request pertama
        $response1 = $this->getJson('/api/public/tryouts');
        $cachedAt1 = $response1->json('meta.cached_at');

        // Buat tryout baru (tidak akan muncul karena cache)
        Tryout::create([
            'title' => 'Tryout New',
            'duration' => 90,
            'status' => 'publish',
            'type' => 'free',
        ]);

        // Request kedua (masih dari cache)
        $response2 = $this->getJson('/api/public/tryouts');
        $cachedAt2 = $response2->json('meta.cached_at');

        // Verifikasi cached_at sama (masih dari cache)
        $this->assertEquals($cachedAt1, $cachedAt2);

        // Verifikasi hanya 1 tryout (yang pertama)
        $response2->assertJsonCount(1, 'data');
    }

    /**
     * Test kombinasi kompleks: tryout dengan free_start_date dan free_valid_until
     */
    public function test_complex_date_combinations(): void
    {
        // Tryout yang sudah mulai tapi belum berakhir
        Tryout::create([
            'title' => 'Active with dates',
            'duration' => 90,
            'status' => 'publish',
            'type' => 'free',
            'free_start_date' => Carbon::now()->subDays(2),
            'free_valid_until' => Carbon::now()->addDays(5),
        ]);

        // Tryout yang belum mulai
        Tryout::create([
            'title' => 'Not started yet',
            'duration' => 90,
            'status' => 'publish',
            'type' => 'free',
            'free_start_date' => Carbon::now()->addDays(3),
            'free_valid_until' => Carbon::now()->addDays(10),
        ]);

        $response = $this->getJson('/api/public/tryouts');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $data = $response->json('data');

        // Verifikasi status_label
        $statusByTitle = [];
        foreach ($data as $tryout) {
            $statusByTitle[$tryout['title']] = $tryout['status_label'];
        }

        $this->assertEquals('active', $statusByTitle['Active with dates']);
        $this->assertEquals('upcoming', $statusByTitle['Not started yet']);
    }
}
