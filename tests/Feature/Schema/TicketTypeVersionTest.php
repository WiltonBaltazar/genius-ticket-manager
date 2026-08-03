<?php

use App\Models\TicketType;

it('increments version on every successful update', function () {
    $type = TicketType::factory()->create(['total_quantity' => 10, 'available_quantity' => 10, 'version' => 0]);

    $type->update(['available_quantity' => 9]);
    expect($type->fresh()->version)->toBe(1);

    $type->update(['available_quantity' => 8]);
    expect($type->fresh()->version)->toBe(2);
});
