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
     * Walk-in certificate requests are being removed, so every request must again
     * belong to a registered resident. Existing walk-in rows have no resident to
     * attach to and are deleted; that data is not recoverable by down().
     */
    public function up(): void
    {
        // Must run before the NOT NULL restore below: on MySQL in non-strict mode a
        // surviving NULL is silently coerced to 0, leaving an orphaned foreign key.
        DB::table('certificates')->whereNull('resident_id')->delete();

        Schema::table('certificates', function (Blueprint $table): void {
            $table->dropColumn([
                'is_walkin',
                'walkin_name',
                'walkin_purok',
                'walkin_street',
                'walkin_house_number',
                'walkin_contact',
            ]);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE certificates MODIFY resident_id BIGINT UNSIGNED NOT NULL');
        } else {
            Schema::table('certificates', function (Blueprint $table): void {
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
            DB::statement('ALTER TABLE certificates MODIFY resident_id BIGINT UNSIGNED NULL');
        } else {
            Schema::table('certificates', function (Blueprint $table): void {
                $table->foreignId('resident_id')->nullable()->change();
            });
        }

        Schema::table('certificates', function (Blueprint $table): void {
            $table->boolean('is_walkin')->default(false)->after('resident_id');
            $table->string('walkin_name')->nullable()->after('is_walkin');
            $table->string('walkin_purok')->nullable()->after('walkin_name');
            $table->string('walkin_street')->nullable()->after('walkin_purok');
            $table->string('walkin_house_number')->nullable()->after('walkin_street');
            $table->string('walkin_contact')->nullable()->after('walkin_house_number');
        });
    }
};
