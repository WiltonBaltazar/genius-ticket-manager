<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->string('action');
            $table->string('auditable_type');
            $table->uuid('auditable_id');
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            // Append-only: created_at only, no updated_at — enforced at the DB grant level (research.md §6).
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
