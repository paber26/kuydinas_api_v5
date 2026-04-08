<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TopupTransaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\MidtransSnapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        protected MidtransSnapService $midtransSnapService
    ) {
    }

    public function midtransWebhook(Request $request)
    {
        $payload = $request->all();
        $transactionStatus = (string) ($payload['transaction_status'] ?? 'pending');
        $fraudStatus = (string) ($payload['fraud_status'] ?? '');

        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signatureKey = (string) ($payload['signature_key'] ?? '');

        Log::info('Midtrans webhook received.', [
            'order_id' => $orderId ?: null,
            'transaction_status' => $transactionStatus,
            'payment_type' => $payload['payment_type'] ?? null,
            'payload' => $payload,
        ]);

        if (!$orderId || !$statusCode || !$grossAmount || !$signatureKey) {
            Log::warning('Midtrans webhook rejected because payload is incomplete.', [
                'order_id' => $orderId ?: null,
                'status_code' => $statusCode ?: null,
                'gross_amount' => $grossAmount ?: null,
                'has_signature' => $signatureKey !== '',
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Payload webhook Midtrans tidak lengkap.',
            ], 200);
        }

        $isSignatureValid = $this->midtransSnapService->verifySignature($orderId, $statusCode, $grossAmount, $signatureKey);

        Log::info('Midtrans webhook signature verification completed.', [
            'order_id' => $orderId,
            'is_valid' => $isSignatureValid,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
        ]);

        if (!$isSignatureValid) {
            Log::warning('Midtrans webhook rejected because signature is invalid.', [
                'order_id' => $orderId,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Signature Midtrans tidak valid.',
            ], 200);
        }

        $result = DB::transaction(function () use ($payload, $orderId, $grossAmount, $transactionStatus, $fraudStatus) {
            Log::info('Processing Midtrans webhook order.', [
                'order_id' => $orderId,
                'transaction_status' => $transactionStatus,
            ]);

            $topup = TopupTransaction::where('order_id', $orderId)
                ->lockForUpdate()
                ->first();

            if (!$topup) {
                Log::warning('Midtrans webhook order_id was not found in topup transactions.', [
                    'order_id' => $orderId,
                ]);

                return [
                    'error' => true,
                    'message' => 'Transaksi top up tidak ditemukan.',
                ];
            }

            Log::info('Midtrans webhook matched topup transaction.', [
                'order_id' => $orderId,
                'topup_transaction_id' => $topup->id,
                'user_id' => $topup->user_id,
                'local_status' => $topup->status,
                'local_transaction_status' => $topup->transaction_status,
            ]);

            if ((int) $topup->gross_amount !== (int) $grossAmount) {
                Log::warning('Midtrans webhook amount mismatch.', [
                    'order_id' => $orderId,
                    'topup_transaction_id' => $topup->id,
                    'expected_gross_amount' => (int) $topup->gross_amount,
                    'received_gross_amount' => $grossAmount,
                ]);

                return [
                    'error' => true,
                    'message' => 'Nominal webhook tidak sesuai.',
                ];
            }

            $isSuccess = $this->isSuccessfulPaymentStatus($transactionStatus, $fraudStatus);
            $mappedStatus = $this->mapLocalTopupStatus($transactionStatus, $fraudStatus, $topup->status);
            $alreadyPaid = $topup->status === 'paid';

            if ($alreadyPaid && !$isSuccess) {
                Log::warning('Midtrans webhook attempted to downgrade a paid topup transaction. Keeping paid status.', [
                    'order_id' => $orderId,
                    'topup_transaction_id' => $topup->id,
                    'incoming_transaction_status' => $transactionStatus,
                ]);

                $mappedStatus = $topup->status;
            }

            $topup->update([
                'status' => $mappedStatus,
                'transaction_status' => $transactionStatus,
                'fraud_status' => $fraudStatus ?: $topup->fraud_status,
                'payment_type' => $payload['payment_type'] ?? $topup->payment_type,
                'raw_notification' => json_encode($payload),
                'paid_at' => $isSuccess ? ($topup->paid_at ?? now()) : $topup->paid_at,
                'expired_at' => $transactionStatus === 'expire' ? now() : $topup->expired_at,
            ]);

            if (!$isSuccess) {
                Log::info('Midtrans webhook did not credit wallet because payment is not successful.', [
                    'order_id' => $orderId,
                    'topup_transaction_id' => $topup->id,
                    'local_status' => $topup->status,
                ]);

                return [
                    'credited' => false,
                    'topup_transaction_id' => $topup->id,
                    'local_status' => $topup->status,
                ];
            }

            $alreadyCredited = WalletTransaction::where('reference_type', 'topup_transaction')
                ->where('reference_id', $topup->id)
                ->lockForUpdate()
                ->exists();

            if ($alreadyCredited) {
                Log::info('Midtrans webhook skipped wallet credit because topup was already credited.', [
                    'order_id' => $orderId,
                    'topup_transaction_id' => $topup->id,
                ]);

                return [
                    'credited' => false,
                    'topup_transaction_id' => $topup->id,
                    'local_status' => $topup->status,
                ];
            }

            $user = User::whereKey($topup->user_id)
                ->lockForUpdate()
                ->first();

            if (!$user) {
                return [
                    'error' => true,
                    'message' => 'User tidak ditemukan.',
                ];
            }

            $balanceBefore = (int) ($user->coin_balance ?? 0);
            $creditedCoin = (int) $topup->coin_amount + (int) $topup->bonus_coin;
            $balanceAfter = $balanceBefore + $creditedCoin;

            $user->update([
                'coin_balance' => $balanceAfter,
            ]);

            WalletTransaction::create([
                'user_id' => $user->id,
                'type' => 'topup',
                'amount' => (int) $topup->gross_amount,
                'coin_amount' => $creditedCoin,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => 'topup_transaction',
                'reference_id' => $topup->id,
                'description' => 'Top up ' . $creditedCoin . ' coin via Midtrans',
            ]);

            Log::info('Midtrans webhook credited wallet successfully.', [
                'order_id' => $orderId,
                'topup_transaction_id' => $topup->id,
                'user_id' => $user->id,
                'credited_coin' => $creditedCoin,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
            ]);

            return [
                'credited' => true,
                'topup_transaction_id' => $topup->id,
                'local_status' => $topup->status,
                'balance_after' => $balanceAfter,
            ];
        });

        if (isset($result['error']) && $result['error']) {
            return response()->json([
                'status' => false,
                'message' => $result['message'],
            ], 200);
        }

        return response()->json([
            'status' => true,
            'message' => 'Webhook Midtrans berhasil diproses.',
            'data' => [
                'order_id' => $orderId,
                'transaction_status' => $transactionStatus,
                'local_status' => $result['local_status'] ?? null,
                'credited' => (bool) ($result['credited'] ?? false),
            ],
        ]);
    }

    private function isSuccessfulPaymentStatus(string $transactionStatus, string $fraudStatus): bool
    {
        return in_array($transactionStatus, ['settlement'], true)
            || ($transactionStatus === 'capture' && $fraudStatus !== 'challenge');
    }

    private function mapLocalTopupStatus(string $transactionStatus, string $fraudStatus, string $currentStatus): string
    {
        return match ($transactionStatus) {
            'settlement' => 'paid',
            'capture' => $fraudStatus === 'challenge' ? 'pending' : 'paid',
            'pending' => 'pending',
            'deny', 'failure' => 'failed',
            'cancel' => 'cancelled',
            'expire' => 'expired',
            default => $currentStatus,
        };
    }
}