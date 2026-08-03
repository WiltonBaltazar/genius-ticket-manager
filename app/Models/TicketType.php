<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketType extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'event_id',
        'name',
        'description',
        'price',
        'total_quantity',
        'available_quantity',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'total_quantity' => 'integer',
            'available_quantity' => 'integer',
            'version' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isSoldOut(): bool
    {
        return $this->available_quantity === 0;
    }

    public function scopeAvailable($query)
    {
        return $query->where('available_quantity', '>', 0);
    }

    protected static function booted(): void
    {
        // Auto-increment the optimistic-locking token on every update, the same way
        // timestamps auto-update — an infrastructure guarantee the schema/model layer
        // provides, not the check-and-retry workflow itself (which stays out of scope
        // here per the "no business logic" framing in spec.md's Assumptions).
        static::updating(function (self $ticketType) {
            $ticketType->version = $ticketType->getOriginal('version') + 1;
        });
    }
}
