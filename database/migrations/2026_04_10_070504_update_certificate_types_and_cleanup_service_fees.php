<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        // Normalize legacy certificate types before tightening enum values.
        DB::table('certificates')
            ->whereIn('type', ['business_permit', 'building_permit', 'cedula', 'other'])
            ->update(['type' => 'barangay_certification']);

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE certificates MODIFY type ENUM('barangay_clearance','certificate_of_indigency','certificate_of_residency','barangay_certification') NOT NULL");
        } elseif ($driver === 'sqlite') {
            Schema::table('certificates', function ($table): void {
                $table->string('type')->change();
            });
        }

        DB::table('service_fees')
            ->whereIn('service_type', ['business_permit', 'building_permit', 'cedula', 'other'])
            ->delete();

        DB::table('service_fees')->updateOrInsert(
            ['service_type' => 'barangay_certification'],
            [
                'label' => 'Barangay Certification',
                'fee' => 50.00,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        DB::table('certificates')
            ->where('type', 'barangay_certification')
            ->update(['type' => 'other']);

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE certificates MODIFY type ENUM('barangay_clearance','certificate_of_residency','certificate_of_indigency','business_permit','building_permit','cedula','other') NOT NULL");
        } elseif ($driver === 'sqlite') {
            Schema::table('certificates', function ($table): void {
                $table->string('type')->change();
            });
        }

        DB::table('service_fees')
            ->where('service_type', 'barangay_certification')
            ->delete();

        $legacyFees = [
            ['service_type' => 'business_permit', 'label' => 'Business Permit', 'fee' => 200.00],
            ['service_type' => 'building_permit', 'label' => 'Building Permit', 'fee' => 150.00],
            ['service_type' => 'cedula', 'label' => 'Cedula', 'fee' => 50.00],
            ['service_type' => 'other', 'label' => 'Other Certificate', 'fee' => 50.00],
        ];

        foreach ($legacyFees as $fee) {
            DB::table('service_fees')->updateOrInsert(
                ['service_type' => $fee['service_type']],
                [
                    'label' => $fee['label'],
                    'fee' => $fee['fee'],
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }
};
