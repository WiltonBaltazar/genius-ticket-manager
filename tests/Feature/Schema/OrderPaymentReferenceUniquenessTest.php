<?php

use App\Models\Order;
use Illuminate\Database\QueryException;

it('rejects a second order with a duplicate payment_reference', function () {
    Order::factory()->create(['payment_reference' => 'QGR7DUPLICATE_TEST']);

    expect(fn () => Order::factory()->create(['payment_reference' => 'QGR7DUPLICATE_TEST']))
        ->toThrow(QueryException::class);

    expect(Order::where('payment_reference', 'QGR7DUPLICATE_TEST')->count())->toBe(1);
});
