<?php

use App\Models\Order;
use App\Models\Staff;

it('persists and retrieves the order audit fields', function () {
    $creator = Staff::factory()->create();
    $confirmer = Staff::factory()->create();
    $refunder = Staff::factory()->create();

    // Truncate to whole seconds: the `timestamp` column type stores no fractional seconds.
    $confirmedAt = now()->subDays(2)->startOfSecond();
    $refundedAt = now()->startOfSecond();

    $order = Order::factory()->create([
        'created_by' => $creator->id,
        'confirmed_by' => $confirmer->id,
        'refunded_by' => $refunder->id,
        'confirmed_at' => $confirmedAt,
        'refunded_at' => $refundedAt,
    ]);

    $fresh = $order->fresh();

    expect($fresh->createdBy->id)->toBe($creator->id)
        ->and($fresh->confirmedBy->id)->toBe($confirmer->id)
        ->and($fresh->refundedBy->id)->toBe($refunder->id)
        ->and($fresh->confirmed_at->equalTo($confirmedAt))->toBeTrue()
        ->and($fresh->refunded_at->equalTo($refundedAt))->toBeTrue();
});
