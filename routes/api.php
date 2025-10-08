<?php

use App\Http\Controllers\Api\Event\EventController;
use App\Http\Controllers\Api\EventPrice\EventPriceController;
use App\Http\Controllers\Api\PaymentMethod\PaymentMethodController;
use App\Http\Controllers\Api\Purchase\PurchaseController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/register', [AuthController::class, 'register'])->name('register');
Route::post('purchases', [PurchaseController::class, 'store']);
Route::post('purchases/single', [PurchaseController::class, 'storeSingle']);
Route::get('transaction/{transactionId}', [PurchaseController::class, 'showByTransaction']);
Route::get('/events/active', [EventController::class, 'active']);
Route::get('/events-prices', [EventPriceController::class, 'index']);
Route::middleware('auth:api')->group(function () {

    Route::prefix('purchases')->group(function () {
        // Rutas específicas
        Route::get('my-purchases', [PurchaseController::class, 'myPurchases']);
        Route::get('transaction/{transactionId}', [PurchaseController::class, 'showByTransaction']);
        Route::patch('{transactionId}/approve', [PurchaseController::class, 'approve']);
        Route::patch('{transactionId}/reject', [PurchaseController::class, 'reject']);
        Route::post('admin', [PurchaseController::class, 'storeAdmin']);
        Route::post('admin/random', [PurchaseController::class, 'storeAdminRandom']);
    });



    // Route::post('purchases', [PurchaseController::class, 'store']);
    Route::get('purchases', [PurchaseController::class, 'index']);
    Route::get('purchases/event/{eventId}', [PurchaseController::class, 'getPurchasesByEvent']);



    Route::prefix('payment-methods')->group(function () {
        Route::get('/active', [PaymentMethodController::class, 'active']);
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


        Route::get('/events/{id}', [EventController::class, 'show']);
        Route::put('/events/{id}', [EventController::class, 'update']);
        Route::delete('/events/{id}', [EventController::class, 'destroy']);
        Route::post('/events/{id}/select-winner', [EventController::class, 'selectWinner']);
    });
});
