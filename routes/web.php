<?php

use App\Http\Controllers\Admin\OrderProofOfPaymentController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredAttendeeController;
use App\Http\Controllers\Checkout\EventCheckoutController;
use App\Http\Controllers\Checkout\OrderController;
use App\Http\Controllers\Checkout\ProofOfPaymentController;
use App\Http\Controllers\Checkout\TicketPdfController;
use Illuminate\Support\Facades\Route;

// Dual-purpose, same as GET /events/{event:slug} below: the public landing
// page and ticket-buying entry point (004-attendee-checkout follow-up).
Route::get('/', [EventCheckoutController::class, 'index'])
    ->name('events.index');

// SPA shell for every attendee-facing auth screen (TanStack Router handles client-side
// routing beneath this single Blade entry point).
Route::get('/auth/{any?}', function () {
    return view('app');
})->where('any', '.*');

// Dual-purpose: serves the SPA shell for a browser navigation, JSON for the
// app's own fetch() call to the same URL (see EventCheckoutController).
Route::get('/events/{event:slug}', [EventCheckoutController::class, 'show'])
    ->name('events.show');

Route::post('/checkout', [OrderController::class, 'store'])
    ->name('checkout.store');

// Dual-purpose, same as GET /events/{event:slug} above.
Route::get('/orders/{order}', [OrderController::class, 'show'])
    ->name('orders.show');

Route::post('/orders/{order}/proof-of-payment', [ProofOfPaymentController::class, 'store'])
    ->name('orders.proof-of-payment');

Route::get('/admin/orders/{order}/proof-of-payment-file', [OrderProofOfPaymentController::class, 'show'])
    ->middleware('auth:staff')
    ->name('admin.orders.proof-of-payment');

Route::get('/orders/{order}/tickets/{ticket}/pdf', [TicketPdfController::class, 'show'])
    ->name('orders.tickets.pdf');

Route::post('/register', [RegisteredAttendeeController::class, 'store'])
    ->middleware('throttle:register')
    ->name('register');

Route::get('/session', [AuthenticatedSessionController::class, 'show'])
    ->name('session.show');

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
