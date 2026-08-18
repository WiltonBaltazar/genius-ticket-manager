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
        Schema::table('payment_settings', function (Blueprint $table) {
            // Each mobile money method needs only a receiving number and the
            // registered account holder's name (shown to the attendee so they
            // can confirm they're sending to the right person) — unlike bank
            // transfer, there's no separate NIB/branch equivalent.
            $table->string('emola_number')->nullable();
            $table->string('emola_name')->nullable();
            $table->string('mpesa_number')->nullable();
            $table->string('mpesa_name')->nullable();
            $table->string('mkesh_number')->nullable();
            $table->string('mkesh_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_settings', function (Blueprint $table) {
            $table->dropColumn([
                'emola_number', 'emola_name',
                'mpesa_number', 'mpesa_name',
                'mkesh_number', 'mkesh_name',
            ]);
        });
    }
};
