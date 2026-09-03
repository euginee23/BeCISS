<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Walk-in complainants are being removed, so every blotter must again belong to
     * a registered resident. Existing walk-in rows have no resident to attach to and
     * are deleted; that data is not recoverable by down().
     *
     * `incident_location` and `incident_type_other` are deliberately kept — they
     * arrived in the same migration but are unrelated to walk-ins.
     */
    public function up(): void
    {
        // Must run before the NOT NULL restore below: on MySQL in non-strict mode a
        // surviving NULL is silently coerced to 0, leaving an orphaned foreign key.
        DB::table('blotters')->whereNull('resident_id')->delete();

        Schema::table('blotters', function (Blueprint $table): void {
            $table->dropColumn([
                'complainant_name',
                'complainant_purok',
                'complainant_street',
                'complainant_house_number',
                'complainant_contact',
            ]);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE blotters MODIFY resident_id BIGINT UNSIGNED NOT NULL');
        } else {
            Schema::table('blotters', function (Blueprint $table): void {
                $table->foreignId('resident_id')->nullable(false)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE blotters MODIFY resident_id BIGINT UNSIGNED NULL');
        } else {
            Schema::table('blotters', function (Blueprint $table): void {
                $table->foreignId('resident_id')->nullable()->change();
            });
        }

        Schema::table('blotters', function (Blueprint $table): void {
            $table->string('complainant_name')->nullable()->after('resident_id');
            $table->string('complainant_purok')->nullable()->after('complainant_name');
            $table->string('complainant_street')->nullable()->after('complainant_purok');
            $table->string('complainant_house_number')->nullable()->after('complainant_street');
            $table->string('complainant_contact')->nullable()->after('complainant_house_number');
        });
    }
};
