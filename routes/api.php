<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SoalController;
use App\Http\Controllers\Api\TryoutController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TryoutResultController;
use App\Http\Controllers\Api\RankingController;

/*
|--------------------------------------------------------------------------
| AUTH ROUTES
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::post('/admin/login', [AuthController::class, 'login']);
Route::post('/admin/register', [AuthController::class, 'register']);



/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/




/*
|--------------------------------------------------------------------------
| USER ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

   

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/tryouts/{id}/start', [TryoutController::class, 'start']);

    Route::post('/tryouts/{id}/autosave', [TryoutController::class, 'autosave']);

    Route::post('/tryouts/{id}/submit', [TryoutController::class, 'submit']);

    Route::get('/tryouts/{id}/remaining-time', [TryoutController::class, 'remainingTime']);

    Route::get('/tryouts/{id}/result', [TryoutResultController::class, 'show']);

    Route::get('/history', [TryoutResultController::class, 'history']);

    Route::get('/tryouts/{id}/ranking', [RankingController::class, 'index']);

    Route::get('/tryouts/{id}/my-rank', [RankingController::class, 'myRank']);

});


/*
|--------------------------------------------------------------------------
| ADMIN ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum','admin'])
->prefix('admin')
->group(function () {

    /*
    |--------------------------------------------------------------------------
    | SOAL MANAGEMENT
    |--------------------------------------------------------------------------
    */

    Route::apiResource('/soal', SoalController::class);

    /*
    |--------------------------------------------------------------------------
    | TRYOUT MANAGEMENT
    |--------------------------------------------------------------------------
    */
    Route::get('/tryouts', [TryoutController::class, 'index']);
    
    Route::get('/tryouts/{id}', [TryoutController::class, 'show']);

    Route::get('/tryouts', [TryoutController::class,'index']);      // list
    Route::post('/tryouts', [TryoutController::class,'store']);     // create
    Route::put('/tryouts/{id}', [TryoutController::class,'update']); // update
    Route::delete('/tryouts/{id}', [TryoutController::class,'destroy']); // delete


    /*
    |--------------------------------------------------------------------------
    | TRYOUT SOAL MANAGEMENT
    |--------------------------------------------------------------------------
    */

    Route::post('/tryouts/{id}/attach', [TryoutController::class, 'attachSoal']);

    Route::delete('/tryouts/{id}/detach/{soalId}', [TryoutController::class, 'detachSoal']);

    Route::put('/tryouts/{id}/reorder', [TryoutController::class, 'reorder']);

    Route::post('/tryouts/{id}/publish', [TryoutController::class, 'publish']);

});