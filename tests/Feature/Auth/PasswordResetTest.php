<?php

use App\Models\Attendee;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

use function Pest\Laravel\postJson;

// throttle:1,1 uses the array cache store in tests (phpunit.xml), which persists across
// test methods within the same process — flush it so each test starts with a clean counter.
beforeEach(fn () => Cache::flush());

function validResetPayload(array $overrides = []): array
{
    return array_merge([
        'password' => 'N3w!Str0ngPassw0rd',
        'password_confirmation' => 'N3w!Str0ngPassw0rd',
    ], $overrides);
}

it('returns 200 for a forgot-password request with a known email', function () {
    $attendee = Attendee::factory()->create();

    postJson('/forgot-password', ['email' => $attendee->email])
        ->assertOk()
        ->assertJson(['message' => 'If that email is registered, a reset link has been sent.']);
});

it('returns an identical 200 for a forgot-password request with an unknown email', function () {
    postJson('/forgot-password', ['email' => 'nobody-'.uniqid().'@gmail.com'])
        ->assertOk()
        ->assertJson(['message' => 'If that email is registered, a reset link has been sent.']);
});

it('throttles a second forgot-password request within 60 seconds to 429', function () {
    $attendee = Attendee::factory()->create();

    postJson('/forgot-password', ['email' => $attendee->email])->assertOk();
    postJson('/forgot-password', ['email' => $attendee->email])->assertStatus(429);
});

it('resets the password end-to-end, deletes existing sessions, and cycles the remember token', function () {
    $attendee = Attendee::factory()->create(['password' => 'Old!StrongPassw0rd']);
    $oldRememberToken = $attendee->remember_token;

    DB::table('sessions')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $attendee->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => base64_encode('test'),
        'last_activity' => now()->timestamp,
    ]);

    $token = Password::broker('attendees')->createToken($attendee);

    postJson('/reset-password', validResetPayload([
        'email' => $attendee->email,
        'token' => $token,
    ]))
        ->assertOk()
        ->assertJson(['message' => 'Password reset successfully. Please log in again.']);

    expect(DB::table('sessions')->where('user_id', $attendee->id)->count())->toBe(0);

    $attendee->refresh();
    expect(Hash::check('N3w!Str0ngPassw0rd', $attendee->password))->toBeTrue()
        ->and(Hash::check('Old!StrongPassw0rd', $attendee->password))->toBeFalse()
        ->and($attendee->remember_token)->not->toBe($oldRememberToken);
});

it('rejects a reset attempt with a token older than 60 minutes', function () {
    $attendee = Attendee::factory()->create();

    $token = Password::broker('attendees')->createToken($attendee);

    DB::table('password_reset_tokens')
        ->where('email', $attendee->email)
        ->update(['created_at' => now()->subMinutes(61)]);

    postJson('/reset-password', validResetPayload([
        'email' => $attendee->email,
        'token' => $token,
    ]))->assertStatus(422)->assertJsonValidationErrors('token');
});

it('rejects a reset attempt with a password that does not meet the strength policy', function () {
    $attendee = Attendee::factory()->create();

    $token = Password::broker('attendees')->createToken($attendee);

    postJson('/reset-password', [
        'email' => $attendee->email,
        'token' => $token,
        'password' => 'weak',
        'password_confirmation' => 'weak',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

it('overwrites the first token when a second reset request is made for the same email, invalidating the first link', function () {
    $attendee = Attendee::factory()->create();

    $firstToken = Password::broker('attendees')->createToken($attendee);
    $secondToken = Password::broker('attendees')->createToken($attendee);

    expect(DB::table('password_reset_tokens')->where('email', $attendee->email)->count())->toBe(1);

    postJson('/reset-password', validResetPayload([
        'email' => $attendee->email,
        'token' => $firstToken,
    ]))->assertStatus(422)->assertJsonValidationErrors('token');

    postJson('/reset-password', validResetPayload([
        'email' => $attendee->email,
        'token' => $secondToken,
    ]))->assertOk();
});

it('rejects a reset attempt for an attendee soft-deleted after the request was made', function () {
    $attendee = Attendee::factory()->create();

    $token = Password::broker('attendees')->createToken($attendee);

    $attendee->delete();

    postJson('/reset-password', validResetPayload([
        'email' => $attendee->email,
        'token' => $token,
    ]))->assertStatus(422)->assertJsonValidationErrors('token');
});
