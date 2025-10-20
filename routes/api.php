<?php

use App\Http\Controllers\Api\Event\EventController;
use App\Http\Controllers\Api\EventPrice\EventPriceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentMethod\PaymentMethodController;
use App\Http\Controllers\Api\Purchase\PurchaseController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/test', [AuthController::class, 'test'])->name('test');
Route::post('/register', [AuthController::class, 'register'])->name('register');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth:api');
Route::post('purchases', [PurchaseController::class, 'store']);
Route::post('purchases/single', [PurchaseController::class, 'storeSingle']);
Route::get('transaction/{transactionId}', [PurchaseController::class, 'showByTransaction']);
Route::get('/events/active', [EventController::class, 'active']);
Route::get('/events-prices', [EventPriceController::class, 'index']);
Route::get('payment-methods/active', [PaymentMethodController::class, 'active']);
Route::get('purchases-whatsapp/{whatsapp}', [PurchaseController::class, 'getByWhatsApp'])
    ->name('purchases.by.whatsapp');
Route::get('purchases-identificacion/{identificacion}', [PurchaseController::class, 'getByIdentificacion'])
    ->name('purchases.by.identificacion');
Route::middleware('auth:api')->group(function () {


    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread', [NotificationController::class, 'unread']);
        Route::get('/unread/count', [NotificationController::class, 'unreadCount']);
        Route::get('/{id}', [NotificationController::class, 'show']);
        Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::patch('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
        Route::delete('/clear/read', [NotificationController::class, 'clearRead']);
    });
    Route::prefix('purchases')->group(function () {
        Route::post('admin', [PurchaseController::class, 'storeAdmin']);
        Route::post('admin/random', [PurchaseController::class, 'storeAdminRandom']);
        Route::get('my-purchases', [PurchaseController::class, 'myPurchases']);
        Route::get('transaction/{transactionId}', [PurchaseController::class, 'showByTransaction']);
        Route::patch('{transactionId}/approve', [PurchaseController::class, 'approve']);
        Route::patch('{transactionId}/reject', [PurchaseController::class, 'reject']);
    });

    Route::get('purchases', [PurchaseController::class, 'index']);
    Route::get('purchases/event/{eventId}', [PurchaseController::class, 'getPurchasesByEvent']);
    Route::prefix('payment-methods')->group(function () {

        Route::middleware(['admin'])->group(function () {
            Route::get('/', [PaymentMethodController::class, 'index']);
            Route::post('/', [PaymentMethodController::class, 'store']);
            Route::get('/{id}', [PaymentMethodController::class, 'show']);
            Route::put('/{id}', [PaymentMethodController::class, 'update']);
            Route::delete('/{id}', [PaymentMethodController::class, 'destroy']);
            Route::patch('/{id}/toggle-active', [PaymentMethodController::class, 'toggleActive']);
        });
    });
    Route::middleware('admin')->group(function () {
        Route::get('purchases/summary/{transactionId}', [PurchaseController::class, 'purchaseSummary']);
        Route::get('events/{id}/statistics', [EventController::class, 'statistics']);
        Route::get('events/{id}/check-number/{number}', [EventController::class, 'checkNumber']);
        Route::get('events/{id}/occupied-numbers', [EventController::class, 'occupiedNumbers']);
        Route::get('/events/{id}/available-numbers', [EventController::class, 'availableNumbers']);
        Route::get('/events', [EventController::class, 'index']);
        Route::post('/events', [EventController::class, 'store']);
        Route::post('/events-prices', [EventPriceController::class, 'store']);
        Route::patch('/events-prices/{id}/set-default', [EventPriceController::class, 'setAsDefault']);
        Route::get('/events/{id}', [EventController::class, 'show']);
        Route::put('/events/{id}', [EventController::class, 'update']);
        Route::delete('/events/{id}', [EventController::class, 'destroy']);
        Route::post('/events/{id}/select-winner', [EventController::class, 'selectWinner']);
    });
});
