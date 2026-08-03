<?php

use App\Models\TicketType;
use Illuminate\Database\QueryException;

it('rejects available_quantity below zero', function () {
    $type = TicketType::factory()->create(['total_quantity' => 10, 'available_quantity' => 5]);

    expect(fn () => $type->update(['available_quantity' => -1]))
        ->toThrow(QueryException::class);
});

it('rejects available_quantity above total_quantity', function () {
    $type = TicketType::factory()->create(['total_quantity' => 10, 'available_quantity' => 5]);

    expect(fn () => $type->update(['available_quantity' => 11]))
        ->toThrow(QueryException::class);
});

it('allows available_quantity at the boundaries of zero and total_quantity', function () {
    $type = TicketType::factory()->create(['total_quantity' => 10, 'available_quantity' => 5]);

    $type->update(['available_quantity' => 0]);
    expect($type->fresh()->available_quantity)->toBe(0);

    $type->update(['available_quantity' => 10]);
    expect($type->fresh()->available_quantity)->toBe(10);
});
