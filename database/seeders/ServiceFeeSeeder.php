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
        /**
         * Certificate fees moved to the certificate_types table, which admins
         * manage directly. Only the blotter fee is still a service fee.
         */
        $fees = [
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
