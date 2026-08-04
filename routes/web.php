<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredAttendeeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// SPA shell for every attendee-facing auth screen (TanStack Router handles client-side
// routing beneath this single Blade entry point).
Route::get('/auth/{any?}', function () {
    return view('app');
})->where('any', '.*');

Route::post('/register', [RegisteredAttendeeController::class, 'store'])
    ->middleware('throttle:register')
    ->name('register');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('throttle:login')
    ->name('login');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
    ->middleware('throttle:1,1')
    ->name('verification.send');

Route::post('/forgot-password', [PasswordResetController::class, 'store'])
    ->middleware('throttle:1,1')
    ->name('password.email');

Route::post('/reset-password', [PasswordResetController::class, 'update'])
    ->name('password.update');
