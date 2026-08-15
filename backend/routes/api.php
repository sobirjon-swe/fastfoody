<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication (0-bosqich)
|--------------------------------------------------------------------------
|
| Token based auth via Sanctum. Registration always creates a customer;
| restaurant staff and super admin accounts are provisioned by a super admin.
|
*/

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

/*
| Role restricted routes are added from 1-bosqich onwards and hang off the
| 'role' middleware alias, e.g.:
|
|   Route::middleware(['auth:sanctum', 'role:super_admin'])->prefix('admin')
|   Route::middleware(['auth:sanctum', 'role:restaurant_staff'])->prefix('staff')
*/
