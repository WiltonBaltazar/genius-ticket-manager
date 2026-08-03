<?php

use App\Models\Attendee;
use App\Models\Order;

it('soft-deletes an attendee while their past orders remain queryable', function () {
    $attendee = Attendee::factory()->create();
    $order = Order::factory()->for($attendee)->create();

    $attendee->delete();

    expect(Attendee::find($attendee->id))->toBeNull()
        ->and(Attendee::withTrashed()->find($attendee->id))->not->toBeNull()
        ->and(Attendee::withTrashed()->find($attendee->id)->deleted_at)->not->toBeNull();

    expect(Order::find($order->id))->not->toBeNull()
        ->and($order->fresh()->attendee_id)->toBe($attendee->id);
});
