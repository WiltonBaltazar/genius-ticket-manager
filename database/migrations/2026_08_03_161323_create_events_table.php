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
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('venue')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'start_date']);
            $table->index('end_date');
        });

        DB::statement(
            'ALTER TABLE events ADD CONSTRAINT events_duration_check '
            .'CHECK (end_date >= start_date AND end_date <= DATE_ADD(start_date, INTERVAL 1 DAY))'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
