<?php

use App\Models\Order;
use Illuminate\Database\QueryException;

it('rejects a second order with a duplicate stripe_payment_intent_id', function () {
    Order::factory()->create(['stripe_payment_intent_id' => 'pi_duplicate_test']);

    expect(fn () => Order::factory()->create(['stripe_payment_intent_id' => 'pi_duplicate_test']))
        ->toThrow(QueryException::class);

    expect(Order::where('stripe_payment_intent_id', 'pi_duplicate_test')->count())->toBe(1);
});
