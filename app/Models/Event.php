<?php

namespace App\Models;

use App\Enums\EventStatus;
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
}
