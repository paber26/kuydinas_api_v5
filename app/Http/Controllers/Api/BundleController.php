<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Models\BundleTransaction;
use App\Models\TryoutRegistration;
use App\Services\MidtransSnapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class BundleController extends Controller
{
    public function __construct(
        protected MidtransSnapService $midtransSnapService
    ) {
    }

    /**
     * Daftar bundle aktif yang bisa dibeli user.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $bundles = Bundle::with('tryouts')
            ->where('is_active', true)
            ->withCount(['transactions as paid_count' => function ($q) {
                $q->where('status', 'paid');
            }])
            ->latest()
            ->get()
            ->filter(fn ($bundle) => $bundle->isAvailable())
            ->values()
            ->map(function ($bundle) use ($user) {
                $alreadyPurchased = BundleTransaction::where('user_id', $user->id)
                    ->where('bundle_id', $bundle->id)
                    ->where('status', 'paid')
                    ->exists();

                return [
                    'id' => $bundle->id,
                    'name' => $bundle->name,
                    'description' => $bundle->description,
                    'price' => (int) $bundle->price,
                    'cover_image' => $bundle->cover_image,
                    'limit_type' => $bundle->limit_type,
                    'limit_quota' => $bundle->limit_quota,
                    'limit_start_date' => optional($bundle->limit_start_date)->format('Y-m-d'),
                    'limit_end_date' => optional($bundle->limit_end_date)->format('Y-m-d'),
                    'paid_count' => $bundle->paid_count,
                    'remaining_quota' => $bundle->limit_type === 'quota' && $bundle->limit_quota
                        ? max(0, $bundle->limit_quota - $bundle->paid_count)
                        : null,
                    'already_purchased' => $alreadyPurchased,
                    'tryouts_count' => $bundle->tryouts->count(),
                    'tryouts' => $bundle->tryouts->map(fn ($t) => [
                        'id' => $t->id,
                        'title' => $t->title,
                        'duration' => (int) $t->duration,
                        'type' => $t->type,
                    ]),
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $bundles,
        ]);
    }

    /**
     * Detail bundle.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $bundle = Bundle::with('tryouts')
            ->where('is_active', true)
            ->findOrFail($id);

        $alreadyPurchased = BundleTransaction::where('user_id', $user->id)
            ->where('bundle_id', $bundle->id)
            ->where('status', 'paid')
            ->exists();

        // Cek tryout mana saja yang sudah terdaftar oleh user
        $registeredTryoutIds = TryoutRegistration::where('user_id', $user->id)
            ->whereIn('tryout_id', $bundle->tryouts->pluck('id'))
            ->pluck('tryout_id')
            ->toArray();

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $bundle->id,
                'name' => $bundle->name,
                'description' => $bundle->description,
                'price' => (int) $bundle->price,
                'cover_image' => $bundle->cover_image,
                'limit_type' => $bundle->limit_type,
                'limit_quota' => $bundle->limit_quota,
                'limit_start_date' => optional($bundle->limit_start_date)->format('Y-m-d'),
                'limit_end_date' => optional($bundle->limit_end_date)->format('Y-m-d'),
                'is_available' => $bundle->isAvailable(),
                'already_purchased' => $alreadyPurchased,
                'remaining_quota' => $bundle->limit_type === 'quota' && $bundle->limit_quota
                    ? max(0, $bundle->limit_quota - $bundle->purchasedCount())
                    : null,
                'tryouts' => $bundle->tryouts->map(fn ($t) => [
                    'id' => $t->id,
                    'title' => $t->title,
                    'duration' => (int) $t->duration,
                    'type' => $t->type,
                    'question_count' => (int) ($t->twk_target ?? 0)
                        + (int) ($t->tiu_target ?? 0)
                        + (int) ($t->tkp_target ?? 0),
                    'already_registered' => in_array($t->id, $registeredTryoutIds),
                ]),
            ],
        ]);
    }

    /**
     * Buat transaksi pembelian bundle via Midtrans.
     */
    public function purchase(Request $request, $id)
    {
        $user = $request->user();

        $bundle = Bundle::with('tryouts')->findOrFail($id);

        if (!$bundle->isAvailable()) {
            return response()->json([
                'status' => false,
                'message' => 'Bundle tidak tersedia untuk dibeli saat ini.',
            ], 422);
        }

        // Cek apakah sudah pernah beli & paid
        $alreadyPurchased = BundleTransaction::where('user_id', $user->id)
            ->where('bundle_id', $bundle->id)
            ->where('status', 'paid')
            ->exists();

        if ($alreadyPurchased) {
            return response()->json([
                'status' => false,
                'message' => 'Kamu sudah pernah membeli bundle ini.',
            ], 422);
        }

        // Cek apakah semua tryout sudah terdaftar
        $bundleTryoutIds = $bundle->tryouts->pluck('id')->toArray();
        $registeredCount = TryoutRegistration::where('user_id', $user->id)
            ->whereIn('tryout_id', $bundleTryoutIds)
            ->count();

        if ($registeredCount >= count($bundleTryoutIds)) {
            return response()->json([
                'status' => false,
                'message' => 'Semua tryout di bundle ini sudah terdaftar.',
            ], 422);
        }

        // Buat transaksi
        $transaction = BundleTransaction::create([
            'user_id' => $user->id,
            'bundle_id' => $bundle->id,
            'order_id' => 'BUNDLE-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(8)),
            'gateway' => 'midtrans',
            'gross_amount' => (int) $bundle->price,
            'status' => 'pending',
            'transaction_status' => 'pending',
        ]);

        try {
            $midtransResponse = $this->midtransSnapService->createTransaction([
                'transaction_details' => [
                    'order_id' => $transaction->order_id,
                    'gross_amount' => (int) $transaction->gross_amount,
                ],
                'customer_details' => [
                    'first_name' => $user->name,
                    'email' => $user->email,
                ],
                'item_details' => [
                    [
                        'id' => 'BUNDLE-' . $bundle->id,
                        'price' => (int) $transaction->gross_amount,
                        'quantity' => 1,
                        'name' => 'Bundle: ' . Str::limit($bundle->name, 40),
                    ],
                ],
                'credit_card' => [
                    'secure' => (bool) config('midtrans.is_3ds', true),
                ],
                'callbacks' => [
                    'finish' => config('wallet.topup_finish_url'),
                ],
            ]);
        } catch (Throwable $e) {
            $transaction->update([
                'status' => 'failed',
                'transaction_status' => 'failed',
                'raw_response' => ['error' => $e->getMessage()],
            ]);

            Log::error('Bundle Midtrans createTransaction failed.', [
                'bundle_id' => $bundle->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Gagal membuat transaksi pembayaran.',
            ], 422);
        }

        $transaction->update([
            'snap_token' => $midtransResponse['token'] ?? null,
            'redirect_url' => $midtransResponse['redirect_url'] ?? null,
            'raw_response' => $midtransResponse,
        ]);

        if (!$transaction->snap_token) {
            $transaction->update([
                'status' => 'failed',
                'transaction_status' => 'failed',
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Snap token Midtrans tidak tersedia.',
            ], 422);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'snap_token' => $transaction->snap_token,
                'order_id' => $transaction->order_id,
                'transaction_id' => $transaction->id,
                'gross_amount' => (int) $transaction->gross_amount,
                'redirect_url' => $transaction->redirect_url,
            ],
        ]);
    }

    /**
     * Polling status pembayaran bundle dari Midtrans.
     */
    public function syncPayment(Request $request, $id)
    {
        $user = $request->user();

        try {
            return DB::transaction(function () use ($user, $id) {
                $transaction = BundleTransaction::where('user_id', $user->id)
                    ->where('bundle_id', $id)
                    ->latest()
                    ->lockForUpdate()
                    ->first();

                if (!$transaction) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Transaksi tidak ditemukan.',
                    ], 404);
                }

                if ($transaction->status === 'paid') {
                    return response()->json([
                        'status' => true,
                        'message' => 'Transaksi sudah dibayar.',
                        'data' => [
                            'status' => 'paid',
                            'transaction_status' => $transaction->transaction_status,
                        ],
                    ]);
                }

                $statusResponse = $this->midtransSnapService->getTransactionStatus($transaction->order_id);
                $transactionStatus = (string) ($statusResponse['transaction_status'] ?? 'pending');
                $fraudStatus = (string) ($statusResponse['fraud_status'] ?? '');
                $paymentType = (string) ($statusResponse['payment_type'] ?? '');

                $isSuccess = in_array($transactionStatus, ['settlement', 'capture'], true) && $fraudStatus !== 'challenge';

                $mappedStatus = match ($transactionStatus) {
                    'settlement' => 'paid',
                    'capture' => $fraudStatus === 'challenge' ? 'pending' : 'paid',
                    'pending' => 'pending',
                    'deny', 'failure' => 'failed',
                    'cancel' => 'cancelled',
                    'expire' => 'expired',
                    default => $transaction->status,
                };

                $transaction->update([
                    'status' => $mappedStatus,
                    'transaction_status' => $transactionStatus,
                    'fraud_status' => $fraudStatus ?: $transaction->fraud_status,
                    'payment_type' => $paymentType ?: $transaction->payment_type,
                    'paid_at' => $isSuccess ? ($transaction->paid_at ?? now()) : $transaction->paid_at,
                    'expired_at' => $transactionStatus === 'expire' ? now() : $transaction->expired_at,
                ]);

                if ($isSuccess) {
                    $this->registerBundleTryouts($transaction);
                }

                return response()->json([
                    'status' => true,
                    'message' => $isSuccess
                        ? 'Pembayaran berhasil! Tryout sudah terdaftar.'
                        : 'Status pembayaran diperbarui.',
                    'data' => [
                        'status' => $mappedStatus,
                        'transaction_status' => $transactionStatus,
                        'registered' => $isSuccess,
                    ],
                ]);
            });
        } catch (Throwable $e) {
            Log::error('Bundle sync payment failed: ' . $e->getMessage(), [
                'bundle_id' => $id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Gagal sinkronisasi status pembayaran.',
            ], 500);
        }
    }

    /**
     * Register semua tryout di bundle untuk user.
     */
    public static function registerBundleTryouts(BundleTransaction $transaction): void
    {
        $bundle = $transaction->bundle()->with('tryouts')->first();

        if (!$bundle) {
            return;
        }

        foreach ($bundle->tryouts as $tryout) {
            $alreadyRegistered = TryoutRegistration::where('user_id', $transaction->user_id)
                ->where('tryout_id', $tryout->id)
                ->exists();

            if (!$alreadyRegistered) {
                TryoutRegistration::create([
                    'user_id' => $transaction->user_id,
                    'tryout_id' => $tryout->id,
                    'status' => 'registered',
                    'registered_at' => now(),
                    // expires_at sengaja tidak diisi — bebas dikerjakan kapan saja
                ]);

                Log::info('Bundle tryout registered.', [
                    'user_id' => $transaction->user_id,
                    'tryout_id' => $tryout->id,
                    'bundle_id' => $bundle->id,
                    'order_id' => $transaction->order_id,
                ]);
            }
        }
    }
}
