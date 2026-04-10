<?php

namespace Database\Seeders;

use App\Models\ServiceFee;
use Illuminate\Database\Seeder;

class ServiceFeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $fees = [
            ['service_type' => 'barangay_clearance', 'label' => 'Barangay Clearance', 'fee' => 50.00],
            ['service_type' => 'certificate_of_residency', 'label' => 'Certificate of Residency', 'fee' => 30.00],
            ['service_type' => 'certificate_of_indigency', 'label' => 'Certificate of Indigency', 'fee' => 0.00],
            ['service_type' => 'business_permit', 'label' => 'Business Permit', 'fee' => 200.00],
            ['service_type' => 'building_permit', 'label' => 'Building Permit', 'fee' => 150.00],
            ['service_type' => 'cedula', 'label' => 'Cedula', 'fee' => 50.00],
            ['service_type' => 'other', 'label' => 'Other Certificate', 'fee' => 50.00],
            ['service_type' => 'blotter', 'label' => 'Blotter Report', 'fee' => 50.00],
        ];

        foreach ($fees as $fee) {
            ServiceFee::updateOrCreate(
                ['service_type' => $fee['service_type']],
                array_merge($fee, ['is_active' => true]),
            );
        }
    }
}
