<?php

namespace Tests\Feature;

use App\Models\TopupPackage;
use App\Models\TopupTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MidtransWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_settlement_webhook_marks_topup_paid_and_credits_wallet_once(): void
    {
        config()->set('midtrans.server_key', 'test-server-key');

        $user = User::factory()->create();
        $package = TopupPackage::create([
            'name' => 'Starter Pack',
            'coin_amount' => 100,
            'bonus_coin' => 0,
            'price' => 1000,
            'is_active' => true,
        ]);

        $topup = TopupTransaction::create([
            'user_id' => $user->id,
            'topup_package_id' => $package->id,
            'order_id' => 'TOPUP-20260318110933-ZT86TLMV',
            'gateway' => 'midtrans',
            'gross_amount' => 1000,
            'coin_amount' => 100,
            'bonus_coin' => 0,
            'status' => 'pending',
            'transaction_status' => 'pending',
        ]);

        $payload = $this->makeWebhookPayload($topup->order_id, '200', '1000.00', 'settlement');

        $response = $this->postJson('/api/payments/midtrans/webhook', $payload);

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.order_id', $topup->order_id)
            ->assertJsonPath('data.local_status', 'paid')
            ->assertJsonPath('data.credited', true);

        $topup->refresh();
        $user->refresh();

        $this->assertSame('paid', $topup->status);
        $this->assertSame('settlement', $topup->transaction_status);
        $this->assertSame(100, $user->coin_balance);
        $this->assertNotNull($topup->paid_at);
        $this->assertDatabaseCount('wallet_transactions', 1);

        $replayedResponse = $this->postJson('/api/payments/midtrans/webhook', $payload);

        $replayedResponse
            ->assertOk()
            ->assertJsonPath('data.local_status', 'paid')
            ->assertJsonPath('data.credited', false);

        $user->refresh();

        $this->assertSame(100, $user->coin_balance);
        $this->assertDatabaseCount('wallet_transactions', 1);

        Sanctum::actingAs($user);

        $walletResponse = $this->getJson('/api/wallet');

        $walletResponse
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.balance', 100);
    }

    public function test_pending_webhook_updates_local_status_without_crediting_wallet(): void
    {
        config()->set('midtrans.server_key', 'test-server-key');

        $user = User::factory()->create();
        $package = TopupPackage::create([
            'name' => 'Starter Pack',
            'coin_amount' => 100,
            'bonus_coin' => 0,
            'price' => 1000,
            'is_active' => true,
        ]);

        $topup = TopupTransaction::create([
            'user_id' => $user->id,
            'topup_package_id' => $package->id,
            'order_id' => 'TOPUP-PENDING-001',
            'gateway' => 'midtrans',
            'gross_amount' => 1000,
            'coin_amount' => 100,
            'bonus_coin' => 0,
            'status' => 'pending',
            'transaction_status' => 'pending',
        ]);

        $payload = $this->makeWebhookPayload($topup->order_id, '201', '1000.00', 'pending');

        $this->postJson('/api/payments/midtrans/webhook', $payload)
            ->assertOk()
            ->assertJsonPath('data.local_status', 'pending')
            ->assertJsonPath('data.credited', false);

        $topup->refresh();
        $user->refresh();

        $this->assertSame('pending', $topup->status);
        $this->assertSame('pending', $topup->transaction_status);
        $this->assertSame(0, $user->coin_balance);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_paid_topup_is_not_downgraded_by_late_expire_webhook(): void
    {
        config()->set('midtrans.server_key', 'test-server-key');

        $user = User::factory()->create(['coin_balance' => 100]);
        $package = TopupPackage::create([
            'name' => 'Starter Pack',
            'coin_amount' => 100,
            'bonus_coin' => 0,
            'price' => 1000,
            'is_active' => true,
        ]);

        $topup = TopupTransaction::create([
            'user_id' => $user->id,
            'topup_package_id' => $package->id,
            'order_id' => 'TOPUP-PAID-001',
            'gateway' => 'midtrans',
            'gross_amount' => 1000,
            'coin_amount' => 100,
            'bonus_coin' => 0,
            'status' => 'paid',
            'transaction_status' => 'settlement',
            'paid_at' => now(),
        ]);

        \App\Models\WalletTransaction::create([
            'user_id' => $user->id,
            'type' => 'topup',
            'amount' => 1000,
            'coin_amount' => 100,
            'balance_before' => 0,
            'balance_after' => 100,
            'reference_type' => 'topup_transaction',
            'reference_id' => $topup->id,
            'description' => 'Top up 100 coin via Midtrans',
        ]);

        $expirePayload = $this->makeWebhookPayload($topup->order_id, '407', '1000.00', 'expire');

        $this->postJson('/api/payments/midtrans/webhook', $expirePayload)
            ->assertOk()
            ->assertJsonPath('data.local_status', 'paid')
            ->assertJsonPath('data.credited', false);

        $topup->refresh();
        $user->refresh();

        $this->assertSame('paid', $topup->status);
        $this->assertSame('expire', $topup->transaction_status);
        $this->assertSame(100, $user->coin_balance);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    private function makeWebhookPayload(
        string $orderId,
        string $statusCode,
        string $grossAmount,
        string $transactionStatus,
        array $overrides = []
    ): array {
        $payload = array_merge([
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'transaction_status' => $transactionStatus,
            'payment_type' => 'bank_transfer',
        ], $overrides);

        $payload['signature_key'] = hash(
            'sha512',
            $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . config('midtrans.server_key')
        );

        return $payload;
    }
}
