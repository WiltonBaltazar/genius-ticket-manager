<?php

use App\Models\Ticket;
use Illuminate\Database\QueryException;

it('rejects a second ticket with a duplicate qr_code', function () {
    Ticket::factory()->create(['qr_code' => 'QR-DUPLICATE-TEST']);

    expect(fn () => Ticket::factory()->create(['qr_code' => 'QR-DUPLICATE-TEST']))
        ->toThrow(QueryException::class);

    expect(Ticket::where('qr_code', 'QR-DUPLICATE-TEST')->count())->toBe(1);
});
