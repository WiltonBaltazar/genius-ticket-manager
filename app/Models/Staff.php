<?php

namespace App\Models;

use App\Casts\StaffRoleCast;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Staff extends Authenticatable implements FilamentUser
{
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => StaffRoleCast::class,
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Every staff account (any role, including gate_operator) can sign in and
        // reach the dashboard per FR-003; which nav entries/resources they can then
        // use is enforced separately by each resource's Policy, not here.
        return true;
    }

    public function checkedInTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'checked_in_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
