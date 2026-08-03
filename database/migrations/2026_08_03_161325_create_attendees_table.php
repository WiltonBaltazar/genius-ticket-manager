<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Generated column: NULL when soft-deleted, so a soft-deleted row's email
        // no longer collides with the unique index — frees the email for reuse
        // (research.md §3) while still enforcing uniqueness among active rows.
        DB::statement(
            'ALTER TABLE attendees ADD COLUMN email_active VARCHAR(255) '
            .'GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN email END) STORED'
        );
        DB::statement('ALTER TABLE attendees ADD UNIQUE INDEX attendees_email_active_unique (email_active)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendees');
    }
};
