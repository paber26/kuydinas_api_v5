<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SoalController;
use App\Http\Controllers\Api\TryoutController;
use App\Http\Controllers\Api\TryoutRegistrationController;
use App\Http\Controllers\Api\TryoutResultController;
use App\Http\Controllers\Api\RankingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RegionController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AdminTopupTransactionController;
use App\Http\Controllers\Api\TopupPackageController;
use App\Http\Controllers\Api\AdminUploadController;
use App\Http\Controllers\Api\AdminTryoutProgressController;
use App\Http\Controllers\Api\AdminTryoutRegistrationController;
use App\Http\Controllers\Api\AdminBundleController;
use App\Http\Controllers\Api\BundleController;
use App\Http\Controllers\Api\User\UserAuthController;
use App\Http\Controllers\Api\User\GoogleAuthController;
use App\Http\Controllers\Api\User\TryoutController as UserTryoutController;
use App\Http\Controllers\Api\User\PublicProfileController;
use App\Http\Controllers\Api\PublicTryoutController;
use App\Http\Controllers\Api\PublicStatsController;

Route::get('/ping', function () {
    return response()->json([
    'status' => true,
    'message' => 'API connected',
    ]);
});

/*
 |--------------------------------------------------------------------------
 | USER AUTH Cukimai
 |--------------------------------------------------------------------------
 */



Route::prefix('user')->group(function () {

    Route::post('/register', [UserAuthController::class , 'register']);
    Route::post('/login', [UserAuthController::class , 'login']);
    Route::post('/forgot-password', [UserAuthController::class , 'forgotPassword']);
    Route::post('/reset-password', [UserAuthController::class , 'resetPassword']);
    Route::get('/email/verify/{id}/{hash}', [UserAuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::get('/google/redirect', [GoogleAuthController::class , 'redirect'])->defaults('scope', 'user');
    Route::get('/google/callback', [GoogleAuthController::class , 'callback'])->defaults('scope', 'user');
    Route::get('/auth/google/redirect', [GoogleAuthController::class , 'redirect'])->defaults('scope', 'user');
    Route::get('/auth/google/callback', [GoogleAuthController::class , 'callback'])->defaults('scope', 'user');

    Route::middleware('auth:sanctum')->group(function () {

            Route::get('/me', [UserAuthController::class , 'me']);
            Route::put('/profile', [UserAuthController::class , 'updateProfile']);
            Route::post('/logout', [UserAuthController::class , 'logout']);
            Route::post('/email/verification-notification', [UserAuthController::class, 'sendVerificationNotification']);

        }
        );

    });

/*
 |--------------------------------------------------------------------------
 | ADMIN AUTH
 |--------------------------------------------------------------------------
 */

Route::prefix('admin')->group(function () {

    Route::post('/login', [AuthController::class , 'login']);

    Route::get('/google/redirect', [GoogleAuthController::class , 'redirect'])->defaults('scope', 'admin');
    Route::get('/google/callback', [GoogleAuthController::class , 'callback'])->defaults('scope', 'admin');
    Route::get('/auth/google/redirect', [GoogleAuthController::class , 'redirect'])->defaults('scope', 'admin');
    Route::get('/auth/google/callback', [GoogleAuthController::class , 'callback'])->defaults('scope', 'admin');

    Route::middleware('auth:sanctum')->group(function () {

            Route::get('/me', [AuthController::class , 'me']);
            Route::post('/logout', [AuthController::class , 'logout']);

        }
        );

    });

/*
 |--------------------------------------------------------------------------
 | USER ROUTES
 |--------------------------------------------------------------------------
 */

Route::middleware(['auth:sanctum', 'user'])->group(function () {

    Route::get('/dashboard/summary', [DashboardController::class , 'summary']);

    Route::get('/tryouts', [UserTryoutController::class , 'index']);

    Route::get('/tryouts/{id}', [UserTryoutController::class , 'show']);

    Route::post('/tryouts/{id}/register', [TryoutRegistrationController::class , 'register']);

    Route::post('/tryouts/{id}/start', [TryoutController::class , 'start']);

    Route::post('/tryouts/{id}/autosave', [TryoutController::class , 'autosave']);

    Route::post('/tryouts/{id}/submit', [TryoutController::class , 'submit']);

    Route::get('/tryouts/{id}/remaining-time', [TryoutController::class , 'remainingTime']);

    Route::get('/tryouts/{id}/result', [TryoutResultController::class , 'show']);

    Route::get('/history', [TryoutRegistrationController::class , 'history']);

    Route::get('/wallet', [WalletController::class , 'index']);

    Route::get('/wallet/topup-packages', [WalletController::class , 'topupPackages']);

    Route::post('/wallet/topup/create', [WalletController::class , 'createTopup']);

    Route::get('/wallet/topup/{id}', [WalletController::class , 'showTopup']);

    Route::post('/wallet/topup/{id}/sync', [WalletController::class , 'syncTopup']);

    Route::get('/wallet/redeemable-tryouts', [WalletController::class , 'redeemableTryouts']);

    Route::post('/wallet/redeem-tryout/{id}', [WalletController::class , 'redeemTryout']);

    Route::get('/regions/provinces', [RegionController::class, 'provinces']);
    Route::get('/regions/regencies/{provinceCode}', [RegionController::class, 'regencies']);
    Route::get('/regions/districts/{regencyCode}', [RegionController::class, 'districts']);


    Route::get('/tryouts/{id}/ranking', [RankingController::class , 'index']);

    Route::get('/tryouts/{id}/my-rank', [RankingController::class , 'myRank']);

    Route::get('/users/{id}/public-profile', [PublicProfileController::class, 'show']);

    // Bundle routes (user)
    Route::get('/bundles', [BundleController::class, 'index']);
    Route::get('/bundles/{id}', [BundleController::class, 'show']);
    Route::post('/bundles/{id}/purchase', [BundleController::class, 'purchase']);
    Route::post('/bundles/{id}/sync', [BundleController::class, 'syncPayment']);
    Route::get('/bundles/{id}/swap-candidates/{tryoutId}', [BundleController::class, 'swapCandidates']);
    Route::post('/bundles/{id}/swap-tryout', [BundleController::class, 'swapTryout']);

});

Route::post('/payments/midtrans/webhook', [PaymentController::class , 'midtransWebhook']);

/*
 |--------------------------------------------------------------------------
 | PUBLIC ROUTES (no auth required)
 |--------------------------------------------------------------------------
 */

Route::middleware('throttle:30,1')->group(function () {
    Route::get('/public/tryouts', [PublicTryoutController::class, 'index']);
    Route::get('/public/stats', [PublicStatsController::class, 'index']);
});


/*
 |--------------------------------------------------------------------------
 | ADMIN ROUTES
 |--------------------------------------------------------------------------
 */

Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin')
    ->group(function () {

        /*
     |--------------------------------------------------------------------------
     | SOAL MANAGEMENT
     |--------------------------------------------------------------------------
     */

        Route::apiResource('/soal', SoalController::class);

        Route::post('/uploads/images', [AdminUploadController::class, 'storeImage']);

        Route::apiResource('/topup-packages', TopupPackageController::class);

        Route::get('/users/active-count', [AdminUserController::class , 'activeCount']);
        Route::get('/users/count', [AdminUserController::class , 'totalCount']);
        Route::get('/topup-transactions/summary', [AdminTopupTransactionController::class , 'summary']);
        Route::get('/users', [AdminUserController::class , 'index']);
        Route::get('/users/{id}/tryout-summary', [AdminUserController::class , 'tryoutSummary']);
        Route::patch('/users/{id}', [AdminUserController::class , 'update']);

        /*
     |--------------------------------------------------------------------------
     | TRYOUT MANAGEMENT
     |--------------------------------------------------------------------------
     */
        Route::get('/tryouts', [TryoutController::class , 'index']);
        Route::get('/tryouts/{id}', [TryoutController::class , 'show']);
        Route::post('/tryouts', [TryoutController::class , 'store']);
        Route::put('/tryouts/{id}', [TryoutController::class , 'update']);
        Route::delete('/tryouts/{id}', [TryoutController::class , 'destroy']);
        Route::get('/tryouts/{id}/ranking', [\App\Http\Controllers\Api\RankingController::class , 'index']);
        Route::get('/tryout-progress', [AdminTryoutProgressController::class , 'index']);
        Route::get('/tryout-history', [AdminTryoutProgressController::class , 'history']);
        Route::get('/tryout-registrations/pending', [AdminTryoutRegistrationController::class , 'pending']);
        Route::get('/tryout-registrations/summary', [AdminTryoutRegistrationController::class , 'summary']);
        Route::get('/tryouts/{id}/participants', [AdminTryoutRegistrationController::class , 'participants']);


        /*
     |--------------------------------------------------------------------------
     | TRYOUT SOAL MANAGEMENT
     |--------------------------------------------------------------------------
     */

        Route::post('/tryouts/{id}/attach', [TryoutController::class , 'attachSoal']);

        Route::delete('/tryouts/{id}/detach/{soalId}', [TryoutController::class , 'detachSoal']);

        Route::put('/tryouts/{id}/reorder', [TryoutController::class , 'reorder']);

        Route::post('/tryouts/{id}/publish', [TryoutController::class , 'publish']);

        /*
     |--------------------------------------------------------------------------
     | BUNDLE MANAGEMENT
     |--------------------------------------------------------------------------
     */
        Route::get('/bundles', [AdminBundleController::class, 'index']);
        Route::post('/bundles', [AdminBundleController::class, 'store']);
        Route::get('/bundles/{id}', [AdminBundleController::class, 'show']);
        Route::put('/bundles/{id}', [AdminBundleController::class, 'update']);
        Route::delete('/bundles/{id}', [AdminBundleController::class, 'destroy']);
        Route::get('/bundles/{id}/transactions', [AdminBundleController::class, 'transactions']);

    });
