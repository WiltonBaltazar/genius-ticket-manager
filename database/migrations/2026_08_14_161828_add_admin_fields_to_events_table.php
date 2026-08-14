<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->string('hero_image_path')->nullable()->after('venue');
            $table->text('internal_notes')->nullable()->after('status');
        });

        // Defensive backfill: this is pre-launch development with no seeder/code path
        // that has ever left an events row without a slug, but guard anyway in case a
        // pre-existing row is present (research.md §2's precedent for this migration).
        DB::table('events')->whereNull('slug')->orWhere('slug', '')->orderBy('id')->get()->each(function ($event) {
            DB::table('events')->where('id', $event->id)->update([
                'slug' => Str::slug($event->name).'-'.Str::lower(Str::random(6)),
            ]);
        });

        DB::statement('ALTER TABLE events MODIFY slug VARCHAR(255) NOT NULL');
        Schema::table('events', function (Blueprint $table) {
            $table->unique('slug');
        });

        // Pre-existing-row safety check (research.md §2): no seeder/code path from
        // features 001/002 has ever written status = sold_out|completed|cancelled,
        // but guard anyway before the app starts reading `status` through the
        // realigned EventStatus enum (draft/published/closed/archived).
        DB::table('events')->whereIn('status', ['completed', 'cancelled'])->update(['status' => 'closed']);
        DB::table('events')->where('status', 'sold_out')->update(['status' => 'archived']);

        DB::statement('ALTER TABLE events DROP CHECK events_duration_check');
        DB::statement('ALTER TABLE events MODIFY start_date DATETIME NOT NULL');
        DB::statement(
            'ALTER TABLE events ADD CONSTRAINT events_duration_check '
            .'CHECK (end_date >= DATE(start_date) AND end_date <= DATE_ADD(DATE(start_date), INTERVAL 1 DAY))'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE events DROP CHECK events_duration_check');
        DB::statement('ALTER TABLE events MODIFY start_date DATE NOT NULL');
        DB::statement(
            'ALTER TABLE events ADD CONSTRAINT events_duration_check '
            .'CHECK (end_date >= start_date AND end_date <= DATE_ADD(start_date, INTERVAL 1 DAY))'
        );

        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'hero_image_path', 'internal_notes']);
        });
    }
};
