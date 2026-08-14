<?php

namespace App\Casts;

use App\Enums\StaffRole;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Unlike Laravel's built-in enum cast (which throws ValueError for a stored
 * value that matches none of the enum's cases), this resolves an unrecognized
 * role to null rather than crashing — required by FR-004: a staff account
 * whose role doesn't match one of the four canonical values must be treated
 * as having gate-operator-equivalent (no) access, not error out.
 *
 * @implements CastsAttributes<StaffRole|null, StaffRole|string|null>
 */
class StaffRoleCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?StaffRole
    {
        if ($value === null) {
            return null;
        }

        return StaffRole::tryFrom($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value instanceof StaffRole) {
            return $value->value;
        }

        return $value;
    }
}
