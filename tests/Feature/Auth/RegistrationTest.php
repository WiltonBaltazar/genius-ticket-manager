<?php

use App\Models\Attendee;
use App\Models\Order;
use App\Notifications\Auth\VerifyAttendeeEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\postJson;

function validRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ada Lovelace',
        'email' => 'ada@gmail.com',
        'phone' => '+27 82 000 0000',
        'password' => 'Str0ng!Passw0rd',
        'password_confirmation' => 'Str0ng!Passw0rd',
    ], $overrides);
}

it('registers a new attendee, leaves the account unverified, and queues a verification notification', function () {
    Notification::fake();

    $response = postJson('/register', validRegistrationPayload());

    $response->assertStatus(201);

    $attendee = Attendee::where('email', 'ada@gmail.com')->first();
    expect($attendee)->not->toBeNull()
        ->and($attendee->email_verified_at)->toBeNull();

    Notification::assertSentTo($attendee, VerifyAttendeeEmail::class);
});

it('rejects a password that does not meet the strength policy', function () {
    postJson('/register', validRegistrationPayload(['password' => 'weak', 'password_confirmation' => 'weak']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');

    expect(Attendee::where('email', 'ada@gmail.com')->exists())->toBeFalse();
});

it('rejects a password longer than 72 characters', function () {
    $tooLong = str_repeat('Aa1!', 20); // 80 chars, still meets character-class rules

    postJson('/register', validRegistrationPayload(['password' => $tooLong, 'password_confirmation' => $tooLong]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

it('rejects a mismatched password confirmation', function () {
    postJson('/register', validRegistrationPayload(['password_confirmation' => 'Different!Pass1']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

it('rejects an email whose domain has no deliverable mail server', function () {
    postJson('/register', validRegistrationPayload(['email' => 'nobody@this-domain-does-not-exist-abc123.invalid']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('accepts an email whose domain has only an A record and no MX record (implicit-MX fallback)', function () {
    postJson('/register', validRegistrationPayload(['email' => 'test@dns.google']))
        ->assertStatus(201);

    expect(Attendee::where('email', 'test@dns.google')->exists())->toBeTrue();
});

it('rejects registration when the email already belongs to a password-protected account', function () {
    Attendee::factory()->create(['email' => 'ada@gmail.com']); // has a password by default

    postJson('/register', validRegistrationPayload())
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');

    expect(Attendee::where('email', 'ada@gmail.com')->count())->toBe(1);
});

it('claims an existing passwordless guest-checkout record instead of creating a duplicate', function () {
    $guest = Attendee::factory()->guest()->create(['email' => 'ada@gmail.com', 'name' => 'Guest Name']);
    $order = Order::factory()->for($guest)->create();

    postJson('/register', validRegistrationPayload(['name' => 'Ada Lovelace']))
        ->assertStatus(201);

    expect(Attendee::where('email', 'ada@gmail.com')->count())->toBe(1);

    $claimed = Attendee::find($guest->id);
    expect($claimed)->not->toBeNull()
        ->and($claimed->name)->toBe('Ada Lovelace')
        ->and($claimed->password)->not->toBeNull()
        ->and($claimed->orders()->count())->toBe(1)
        ->and($claimed->orders()->first()->id)->toBe($order->id);
});

it('stores the password as a bcrypt hash, never the raw submitted value', function () {
    postJson('/register', validRegistrationPayload());

    $attendee = Attendee::where('email', 'ada@gmail.com')->first();

    expect($attendee->password)->not->toBe('Str0ng!Passw0rd')
        ->and(Hash::check('Str0ng!Passw0rd', $attendee->password))->toBeTrue();
});

it('queues the verification notification instead of sending it inline, so registration does not block on mail delivery', function () {
    // phpunit.xml forces QUEUE_CONNECTION=sync globally so other tests can assert on
    // synchronously-processed side effects — override it just for this test to prove
    // the notification is genuinely deferred to a worker (FR-006), not executed inline.
    config(['queue.default' => 'database']);

    $response = postJson('/register', validRegistrationPayload());

    $response->assertStatus(201);

    expect(DB::table('jobs')->count())->toBeGreaterThan(0);
});

it('throttles registration to 5 attempts per minute per IP', function () {
    RateLimiter::clear('register:127.0.0.1');

    for ($i = 0; $i < 5; $i++) {
        postJson('/register', validRegistrationPayload(['email' => "attempt{$i}@gmail.com"]))
            ->assertStatus(201);
    }

    postJson('/register', validRegistrationPayload(['email' => 'attempt6@gmail.com']))
        ->assertStatus(429);
});
