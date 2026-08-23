<?php

use App\Http\Controllers\Api\Admin\RestaurantController;
use App\Http\Controllers\Api\Admin\RestaurantStaffController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Customer\OrderController;
use App\Http\Controllers\Api\Customer\RestaurantController as CustomerRestaurantController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\Staff\MenuItemController;
use App\Http\Controllers\Api\Staff\OrderController as StaffOrderController;
use App\Http\Controllers\Api\StatisticsController;
use App\Http\Controllers\Api\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Telegram bot webhook
|--------------------------------------------------------------------------
|
| Telegram bu manzilga yangilanishlarni yuboradi. Autentifikatsiya yoʻq —
| himoya `X-Telegram-Bot-Api-Secret-Token` sarlavhasi orqali (kontroller
| ichida tekshiriladi). Chastota chegarasi keng: bir necha odam bir vaqtda
| yozsa ham yangilanishlar tushib qolmasligi kerak.
|
*/

Route::post('telegram/webhook', TelegramWebhookController::class)->middleware('throttle:120,1');

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

    // Telegram Mini App ichidan kirish (7-bosqich)
    Route::post('telegram', [AuthController::class, 'telegram'])->middleware('throttle:20,1');

    // Parolni unutgan foydalanuvchi uchun
    Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])
        ->middleware('throttle:5,1');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::patch('profile', [AuthController::class, 'updateProfile']);
        Route::put('password', [AuthController::class, 'updatePassword']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

/*
|--------------------------------------------------------------------------
| Mijoz: oshxonalar, menyu va buyurtmalar (2-bosqich)
|--------------------------------------------------------------------------
|
| Browsing is open to everyone; ordering requires a signed in customer.
|
*/

Route::get('restaurants', [CustomerRestaurantController::class, 'index']);
Route::get('restaurants/{restaurant}', [CustomerRestaurantController::class, 'show'])
    ->whereNumber('restaurant');

Route::middleware(['auth:sanctum', 'role:customer'])->group(function () {
    Route::get('orders', [OrderController::class, 'index']);
    // Savatcha oʻzgarganda soʻraladi, shuning uchun chegara kengroq.
    Route::post('orders/estimate', [OrderController::class, 'estimate'])->middleware('throttle:60,1');
    Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:20,1');
    Route::get('orders/{order}', [OrderController::class, 'show'])->whereNumber('order');
    Route::post('orders/{order}/pay', [OrderController::class, 'pay'])
        ->whereNumber('order')
        ->middleware('throttle:20,1');

    // Mahsulot tugaganda mijozning tanlovi (5-bosqich)
    Route::post('orders/{order}/replace-item', [OrderController::class, 'replaceItem'])->whereNumber('order');
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->whereNumber('order');
});

/*
|--------------------------------------------------------------------------
| Super admin: restaurants and their staff accounts (1-bosqich)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'role:super_admin'])->prefix('admin')->group(function () {
    Route::get('statistics', [StatisticsController::class, 'admin']);
    Route::get('restaurants', [RestaurantController::class, 'index']);
    Route::post('restaurants', [RestaurantController::class, 'store']);
    Route::get('restaurants/{restaurant}', [RestaurantController::class, 'show']);
    Route::patch('restaurants/{restaurant}', [RestaurantController::class, 'update']);

    Route::get('restaurants/{restaurant}/staff', [RestaurantStaffController::class, 'index']);
    Route::post('restaurants/{restaurant}/staff', [RestaurantStaffController::class, 'store']);
    Route::delete('restaurants/{restaurant}/staff/{staff}', [RestaurantStaffController::class, 'destroy']);
    Route::post('restaurants/{restaurant}/staff/{staff}/restore', [RestaurantStaffController::class, 'restore']);
});

/*
|--------------------------------------------------------------------------
| Restaurant staff: the menu of their own restaurant (1-bosqich)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'role:restaurant_staff'])->prefix('staff')->group(function () {
    Route::get('statistics', [StatisticsController::class, 'staff']);
    Route::get('menu-items', [MenuItemController::class, 'index']);
    Route::post('menu-items', [MenuItemController::class, 'store']);
    Route::get('menu-items/{menuItem}', [MenuItemController::class, 'show'])->whereNumber('menuItem');
    Route::patch('menu-items/{menuItem}', [MenuItemController::class, 'update'])->whereNumber('menuItem');
    Route::delete('menu-items/{menuItem}', [MenuItemController::class, 'destroy'])->whereNumber('menuItem');
    // Modifikator guruhlari (9-bosqich)
    Route::put('menu-items/{menuItem}/options', [MenuItemController::class, 'syncOptions'])
        ->whereNumber('menuItem');

    Route::post('menu-items/{menuItem}/image', [MenuItemController::class, 'uploadImage'])
        ->whereNumber('menuItem');
    Route::delete('menu-items/{menuItem}/image', [MenuItemController::class, 'destroyImage'])
        ->whereNumber('menuItem');

    // Buyurtmalar taxtasi va holatni oʻzgartirish (4-bosqich)
    Route::get('orders', [StaffOrderController::class, 'index']);
    Route::get('orders/{order}', [StaffOrderController::class, 'show'])->whereNumber('order');
    Route::patch('orders/{order}', [StaffOrderController::class, 'update'])->whereNumber('order');
    Route::post('orders/{order}/out-of-stock', [StaffOrderController::class, 'reportOutOfStock'])
        ->whereNumber('order');
});
