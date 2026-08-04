<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Raw SQL rather than Schema::table(...)->change() to avoid a doctrine/dbal
     * dependency (constitution Principle VI: no unjustified new packages) — the
     * default scaffold's sessions.user_id is BIGINT, sized for the default
     * users.id auto-increment key, but Attendee's key is a UUID (research.md §2).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE sessions MODIFY user_id CHAR(36) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE sessions MODIFY user_id BIGINT UNSIGNED NULL');
    }
};
