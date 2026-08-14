<?php

namespace App\Http\Controllers\Checkout;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class EventCheckoutController extends Controller
{
    /**
     * Dual-purpose, like the /auth/{any?} SPA shell: a browser navigating here
     * gets the React shell (which re-fetches this same URL as JSON once
     * mounted); the app's own fetch() call gets the JSON payload directly.
     * The public landing page: the single most-recently-created published
     * event that hasn't already finished, so the whole buy flow (tickets,
     * attendee details) lives on this one page rather than a list linking out.
     */
    public function index(Request $request): Response
    {
        if (! $request->expectsJson()) {
            return response(view('app'));
        }

        $event = $this->activeEvents()->orderByDesc('created_at')->first();

        if ($event === null) {
            return response()->json(['event' => null, 'ticket_types' => []]);
        }

        return response()->json($this->eventPayload($event));
    }

    /**
     * Dual-purpose, like the /auth/{any?} SPA shell: a browser navigating here
     * gets the React shell (which re-fetches this same URL as JSON once
     * mounted); the app's own fetch() call gets the JSON payload directly.
     * Kept for direct/shared links to a specific event even though the
     * landing page no longer links here itself.
     */
    public function show(Request $request, Event $event): Response
    {
        abort_unless($event->status === EventStatus::Published, 404);

        if (! $request->expectsJson()) {
            return response(view('app'));
        }

        return response()->json($this->eventPayload($event));
    }

    private function activeEvents(): Builder
    {
        return Event::query()
            ->where('status', EventStatus::Published)
            ->where(function ($query) {
                $query->whereNotNull('end_date')->where('end_date', '>=', now()->toDateString())
                    ->orWhere(function ($query) {
                        $query->whereNull('end_date')->where('start_date', '>=', now());
                    });
            });
    }

    /**
     * @return array{event: array<string, mixed>, ticket_types: Collection<int, array<string, mixed>>}
     */
    private function eventPayload(Event $event): array
    {
        $ticketTypes = $this->onSaleTicketTypes($event)->get();

        return [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'venue' => $event->venue,
                'start_date' => $event->start_date,
                'end_date' => $event->end_date,
                'description' => $event->description,
                'hero_image_url' => $event->hero_image_path ? asset('storage/'.$event->hero_image_path) : null,
            ],
            'ticket_types' => $ticketTypes->map(fn ($ticketType) => [
                'id' => $ticketType->id,
                'name' => $ticketType->name,
                'description' => $ticketType->description,
                'price' => (string) $ticketType->price,
                'available_quantity' => $ticketType->available_quantity,
            ]),
        ];
    }

    private function onSaleTicketTypes(Event $event): HasMany
    {
        return $event->ticketTypes()
            ->where(function ($query) {
                $query->whereNull('sales_start_date')->orWhere('sales_start_date', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('sales_end_date')->orWhere('sales_end_date', '>=', now());
            });
    }
}
