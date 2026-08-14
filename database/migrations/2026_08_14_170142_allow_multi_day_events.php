<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE events DROP CHECK events_duration_check');
        DB::statement(
            'ALTER TABLE events ADD CONSTRAINT events_duration_check '
            .'CHECK (end_date >= DATE(start_date))'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE events DROP CHECK events_duration_check');
        DB::statement(
            'ALTER TABLE events ADD CONSTRAINT events_duration_check '
            .'CHECK (end_date >= DATE(start_date) AND end_date <= DATE_ADD(DATE(start_date), INTERVAL 1 DAY))'
        );
    }
};
