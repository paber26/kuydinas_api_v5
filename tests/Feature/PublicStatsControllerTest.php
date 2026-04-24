<?php

namespace Tests\Feature;

use App\Models\TryoutRegistration;
use App\Models\Tryout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * **Validates: Requirements 2.1, 2.2, 2.3, 2.4**
 *
 * Test suite untuk PublicStatsController yang memverifikasi:
 * - Endpoint dapat diakses tanpa autentikasi
 * - Response memiliki tiga field integer non-negatif
 * - total_completed selalu <= total_registrations
 * - Tidak ada data PII dalam response
 */
class PublicStatsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * **Validates: Requirement 2.1**
     * Test bahwa endpoint mengembalikan HTTP 200 tanpa token autentikasi
     */
    public function test_returns_200_without_authentication(): void
    {
        $response = $this->getJson('/api/public/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data',
                'meta' => ['cached_at'],
            ]);
    }

    /**
     * **Validates: Requirement 2.2, Property 2.B**
     * Test bahwa response memiliki tiga field integer non-negatif
     */
    public function test_response_has_three_non_negative_integer_fields(): void
    {
        $response = $this->getJson('/api/public/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_registrations',
                    'total_completed',
                    'total_users',
                ],
            ]);

        $data = $response->json('data');

        // Verifikasi semua field adalah integer
        $this->assertIsInt($data['total_registrations']);
        $this->assertIsInt($data['total_completed']);
        $this->assertIsInt($data['total_users']);

        // Verifikasi semua field non-negatif (Property 2.B)
        $this->assertGreaterThanOrEqual(0, $data['total_registrations']);
        $this->assertGreaterThanOrEqual(0, $data['total_completed']);
        $this->assertGreaterThanOrEqual(0, $data['total_users']);
    }

    /**
     * **Validates: Requirement 2.3, Property 2.A**
     * Test bahwa total_completed selalu <= total_registrations
     */
    public function test_total_completed_is_less_than_or_equal_to_total_registrations(): void
    {
        $tryout = Tryout::create([
            'title' => 'Test Tryout',
            'duration' => 90,
            'status' => 'publish',
            'type' => 'free',
        ]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        // 2 registrasi completed
        TryoutRegistration::create([
            'user_id' => $user1->id,
            'tryout_id' => $tryout->id,
            'status' => 'completed',
            'registered_at' => now(),
        ]);

        TryoutRegistration::create([
            'user_id' => $user2->id,
            'tryout_id' => $tryout->id,
            'status' => 'completed',
            'registered_at' => now(),
        ]);

        // 1 registrasi belum selesai
        TryoutRegistration::create([
            'user_id' => $user3->id,
            'tryout_id' => $tryout->id,
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        $response = $this->getJson('/api/public/stats');

        $response->assertOk();

        $data = $response->json('data');

        // Property 2.A: total_completed <= total_registrations
        $this->assertLessThanOrEqual(
            $data['total_registrations'],
            $data['total_completed'],
            'total_completed should be <= total_registrations'
        );

        // Verifikasi nilai konkret
        $this->assertEquals(3, $data['total_registrations']);
        $this->assertEquals(2, $data['total_completed']);
    }

    /**
     * **Validates: Requirement 2.4, Property 2.C**
     * Test bahwa response tidak mengandung data PII
     */
    public function test_response_does_not_contain_pii(): void
    {
        User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $response = $this->getJson('/api/public/stats');

        $response->assertOk();

        $responseBody = $response->getContent();

        // Verifikasi tidak ada nama atau email dalam response
        $this->assertStringNotContainsString('John Doe', $responseBody);
        $this->assertStringNotContainsString('john@example.com', $responseBody);

        // Verifikasi tidak ada field PII dalam data
        $data = $response->json('data');
        $this->assertArrayNotHasKey('name', $data);
        $this->assertArrayNotHasKey('email', $data);
        $this->assertArrayNotHasKey('user_id', $data);
        $this->assertArrayNotHasKey('users', $data);
    }

    /**
     * Test bahwa stats menghitung dengan benar dari database kosong
     */
    public function test_stats_with_empty_database(): void
    {
        $response = $this->getJson('/api/public/stats');

        $response->assertOk()
            ->assertJsonPath('data.total_registrations', 0)
            ->assertJsonPath('data.total_completed', 0)
            ->assertJsonPath('data.total_users', 0);
    }

    /**
     * Test bahwa total_users menghitung semua user yang terdaftar
     */
    public function test_total_users_counts_all_registered_users(): void
    {
        User::factory()->count(5)->create();

        $response = $this->getJson('/api/public/stats');

        $response->assertOk()
            ->assertJsonPath('data.total_users', 5);
    }

    /**
     * Test bahwa total_registrations menghitung semua registrasi (semua status)
     */
    public function test_total_registrations_counts_all_statuses(): void
    {
        $tryout = Tryout::create([
            'title' => 'Test Tryout',
            'duration' => 90,
            'status' => 'publish',
            'type' => 'free',
        ]);

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
            'status' => 'in_progress',
            'registered_at' => now(),
        ]);

        TryoutRegistration::create([
            'user_id' => $user3->id,
            'tryout_id' => $tryout->id,
            'status' => 'completed',
            'registered_at' => now(),
        ]);

        $response = $this->getJson('/api/public/stats');

        $response->assertOk()
            ->assertJsonPath('data.total_registrations', 3)
            ->assertJsonPath('data.total_completed', 1);
    }

    /**
     * Test bahwa response di-cache dengan benar
     */
    public function test_response_is_cached(): void
    {
        User::factory()->count(3)->create();

        // Request pertama
        $response1 = $this->getJson('/api/public/stats');
        $cachedAt1 = $response1->json('meta.cached_at');
        $users1 = $response1->json('data.total_users');

        // Tambah user baru (tidak akan terlihat karena cache)
        User::factory()->count(2)->create();

        // Request kedua (masih dari cache)
        $response2 = $this->getJson('/api/public/stats');
        $cachedAt2 = $response2->json('meta.cached_at');
        $users2 = $response2->json('data.total_users');

        // Verifikasi cached_at sama
        $this->assertEquals($cachedAt1, $cachedAt2);

        // Verifikasi data masih dari cache (3 user, bukan 5)
        $this->assertEquals($users1, $users2);
        $this->assertEquals(3, $users2);
    }

    /**
     * **Validates: Property 2.A**
     * Test property: total_completed <= total_registrations selalu berlaku
     * bahkan ketika semua registrasi berstatus completed
     */
    public function test_property_2a_holds_when_all_registrations_are_completed(): void
    {
        $tryout = Tryout::create([
            'title' => 'Test Tryout',
            'duration' => 90,
            'status' => 'publish',
            'type' => 'free',
        ]);

        $users = User::factory()->count(4)->create();

        foreach ($users as $user) {
            TryoutRegistration::create([
                'user_id' => $user->id,
                'tryout_id' => $tryout->id,
                'status' => 'completed',
                'registered_at' => now(),
            ]);
        }

        $response = $this->getJson('/api/public/stats');

        $response->assertOk();

        $data = $response->json('data');

        // Ketika semua completed, total_completed == total_registrations
        $this->assertEquals($data['total_registrations'], $data['total_completed']);
        $this->assertLessThanOrEqual($data['total_registrations'], $data['total_completed']);
    }
}
