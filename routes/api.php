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
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AdminTopupTransactionController;
use App\Http\Controllers\Api\TopupPackageController;
use App\Http\Controllers\Api\User\UserAuthController;
use App\Http\Controllers\Api\User\GoogleAuthController;
use App\Http\Controllers\Api\User\TryoutController as UserTryoutController;

Route::get('/ping', function () {
    return response()->json([
    'status' => true,
    'message' => 'API connected',
    ]);
});

/*
 |--------------------------------------------------------------------------
 | USER AUTH
 |--------------------------------------------------------------------------
 */



Route::prefix('user')->group(function () {

    Route::post('/register', [UserAuthController::class , 'register']);
    Route::post('/login', [UserAuthController::class , 'login']);

    Route::get('/google/redirect', [GoogleAuthController::class , 'redirect'])->defaults('scope', 'user');
    Route::get('/google/callback', [GoogleAuthController::class , 'callback'])->defaults('scope', 'user');
    Route::get('/auth/google/redirect', [GoogleAuthController::class , 'redirect'])->defaults('scope', 'user');
    Route::get('/auth/google/callback', [GoogleAuthController::class , 'callback'])->defaults('scope', 'user');

    Route::middleware('auth:sanctum')->group(function () {

            Route::get('/me', [UserAuthController::class , 'me']);
            Route::post('/logout', [UserAuthController::class , 'logout']);

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


    Route::get('/tryouts/{id}/ranking', [RankingController::class , 'index']);

    Route::get('/tryouts/{id}/my-rank', [RankingController::class , 'myRank']);

});

Route::post('/payments/midtrans/webhook', [PaymentController::class , 'midtransWebhook']);


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

        Route::apiResource('/topup-packages', TopupPackageController::class);

        Route::get('/users/active-count', [AdminUserController::class , 'activeCount']);
        Route::get('/users/count', [AdminUserController::class , 'totalCount']);
        Route::get('/topup-transactions/summary', [AdminTopupTransactionController::class , 'summary']);
        Route::get('/users', [AdminUserController::class , 'index']);
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


        /*
     |--------------------------------------------------------------------------
     | TRYOUT SOAL MANAGEMENT
     |--------------------------------------------------------------------------
     */

        Route::post('/tryouts/{id}/attach', [TryoutController::class , 'attachSoal']);

        Route::delete('/tryouts/{id}/detach/{soalId}', [TryoutController::class , 'detachSoal']);

        Route::put('/tryouts/{id}/reorder', [TryoutController::class , 'reorder']);

        Route::post('/tryouts/{id}/publish', [TryoutController::class , 'publish']);

    });
