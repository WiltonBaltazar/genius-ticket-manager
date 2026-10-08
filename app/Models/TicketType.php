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
        'sales_start_date',
        'sales_end_date',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'total_quantity' => 'integer',
            'available_quantity' => 'integer',
            'version' => 'integer',
            'sales_start_date' => 'datetime',
            'sales_end_date' => 'datetime',
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

    /**
     * Inside its own sales window — each ticket type's window is independent,
     * so Early Bird ending never takes the event's other types off sale.
     * A null bound means open-ended on that side.
     */
    public function scopeOnSale($query)
    {
        return $query
            ->where(function ($query) {
                $query->whereNull('sales_start_date')->orWhere('sales_start_date', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('sales_end_date')->orWhere('sales_end_date', '>=', now());
            });
    }

    public function isOnSale(): bool
    {
        return ($this->sales_start_date === null || $this->sales_start_date->lte(now()))
            && ($this->sales_end_date === null || $this->sales_end_date->gte(now()));
    }

    /**
     * A lot has gone live once its sales window has opened — a null start
     * means it was on sale from creation. Before that, staff may still resize
     * it in either direction.
     */
    public function hasGoneLive(): bool
    {
        return $this->sales_start_date === null || $this->sales_start_date->lte(now());
    }

    /**
     * Seats taken out of the sellable pool (paid or still-pending orders),
     * i.e. the floor total_quantity can never be lowered below.
     */
    public function takenQuantity(): int
    {
        return $this->total_quantity - $this->available_quantity;
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
