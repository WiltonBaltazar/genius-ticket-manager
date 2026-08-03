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
        Schema::create('staff', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->string('role')->nullable();
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Same soft-delete-aware uniqueness pattern as attendees.email_active (research.md §3).
        DB::statement(
            'ALTER TABLE staff ADD COLUMN email_active VARCHAR(255) '
            .'GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN email END) STORED'
        );
        DB::statement('ALTER TABLE staff ADD UNIQUE INDEX staff_email_active_unique (email_active)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
