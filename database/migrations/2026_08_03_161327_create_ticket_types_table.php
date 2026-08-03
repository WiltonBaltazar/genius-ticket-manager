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
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('total_quantity');
            $table->unsignedInteger('available_quantity');
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // No separate index('event_id') needed: InnoDB auto-creates one for the FK column above.
        });

        DB::statement(
            'ALTER TABLE ticket_types ADD CONSTRAINT ticket_types_quantity_check '
            .'CHECK (available_quantity >= 0 AND available_quantity <= total_quantity)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_types');
    }
};
