<?php

use App\Models\Order;
use App\Models\OrderItem;

it('cascades order_items deletion when the order is force-deleted', function () {
    $order = Order::factory()->create();
    $item = OrderItem::factory()->for($order)->create();

    $order->forceDelete();

    expect(OrderItem::find($item->id))->toBeNull();
});
