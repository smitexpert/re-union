<?php

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home');
});

Route::get('/register', [RegistrationController::class, 'show'])->name('register.show');
Route::post('/register', [RegistrationController::class, 'store'])->name('register.store');

Route::get('/payments', function () {
    return Inertia::render('PaymentLookup');
})->name('payments.lookup');

Route::get('/payments/{registration}', [PaymentController::class, 'show'])
    ->where('registration', '[A-Za-z0-9\-]+')
    ->name('payments.show');
Route::post('/payments/{registration}', [PaymentController::class, 'store'])
    ->where('registration', '[A-Za-z0-9\-]+')
    ->name('payments.store');

// Custom 404 (also covers /payments/{badCode} when registration is missing
// because PaymentController::show renders NotFound instead of aborting).
Route::fallback(function () {
    return Inertia::render('NotFound');
})->name('notfound');
