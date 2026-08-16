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
        // Nullable: NULL means the line/ticket covers the whole event (today's
        // behavior, and the only possibility for single-day events). Set only
        // when a buyer picks one specific day of a multi-day event, at
        // price = ticket_type.price / days_count.
        Schema::table('order_items', function (Blueprint $table) {
            $table->date('event_date')->nullable()->after('ticket_type_id');
        });

        // Denormalized from order_items.event_date, same reasoning as this table's
        // existing denormalized ticket_type_id: fast reads (PDF, future check-in)
        // without joining back through order_items.
        Schema::table('tickets', function (Blueprint $table) {
            $table->date('event_date')->nullable()->after('ticket_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('event_date');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('event_date');
        });
    }
};
