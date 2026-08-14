<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class Order extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'attendee_id',
        'status',
        'transaction_hash',
        'payment_method',
        'payment_reference',
        'mpesa_checkout_request_id',
        'proof_of_payment_path',
        'total_amount',
        'ip_address',
        'user_agent',
        'created_by',
        'confirmed_by',
        'refunded_by',
        'confirmed_at',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'total_amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * orders has no event_id column (research.md §5) — an order's event only exists
     * transitively through order_items.ticket_type_id → ticket_types.event_id.
     * Reads the already-loaded orderItems.ticketType.event relation; eager-load that
     * path rather than calling this before orderItems is loaded.
     */
    public function event(): ?Event
    {
        return $this->orderItems->first()?->ticketType?->event;
    }

    /**
     * tickets belongs to order_items, not orders directly (feature 001) — this is
     * a convenience HasManyThrough, not a new FK, for read paths like the
     * checkout order-status page (004-attendee-checkout) that need "every
     * ticket on this order" without walking orderItems manually each time.
     */
    public function tickets(): HasManyThrough
    {
        return $this->hasManyThrough(Ticket::class, OrderItem::class, 'order_id', 'order_item_id');
    }

    public function paymentEvents(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function createdBy(): BelongsTo
    {
        // withoutGlobalScope so a soft-deleted Staff member doesn't silently make this
        // relation resolve to null, per FR-017's "historical actions remain attributable".
        return $this->belongsTo(Staff::class, 'created_by')->withoutGlobalScope(SoftDeletingScope::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'confirmed_by')->withoutGlobalScope(SoftDeletingScope::class);
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'refunded_by')->withoutGlobalScope(SoftDeletingScope::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
