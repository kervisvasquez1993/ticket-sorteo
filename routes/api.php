<?php

use App\Http\Controllers\Api\Event\EventController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/register', [AuthController::class, 'register'])->name('register');

Route::middleware('auth:api')->group(function () {
    Route::get('/events/active', [EventController::class, 'active']);
    Route::get('/events/{id}', [EventController::class, 'show']);
    Route::get('/events/{id}/available-numbers', [EventController::class, 'availableNumbers']);
    Route::middleware('admin')->group(function () {
        Route::get('/events', [EventController::class, 'index']);
        Route::post('/events', [EventController::class, 'store']);
        Route::put('/events/{id}', [EventController::class, 'update']);
        Route::delete('/events/{id}', [EventController::class, 'destroy']);
        Route::post('/events/{id}/select-winner', [EventController::class, 'selectWinner']);
    });
});
