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
        // Nullable, and default to the order's attendee when unset (Ticket::currentHolderName/
        // currentHolderEmail) — most tickets are never transferred, so this only needs to record
        // a divergence from the order's attendee, not duplicate it for every ticket up front.
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('holder_name')->nullable()->after('event_date');
            $table->string('holder_email')->nullable()->after('holder_name');
            $table->timestamp('transferred_at')->nullable()->after('checked_in_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['holder_name', 'holder_email', 'transferred_at']);
        });
    }
};
