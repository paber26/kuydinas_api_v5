<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TopupTransaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\MidtransSnapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Exceptions\HttpResponseException;

class PaymentController extends Controller
{
    public function __construct(
        protected MidtransSnapService $midtransSnapService
    ) {
    }

    public function midtransWebhook(Request $request)
    {
        $payload = $request->all();

        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signatureKey = (string) ($payload['signature_key'] ?? '');

        if (!$orderId || !$statusCode || !$grossAmount || !$signatureKey) {
            return response()->json([
                'status' => false,
                'message' => 'Payload webhook Midtrans tidak lengkap.',
            ], 422);
        }

        if (!$this->midtransSnapService->verifySignature($orderId, $statusCode, $grossAmount, $signatureKey)) {
            return response()->json([
                'status' => false,
                'message' => 'Signature Midtrans tidak valid.',
            ], 403);
        }

        DB::transaction(function () use ($payload, $orderId, $grossAmount) {
            $topup = TopupTransaction::where('order_id', $orderId)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $topup->gross_amount !== (int) $grossAmount) {
                throw new HttpResponseException(response()->json([
                    'status' => false,
                    'message' => 'Nominal webhook tidak sesuai dengan transaksi top up.',
                ], 422));
            }

            $transactionStatus = (string) ($payload['transaction_status'] ?? 'pending');
            $fraudStatus = (string) ($payload['fraud_status'] ?? '');
            $isSuccess = in_array($transactionStatus, ['settlement'], true)
                || ($transactionStatus === 'capture' && $fraudStatus !== 'challenge');

            $mappedStatus = match ($transactionStatus) {
                'settlement' => 'paid',
                'capture' => $fraudStatus === 'challenge' ? 'pending' : 'paid',
                'pending' => 'pending',
                'deny' => 'failed',
                'cancel' => 'cancelled',
                'expire' => 'expired',
                'failure' => 'failed',
                default => $topup->transaction_status,
            };

            $topup->update([
                'status' => $mappedStatus,
                'transaction_status' => $transactionStatus,
                'fraud_status' => $fraudStatus ?: $topup->fraud_status,
                'payment_type' => $payload['payment_type'] ?? $topup->payment_type,
                'raw_notification' => $payload,
                'paid_at' => $isSuccess ? ($topup->paid_at ?? now()) : $topup->paid_at,
                'expired_at' => $transactionStatus === 'expire' ? now() : $topup->expired_at,
            ]);

            if (!$isSuccess) {
                return;
            }

            $alreadyCredited = WalletTransaction::where('reference_type', 'topup_transaction')
                ->where('reference_id', $topup->id)
                ->lockForUpdate()
                ->exists();

            if ($alreadyCredited) {
                return;
            }

            $user = User::whereKey($topup->user_id)
                ->lockForUpdate()
                ->firstOrFail();

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
        });

        return response()->json([
            'status' => true,
            'message' => 'Webhook Midtrans berhasil diproses.',
            'data' => [
                'order_id' => $orderId,
                'transaction_status' => (string) ($payload['transaction_status'] ?? 'pending'),
            ],
        ]);
    }
}
