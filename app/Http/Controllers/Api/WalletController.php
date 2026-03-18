<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TopupPackage;
use App\Models\TopupTransaction;
use App\Models\Tryout;
use App\Models\TryoutRegistration;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\MidtransSnapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class WalletController extends Controller
{
    public function __construct(
        protected MidtransSnapService $midtransSnapService
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user()->fresh();

        $walletTransactions = WalletTransaction::where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'source' => 'wallet_transaction',
                    'type' => $transaction->type,
                    'status' => 'completed',
                    'amount' => $transaction->amount,
                    'coin_amount' => (int) ($transaction->coin_amount ?? 0),
                    'balance_before' => (int) ($transaction->balance_before ?? 0),
                    'balance_after' => (int) ($transaction->balance_after ?? 0),
                    'order_id' => null,
                    'reference_type' => $transaction->reference_type,
                    'reference_id' => $transaction->reference_id,
                    'description' => $transaction->description,
                    'created_at' => optional($transaction->created_at)->toDateTimeString(),
                ];
            });

        $topupTransactions = TopupTransaction::where('user_id', $user->id)
            ->with('topupPackage')
            ->latest()
            ->get()
            ->map(function ($topup) {
                return [
                    'id' => $topup->id,
                    'source' => 'topup_transaction',
                    'type' => 'topup',
                    'status' => $topup->status,
                    'amount' => (int) $topup->gross_amount,
                    'coin_amount' => (int) $topup->coin_amount + (int) $topup->bonus_coin,
                    'balance_before' => null,
                    'balance_after' => null,
                    'order_id' => $topup->order_id,
                    'reference_type' => 'topup_package',
                    'reference_id' => $topup->topup_package_id,
                    'description' => 'Top up package ' . ($topup->topupPackage?->name ?? $topup->topup_package_id),
                    'created_at' => optional($topup->created_at)->toDateTimeString(),
                ];
            });

        $transactions = $walletTransactions
            ->concat($topupTransactions)
            ->sortByDesc(fn ($transaction) => $transaction['created_at'] ?? '')
            ->values();

        return response()->json([
            'status' => true,
            'data' => [
                'balance' => (int) ($user->coin_balance ?? 0),
                'transactions' => $transactions,
            ],
        ]);
    }

    public function topupPackages()
    {
        $packages = TopupPackage::where('is_active', true)
            ->orderBy('price')
            ->get()
            ->map(function ($package) {
                return [
                    'id' => $package->id,
                    'name' => $package->name,
                    'coin_amount' => (int) $package->coin_amount,
                    'bonus_coin' => (int) $package->bonus_coin,
                    'price' => (int) $package->price,
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $packages,
        ]);
    }

    public function createTopup(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'package_id' => 'required|integer',
        ]);

        $package = TopupPackage::where('id', (int) $validated['package_id'])
            ->where('is_active', true)
            ->first();

        if (!$package) {
            return response()->json([
                'status' => false,
                'message' => 'Paket top-up tidak ditemukan.',
            ], 422);
        }

        $topup = TopupTransaction::create([
            'user_id' => $user->id,
            'order_id' => 'TOPUP-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(8)),
            'topup_package_id' => $package->id,
            'gateway' => 'midtrans',
            'gross_amount' => (int) $package->price,
            'coin_amount' => (int) $package->coin_amount,
            'bonus_coin' => (int) $package->bonus_coin,
            'status' => 'pending',
            'transaction_status' => 'pending',
        ]);

        try {
            $midtransResponse = $this->midtransSnapService->createTransaction([
                'transaction_details' => [
                    'order_id' => $topup->order_id,
                    'gross_amount' => (int) $topup->gross_amount,
                ],
                'customer_details' => [
                    'first_name' => $user->name,
                    'email' => $user->email,
                ],
                'item_details' => [
                    [
                        'id' => 'TOPUP-' . $topup->topup_package_id,
                        'price' => (int) $topup->gross_amount,
                        'quantity' => 1,
                        'name' => $package->name,
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
            $topup->update([
                'status' => 'failed',
                'transaction_status' => 'failed',
                'raw_response' => [
                    'error' => $e->getMessage(),
                ],
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Gagal membuat transaksi Midtrans.',
            ], 422);
        }

        $topup->update([
            'snap_token' => $midtransResponse['token'] ?? null,
            'redirect_url' => $midtransResponse['redirect_url'] ?? null,
            'raw_response' => $midtransResponse,
        ]);

        if (!$topup->snap_token) {
            $topup->update([
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
                'snap_token' => $topup->snap_token,
                'order_id' => $topup->order_id,
                'transaction_id' => $topup->id,
                'gross_amount' => (int) $topup->gross_amount,
                'redirect_url' => $topup->redirect_url,
            ],
        ]);
    }

    public function showTopup(Request $request, $id)
    {
        $topup = TopupTransaction::where('user_id', $request->user()->id)
            ->with('topupPackage')
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $topup->id,
                'order_id' => $topup->order_id,
                'package_id' => $topup->topup_package_id,
                'package_name' => $topup->topupPackage?->name,
                'coin_amount' => $topup->coin_amount,
                'bonus_coin' => (int) $topup->bonus_coin,
                'gross_amount' => $topup->gross_amount,
                'status' => $topup->status,
                'transaction_status' => $topup->transaction_status,
                'snap_token' => $topup->snap_token,
                'redirect_url' => $topup->redirect_url,
                'paid_at' => optional($topup->paid_at)->toDateTimeString(),
                'expired_at' => optional($topup->expired_at)->toDateTimeString(),
                'created_at' => optional($topup->created_at)->toDateTimeString(),
            ],
        ]);
    }

    public function syncTopup(Request $request, $id)
    {
        $topup = TopupTransaction::where('user_id', $request->user()->id)
            ->findOrFail($id);

        if ($topup->status === 'paid') {
            return response()->json([
                'status' => true,
                'message' => 'Transaksi sudah dibayar.',
                'data' => [
                    'status' => $topup->status,
                ],
            ]);
        }

        try {
            $statusResponse = $this->midtransSnapService->getTransactionStatus($topup->order_id);
            $transactionStatus = $statusResponse['transaction_status'] ?? 'pending';
            $fraudStatus = $statusResponse['fraud_status'] ?? '';

            $isSuccess = in_array($transactionStatus, ['settlement', 'capture']) && $fraudStatus !== 'challenge';

            if ($isSuccess) {
                DB::transaction(function () use ($topup, $transactionStatus, $fraudStatus) {
                    $topup->refresh();
                    if ($topup->status === 'paid') return;

                    $user = User::whereKey($topup->user_id)->lockForUpdate()->firstOrFail();
                    $balanceBefore = (int) ($user->coin_balance ?? 0);
                    $creditedCoin = (int) $topup->coin_amount + (int) $topup->bonus_coin;
                    $balanceAfter = $balanceBefore + $creditedCoin;

                    $user->update(['coin_balance' => $balanceAfter]);

                    $topup->update([
                        'status' => 'paid',
                        'transaction_status' => $transactionStatus,
                        'fraud_status' => $fraudStatus,
                        'paid_at' => now(),
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
                        'description' => 'Sync Manual: Top up ' . $creditedCoin . ' coin via Midtrans',
                    ]);
                });

                return response()->json([
                    'status' => true,
                    'message' => 'Status transaksi berhasil diperbarui.',
                    'data' => [
                        'status' => 'paid',
                    ],
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Transaksi belum dibayar atau status belum berubah.',
                'data' => [
                    'status' => $topup->status,
                    'midtrans_status' => $transactionStatus,
                ],
            ]);

        } catch (Throwable $e) {
            Log::error('Gagal sinkronisasi status Midtrans: ' . $e->getMessage(), [
                'order_id' => $topup->order_id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Gagal sinkronisasi status dari Midtrans.',
            ], 500);
        }
    }

    public function redeemableTryouts(Request $request)
    {
        $user = $request->user();

        $tryouts = Tryout::where('type', 'premium')
            ->where('status', 'publish')
            ->whereDoesntHave('registrations', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->latest()
            ->get()
            ->map(function ($tryout) {
                return [
                    'id' => $tryout->id,
                    'title' => $tryout->title,
                    'price' => (int) ($tryout->price ?? 0),
                    'duration' => (int) $tryout->duration,
                    'questionCount' =>
                        (int) ($tryout->twk_target ?? 0) +
                        (int) ($tryout->tiu_target ?? 0) +
                        (int) ($tryout->tkp_target ?? 0),
                    'type' => $tryout->type,
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $tryouts,
        ]);
    }

    public function redeemTryout(Request $request, $id)
    {
        $user = $request->user();

        $result = DB::transaction(function () use ($user, $id) {
            $lockedUser = User::whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $tryout = Tryout::lockForUpdate()->findOrFail($id);

            if ($tryout->type !== 'premium') {
                return response()->json([
                    'status' => false,
                    'message' => 'Tryout ini bukan tryout premium.',
                ], 422);
            }

            if ($tryout->status !== 'publish') {
                return response()->json([
                    'status' => false,
                    'message' => 'Tryout belum tersedia untuk ditukar.',
                ], 422);
            }

            $alreadyRegistered = TryoutRegistration::where('user_id', $lockedUser->id)
                ->where('tryout_id', $tryout->id)
                ->lockForUpdate()
                ->exists();

            if ($alreadyRegistered) {
                return response()->json([
                    'status' => false,
                    'message' => 'User sudah terdaftar pada tryout ini.',
                ], 422);
            }

            $price = (int) ($tryout->price ?? 0);
            $balanceBefore = (int) ($lockedUser->coin_balance ?? 0);

            if ($price <= 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Harga tryout tidak valid untuk redeem.',
                ], 422);
            }

            if ($balanceBefore < $price) {
                return response()->json([
                    'status' => false,
                    'message' => 'Saldo koin tidak mencukupi.',
                ], 422);
            }

            $balanceAfter = $balanceBefore - $price;

            $lockedUser->update([
                'coin_balance' => $balanceAfter,
            ]);

            WalletTransaction::create([
                'user_id' => $lockedUser->id,
                'type' => 'redeem_tryout',
                'amount' => $price,
                'coin_amount' => 0,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => 'tryout',
                'reference_id' => $tryout->id,
                'description' => 'Tukar koin untuk tryout ' . $tryout->title,
            ]);

            TryoutRegistration::create([
                'user_id' => $lockedUser->id,
                'tryout_id' => $tryout->id,
                'status' => 'registered',
                'registered_at' => now(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Tryout berhasil ditukar dengan koin',
                'data' => [
                    'tryout_id' => $tryout->id,
                    'remaining_balance' => $balanceAfter,
                    'registration_status' => 'registered',
                ],
            ]);
        });

        return $result;
    }
}
