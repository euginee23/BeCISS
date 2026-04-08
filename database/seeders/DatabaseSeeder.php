<?php

namespace Database\Seeders;

use App\Models\BarangayProfile;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Admin user
        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        // Staff user
        User::factory()->staff()->create([
            'name' => 'Staff User',
            'email' => 'staff@example.com',
        ]);

        // Resident user (for testing)
        User::factory()->create([
            'name' => 'Test Resident',
            'email' => 'resident@example.com',
        ]);

        // Barangay profile
        BarangayProfile::create([
            'barangay_name' => 'Barangay San Isidro',
            'municipality' => 'Caloocan City',
            'province' => 'Metro Manila',
            'zip_code' => '1400',
            'address' => '123 Barangay Hall Street, Caloocan City',
            'phone' => '02-8123-4567',
            'email' => 'bgy.sanisidro@caloocan.gov.ph',
            'captain_name' => 'Hon. Juan Dela Cruz',
            'secretary_name' => 'Maria Santos',
            'treasurer_name' => 'Pedro Reyes',
            'office_hours' => "Monday - Friday: 8:00 AM - 5:00 PM\nSaturday: 8:00 AM - 12:00 PM",
        ]);

    }
}
