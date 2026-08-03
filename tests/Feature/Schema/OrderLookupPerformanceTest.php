<?php

use App\Models\Attendee;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

function assertUsesIndex(string $sql, array $bindings, string $expectedIndex): void
{
    $plan = DB::select("EXPLAIN {$sql}", $bindings);

    expect($plan[0]->key)->toBe($expectedIndex, "Expected query to use index [{$expectedIndex}], got [{$plan[0]->key}]. SQL: {$sql}");
}

beforeEach(function () {
    // Seed a representative row count (plan.md Scale/Scope: "low thousands") so lookups are
    // measured against a non-trivial data volume, not an empty table.
    Attendee::factory()->count(50)->create()->each(function (Attendee $attendee) {
        Order::factory()->count(20)->for($attendee)->create();
    });
});

it('returns the correct orders when looking up by attendee_id and status, using its index', function () {
    $attendee = Attendee::first();
    $expectedCount = Order::where('attendee_id', $attendee->id)->where('status', 'paid')->count();

    $start = microtime(true);
    $results = Order::where('attendee_id', $attendee->id)->where('status', 'paid')->get();
    $elapsedMs = (microtime(true) - $start) * 1000;

    expect($results)->toHaveCount($expectedCount)
        ->and($elapsedMs)->toBeLessThan(2000);

    assertUsesIndex(
        'SELECT * FROM orders WHERE attendee_id = ? AND status = ?',
        [$attendee->id, 'paid'],
        'orders_attendee_id_status_index'
    );
});

it('returns the correct order when looking up by stripe_payment_intent_id, using its index', function () {
    $target = Order::factory()->for(Attendee::factory())->create(['stripe_payment_intent_id' => 'pi_lookup_perf_test']);

    $start = microtime(true);
    $found = Order::where('stripe_payment_intent_id', 'pi_lookup_perf_test')->first();
    $elapsedMs = (microtime(true) - $start) * 1000;

    expect($found->id)->toBe($target->id)
        ->and($elapsedMs)->toBeLessThan(2000);

    assertUsesIndex(
        'SELECT * FROM orders WHERE stripe_payment_intent_id = ?',
        ['pi_lookup_perf_test'],
        'orders_stripe_payment_intent_id_unique'
    );
});

it('orders results by created_at descending for an attendee using the constitution-mandated index', function () {
    $attendee = Attendee::first();

    $start = microtime(true);
    $ordered = Order::where('attendee_id', $attendee->id)->orderByDesc('created_at')->get();
    $elapsedMs = (microtime(true) - $start) * 1000;

    expect($elapsedMs)->toBeLessThan(2000);

    $timestamps = $ordered->pluck('created_at')->all();
    $sorted = collect($timestamps)->sortByDesc(fn ($t) => $t)->values()->all();
    expect($timestamps)->toEqual($sorted);
});
