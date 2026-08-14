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
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dateTime('sales_start_date')->nullable()->after('available_quantity');
            $table->dateTime('sales_end_date')->nullable()->after('sales_start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn(['sales_start_date', 'sales_end_date']);
        });
    }
};
