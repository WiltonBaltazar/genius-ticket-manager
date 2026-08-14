<?php

namespace Database\Seeders;

use App\Enums\StaffRole;
use App\Models\Staff;
use Illuminate\Database\Seeder;

class StaffSeeder extends Seeder
{
    /**
     * Seed placeholder super_admin/event_manager staff accounts for initial
     * setup/testing (spec.md FR-020). Environment-gated so these
     * non-production credentials can never be created outside local/testing
     * by an ordinary deploy/seed command — pass --force to override.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) && ! $this->command?->option('force')) {
            $this->command?->warn('StaffSeeder skipped: not running in local/testing (pass --force to override).');

            return;
        }

        Staff::firstOrCreate(
            ['email' => 'super.admin@example.test'],
            [
                'name' => 'Super Admin',
                'password' => 'password',
                'role' => StaffRole::SuperAdmin,
                'email_verified_at' => now(),
            ]
        );

        Staff::firstOrCreate(
            ['email' => 'event.manager@example.test'],
            [
                'name' => 'Event Manager',
                'password' => 'password',
                'role' => StaffRole::EventManager,
                'email_verified_at' => now(),
            ]
        );
    }
}
