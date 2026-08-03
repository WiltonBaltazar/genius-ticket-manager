<?php

use App\Models\Order;
use Illuminate\Database\QueryException;

it('rejects a second order with a duplicate transaction_hash', function () {
    $first = Order::factory()->create(['transaction_hash' => 'txn_duplicate_test']);

    expect(fn () => Order::factory()->create(['transaction_hash' => 'txn_duplicate_test']))
        ->toThrow(QueryException::class);

    expect(Order::where('transaction_hash', 'txn_duplicate_test')->count())->toBe(1);
});
