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
     * Residents now record WHEN they registered in the barangay; the number of years
     * is derived from that date so it stays correct as time passes, rather than being
     * a typed-in number that goes stale every year.
     */
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table): void {
            $table->date('residency_start_date')->nullable()->after('purok');
        });

        // Backfill before the drop, or the existing figures are lost.
        DB::table('residents')
            ->whereNotNull('years_of_residency')
            ->where('years_of_residency', '>', 0)
            ->orderBy('id')
            ->each(function (object $resident): void {
                DB::table('residents')
                    ->where('id', $resident->id)
                    ->update([
                        'residency_start_date' => now()->subYears((int) $resident->years_of_residency)->startOfYear()->toDateString(),
                    ]);
            });

        Schema::table('residents', function (Blueprint $table): void {
            $table->dropColumn('years_of_residency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('residents', function (Blueprint $table): void {
            $table->unsignedInteger('years_of_residency')->nullable()->after('purok');
        });

        DB::table('residents')
            ->whereNotNull('residency_start_date')
            ->orderBy('id')
            ->each(function (object $resident): void {
                DB::table('residents')
                    ->where('id', $resident->id)
                    ->update([
                        'years_of_residency' => now()->diffInYears($resident->residency_start_date, absolute: true),
                    ]);
            });

        Schema::table('residents', function (Blueprint $table): void {
            $table->dropColumn('residency_start_date');
        });
    }
};
