<?php

use App\Http\Controllers\Api\V1\SessionAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/auth')->name('api.v1.auth.session.')->group(function (): void {
    Route::post('session', [SessionAuthController::class, 'store'])
        ->middleware('throttle:login')
        ->name('store');
    Route::post('session/logout', [SessionAuthController::class, 'destroy'])
        ->middleware('auth:web')
        ->name('destroy');
});

Route::get('/', function () {
    return view('welcome');
});
