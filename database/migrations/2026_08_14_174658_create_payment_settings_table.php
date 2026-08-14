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
        // Singleton settings row — auto-increment id (never referenced by any
        // other table) rather than the app's usual uuid primary key, since this
        // isn't a business entity, just admin-editable payment configuration
        // that previously lived only in .env (WHATSAPP_ORGANIZER_NUMBER,
        // BANK_TRANSFER_*).
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('whatsapp_number')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_branch')->nullable();
            $table->text('bank_transfer_instructions')->nullable();
            $table->timestamps();
        });

        // Seed the single row from existing .env values so upgrading doesn't
        // silently blank out an already-configured WhatsApp number/bank details.
        DB::table('payment_settings')->insert([
            'whatsapp_number' => env('WHATSAPP_ORGANIZER_NUMBER'),
            'bank_account_name' => env('BANK_TRANSFER_ACCOUNT_NAME'),
            'bank_account_number' => env('BANK_TRANSFER_ACCOUNT_NUMBER'),
            'bank_name' => env('BANK_TRANSFER_BANK_NAME'),
            'bank_branch' => env('BANK_TRANSFER_BRANCH'),
            'bank_transfer_instructions' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
    }
};
