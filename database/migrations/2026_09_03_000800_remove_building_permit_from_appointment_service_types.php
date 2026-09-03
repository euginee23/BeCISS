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
     * Building permits are not a barangay service here. `business_permit` stays.
     */
    public function up(): void
    {
        // Must run first: MySQL's MODIFY would truncate these rows to '' instead.
        DB::table('appointments')
            ->where('service_type', 'building_permit')
            ->update(['service_type' => 'other']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE appointments MODIFY service_type ENUM('certificate_request','complaint','mediation','business_permit','health_services','legal_assistance','consultation','other') NOT NULL");
        } else {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->string('service_type')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE appointments MODIFY service_type ENUM('certificate_request','complaint','mediation','business_permit','building_permit','health_services','legal_assistance','consultation','other') NOT NULL");
        } else {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->string('service_type')->change();
            });
        }
    }
};
