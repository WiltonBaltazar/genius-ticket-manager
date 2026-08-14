<?php

use App\Models\Attendee;
use App\Models\AuditLog;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\postJson;

// throttle:login uses the array cache store in tests (phpunit.xml), which persists across
// test methods within the same process — flush it so each test starts with a clean counter.
beforeEach(fn () => Cache::flush());

it('rejects login for an unverified account with a distinct 423 response', function () {
    $attendee = Attendee::factory()->unverified()->create(['password' => 'Str0ng!Passw0rd']);

    postJson('/login', ['email' => $attendee->email, 'password' => 'Str0ng!Passw0rd'])
        ->assertStatus(423)
        ->assertJson([
            'message' => 'Please verify your email address before logging in.',
            'resend_available' => true,
        ]);

    expect(Auth::check())->toBeFalse();
});

it('logs in a verified account, regenerates the session, and writes exactly one audit row', function () {
    $attendee = Attendee::factory()->create(['password' => 'Str0ng!Passw0rd']);

    $this->withSession([]);
    $preLoginSessionId = session()->getId();

    $response = postJson('/login', ['email' => $attendee->email, 'password' => 'Str0ng!Passw0rd']);

    $response->assertOk()->assertJson([
        'attendee' => [
            'id' => $attendee->id,
            'name' => $attendee->name,
            'email' => $attendee->email,
        ],
    ]);

    expect(session()->getId())->not->toBe($preLoginSessionId);
    expect(Auth::check())->toBeTrue();
    expect(Auth::id())->toBe($attendee->id);

    $logs = AuditLog::where('auditable_type', Attendee::class)
        ->where('auditable_id', $attendee->id)
        ->where('action', 'attendee_login')
        ->get();

    expect($logs)->toHaveCount(1);

    $log = $logs->first();
    expect($log->staff_id)->toBeNull()
        ->and($log->ip_address)->not->toBeNull();

    $changesJson = json_encode($log->changes);
    expect($changesJson)->not->toContain('Str0ng!Passw0rd')
        ->and($changesJson)->not->toContain('password');
});

it('rejects a wrong password with a generic 422 and writes a failed-login audit row', function () {
    $attendee = Attendee::factory()->create(['password' => 'Str0ng!Passw0rd']);

    postJson('/login', ['email' => $attendee->email, 'password' => 'WrongPassword1!'])
        ->assertStatus(422)
        ->assertJson([
            'errors' => ['email' => ['These credentials do not match our records.']],
        ]);

    expect(Auth::check())->toBeFalse();

    $log = AuditLog::where('auditable_type', Attendee::class)
        ->where('auditable_id', $attendee->id)
        ->where('action', 'attendee_login_failed')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->changes['reason'] ?? null)->toBe('invalid_credentials');

    $changesJson = json_encode($log->changes);
    expect($changesJson)->not->toContain('WrongPassword1!');
});

it('returns an identical 422 for a nonexistent email and writes no audit row', function () {
    postJson('/login', ['email' => 'nobody-'.uniqid().'@gmail.com', 'password' => 'WrongPassword1!'])
        ->assertStatus(422)
        ->assertJson([
            'errors' => ['email' => ['These credentials do not match our records.']],
        ]);

    expect(AuditLog::where('action', 'attendee_login_failed')->count())->toBe(0);
});

it('throttles the 6th rapid login attempt from one IP to 429 with a Retry-After header', function () {
    $attendee = Attendee::factory()->create(['password' => 'Str0ng!Passw0rd']);

    for ($i = 0; $i < 5; $i++) {
        postJson('/login', ['email' => $attendee->email, 'password' => 'WrongPassword1!'])
            ->assertStatus(422);
    }

    postJson('/login', ['email' => $attendee->email, 'password' => 'WrongPassword1!'])
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

it('resolves the web guard to the attendees provider, independent of the staff guard', function () {
    expect(Auth::guard('web')->getProvider())->toBeInstanceOf(EloquentUserProvider::class);
    expect(config('auth.guards.web.provider'))->toBe('attendees');
    expect(config('auth.guards.staff.provider'))->toBe('staff');
});
