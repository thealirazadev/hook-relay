<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DestinationController;
use App\Http\Controllers\DlqController;
use App\Http\Controllers\SourceController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('sources', SourceController::class)->except(['show']);
    Route::resource('destinations', DestinationController::class)->except(['show']);

    Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show'])->name('deliveries.show');

    Route::get('/dlq', [DlqController::class, 'index'])->name('dlq.index');
    Route::post('/dlq/requeue-all', [DlqController::class, 'requeueAll'])->name('dlq.requeue-all');
    Route::post('/dlq/{delivery}/requeue', [DlqController::class, 'requeue'])->name('dlq.requeue');
});
