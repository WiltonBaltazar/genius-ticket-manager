<?php

namespace App\Models;

use App\Enums\EventStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'venue',
        'hero_image_path',
        'start_date',
        'end_date',
        'status',
        'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'date',
            'status' => EventStatus::class,
        ];
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    /**
     * Whole calendar days the event spans, inclusive of both ends — the divisor
     * for a single-day ticket's price and the range a day selection must fall in.
     */
    public function daysCount(): int
    {
        return (int) $this->start_date->copy()->startOfDay()->diffInDays($this->end_date->copy()->startOfDay()) + 1;
    }

    /**
     * Formats the date a ticket PDF shows, in Portuguese, sized to fit the ticket's
     * narrow two-column info grid without wrapping mid-phrase. Pass the ticket's own
     * event_date for a single-day pass on a multi-day event; null for a ticket that
     * covers the whole event.
     *
     * A whole-event, multi-day range drops the start time (not meaningful for a
     * range) and joins each "d de F" phrase with non-breaking spaces — without that,
     * a narrow column wraps between the day number and its month (e.g. "...– 14" /
     * "de novembro") instead of at the space around the dash.
     */
    public function ticketDateLabel(?Carbon $ticketEventDate = null): string
    {
        if ($ticketEventDate !== null) {
            return $ticketEventDate->locale('pt')->translatedFormat('d \d\e F');
        }

        if ($this->end_date === null || $this->end_date->isSameDay($this->start_date)) {
            return $this->start_date->locale('pt')->translatedFormat('d \d\e F, H:i');
        }

        $start = $this->start_date->locale('pt');
        $end = $this->end_date->locale('pt');

        if ($start->isSameMonth($end) && $start->year === $end->year) {
            return $start->format('d').'–'.$this->nonBreakingDate($end);
        }

        return $this->nonBreakingDate($start).' – '.$this->nonBreakingDate($end);
    }

    private function nonBreakingDate(Carbon $date): string
    {
        return str_replace(' ', "\u{00A0}", $date->translatedFormat('d \d\e F'));
    }
}
