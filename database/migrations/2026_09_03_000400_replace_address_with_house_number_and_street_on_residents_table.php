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
     * The free-text "Home Address" field is replaced by structured parts that pair
     * with the purok dropdown. The Resident model exposes an `address` accessor that
     * recomposes them, so existing render sites keep working.
     */
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table): void {
            $table->string('house_number', 50)->nullable()->after('contact_number');
            $table->string('street')->nullable()->after('house_number');
        });

        // Backfill before the drop. `address` is TEXT and `street` is VARCHAR(255),
        // so anything longer is deliberately truncated rather than silently dropped.
        DB::table('residents')
            ->whereNotNull('address')
            ->orderBy('id')
            ->each(function (object $resident): void {
                DB::table('residents')
                    ->where('id', $resident->id)
                    ->update(['street' => mb_substr((string) $resident->address, 0, 255)]);
            });

        Schema::table('residents', function (Blueprint $table): void {
            $table->dropColumn('address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('residents', function (Blueprint $table): void {
            $table->text('address')->nullable()->after('contact_number');
        });

        DB::table('residents')
            ->orderBy('id')
            ->each(function (object $resident): void {
                DB::table('residents')
                    ->where('id', $resident->id)
                    ->update([
                        'address' => collect([$resident->house_number, $resident->street])
                            ->filter()
                            ->implode(', '),
                    ]);
            });

        Schema::table('residents', function (Blueprint $table): void {
            $table->dropColumn(['house_number', 'street']);
        });
    }
};
