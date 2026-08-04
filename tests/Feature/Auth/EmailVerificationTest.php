<?php

use App\Models\Attendee;
use App\Notifications\Auth\VerifyAttendeeEmail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

// throttle:1,1 and throttle:6,1 use the array cache store in tests (phpunit.xml), which
// persists across test methods within the same process (unlike the database store, it is
// not rolled back by DatabaseTransactions) — flush it so each test starts with a clean
// rate-limit counter regardless of execution order.
beforeEach(fn () => Cache::flush());

function signedVerificationUrl(Attendee $attendee, $expiresAt = null): string
{
    return URL::temporarySignedRoute(
        'verification.verify',
        $expiresAt ?? now()->addMinutes(1440),
        ['id' => $attendee->id, 'hash' => sha1($attendee->getEmailForVerification())]
    );
}

it('verifies via a valid signed link and redirects to login with a success flag', function () {
    $attendee = Attendee::factory()->unverified()->create();

    get(signedVerificationUrl($attendee))->assertRedirect('/auth/login?verified=1');

    expect($attendee->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('is an idempotent no-op when the link is visited again after already verifying', function () {
    $attendee = Attendee::factory()->create(); // verified by default factory state

    get(signedVerificationUrl($attendee))->assertRedirect('/auth/login?verified=1');

    expect($attendee->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('redirects to a failure state for a tampered hash', function () {
    $attendee = Attendee::factory()->unverified()->create();

    $url = signedVerificationUrl($attendee);
    // `hash` is a path segment (route: /email/verify/{id}/{hash}), not a query parameter —
    // replace the 40-char sha1 segment immediately preceding the query string.
    $tampered = preg_replace('/[a-f0-9]{40}\?/', str_repeat('0', 40).'?', $url);

    get($tampered)->assertRedirect('/auth/login?verification=failed');

    expect($attendee->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('redirects to a failure state for an expired (over 24h) link', function () {
    $attendee = Attendee::factory()->unverified()->create();

    get(signedVerificationUrl($attendee, now()->subMinute()))
        ->assertRedirect('/auth/login?verification=failed');

    expect($attendee->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('queues a new verification notification when resending for an unverified account', function () {
    Notification::fake();
    $attendee = Attendee::factory()->unverified()->create();

    postJson('/email/verification-notification', ['email' => $attendee->email])
        ->assertOk()
        ->assertJson(['message' => 'If that account needs verification, a new link has been sent.']);

    Notification::assertSentTo($attendee, VerifyAttendeeEmail::class);
});

it('returns the identical response for an already-verified account without sending anything', function () {
    Notification::fake();
    $attendee = Attendee::factory()->create(); // verified

    postJson('/email/verification-notification', ['email' => $attendee->email])
        ->assertOk()
        ->assertJson(['message' => 'If that account needs verification, a new link has been sent.']);

    Notification::assertNothingSent();
});

it('returns the identical response for a nonexistent email without disclosing anything', function () {
    Notification::fake();

    postJson('/email/verification-notification', ['email' => 'nobody@gmail.com'])
        ->assertOk()
        ->assertJson(['message' => 'If that account needs verification, a new link has been sent.']);

    Notification::assertNothingSent();
});

it('throttles a second resend within 60 seconds to 429', function () {
    $attendee = Attendee::factory()->unverified()->create();

    postJson('/email/verification-notification', ['email' => $attendee->email])->assertOk();
    postJson('/email/verification-notification', ['email' => $attendee->email])->assertStatus(429);
});
