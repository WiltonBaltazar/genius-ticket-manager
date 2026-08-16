<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class Ticket extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'order_item_id',
        'ticket_type_id',
        'event_date',
        'qr_code',
        'status',
        'checked_in_at',
        'checked_in_by',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'status' => TicketStatus::class,
            'checked_in_at' => 'datetime',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function checkedInBy(): BelongsTo
    {
        // Without this, a soft-deleted Staff member would make this relation silently
        // resolve to null, breaking FR-017's "historical actions remain attributable" guarantee.
        return $this->belongsTo(Staff::class, 'checked_in_by')->withoutGlobalScope(SoftDeletingScope::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public function isCheckedIn(): bool
    {
        return $this->status === TicketStatus::CheckedIn;
    }

    public function isVoided(): bool
    {
        return $this->status === TicketStatus::Voided;
    }
}
