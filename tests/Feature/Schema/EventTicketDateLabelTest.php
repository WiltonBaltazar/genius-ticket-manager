<?php

use App\Models\Event;
use Carbon\Carbon;

it('formats a single-day event as one date with the start time', function () {
    $event = Event::factory()->create(['start_date' => '2026-10-05 14:30:00', 'end_date' => '2026-10-05']);

    expect($event->ticketDateLabel())->toBe('05 de outubro, 14:30');
});

it('formats a single-day pass on a multi-day event using the ticket\'s own day, no time', function () {
    $event = Event::factory()->create(['start_date' => '2026-11-12 09:00:00', 'end_date' => '2026-11-14']);

    expect($event->ticketDateLabel(Carbon::parse('2026-11-13')))->toBe('13 de novembro');
});

it('formats a whole-event multi-day pass within the same month as a compact range, dropping the time', function () {
    $event = Event::factory()->create(['start_date' => '2026-11-12 09:00:00', 'end_date' => '2026-11-14']);

    expect($event->ticketDateLabel())->toBe("12–14\u{00A0}de\u{00A0}novembro");
});

it('formats a whole-event multi-day pass spanning two months with each date kept non-breaking', function () {
    $event = Event::factory()->create(['start_date' => '2026-10-30 18:00:00', 'end_date' => '2026-11-02']);

    expect($event->ticketDateLabel())->toBe("30\u{00A0}de\u{00A0}outubro – 02\u{00A0}de\u{00A0}novembro");
});
