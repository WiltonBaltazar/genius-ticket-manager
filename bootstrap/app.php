<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // 004-attendee-checkout, research.md §3: releases inventory held by
        // abandoned pending orders. Requires the standard Laravel scheduler
        // cron entry (`* * * * * php artisan schedule:run`) on the server —
        // see docs/deployment-runbook.md.
        $schedule->command('orders:expire-pending')->everyFiveMinutes();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust the reverse proxy this shared-hosting deployment sits behind (e.g. LiteSpeed/
        // CDN) so X-Forwarded-For is honored for the real client IP — without this, FR-012's
        // login throttle and FR-013/FR-014's audit ip_address would trust a spoofable header
        // from an untrusted source instead. `*` here trusts the immediate upstream proxy only
        // (standard for a single reverse-proxy hop); tighten to specific IPs if the hosting
        // provider's proxy addresses are known and stable.
        $middleware->trustProxies(at: '*', headers: SymfonyRequest::HEADER_X_FORWARDED_FOR
            | SymfonyRequest::HEADER_X_FORWARDED_HOST
            | SymfonyRequest::HEADER_X_FORWARDED_PORT
            | SymfonyRequest::HEADER_X_FORWARDED_PROTO);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The scaffold's default only rendered JSON for `api/*` paths — but this app has no
        // `/api` prefix at all (research.md §1: the auth endpoints are session-based `web`
        // routes, not a stateless API). Removed in favor of Laravel's default content
        // negotiation (Accept header via $request->expectsJson()), which correctly serves
        // JSON to the React SPA's fetch calls without needing an artificial path prefix.

        // A tampered or expired (>24h) verification link throws this from the `signed`
        // middleware before EmailVerificationController::verify() ever runs — redirect to
        // the SPA's failure state (contracts/auth-api.md §GET /email/verify) instead of
        // Laravel's default 403 page.
        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            if ($request->routeIs('verification.verify')) {
                return redirect(url('/auth/login').'?verification=failed');
            }
        });

        // Laravel's default redirects an unauthenticated request to the `login`
        // named route regardless of which guard rejected it — that's the
        // attendee's POST-only /login here, not staff's. Send a 'staff'-guard
        // rejection to Filament's own login page instead, same guard the
        // check-in page (and admin panel) both share.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (in_array('staff', $e->guards(), true) && ! $request->expectsJson()) {
                return redirect()->guest('/admin/login');
            }
        });
    })->create();
