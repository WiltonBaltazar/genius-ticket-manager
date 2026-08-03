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
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('attendee_id')->constrained('attendees')->restrictOnDelete();
            $table->string('status', 20);
            $table->string('transaction_hash')->unique();
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->decimal('total_amount', 10, 2);
            $table->string('ip_address', 45);
            $table->string('user_agent', 512)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('staff')->restrictOnDelete();
            $table->foreignUuid('confirmed_by')->nullable()->constrained('staff')->restrictOnDelete();
            $table->foreignUuid('refunded_by')->nullable()->constrained('staff')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['attendee_id', 'status']);
        });

        // Constitution Principle IV mandates (attendee_id, created_at DESC) literally; kept
        // alongside (attendee_id, status) above per plan.md's Complexity Tracking entry.
        DB::statement('CREATE INDEX orders_attendee_id_created_at_desc_index ON orders (attendee_id, created_at DESC)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
