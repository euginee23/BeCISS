<?php

namespace Database\Seeders;

use App\Models\BarangayOfficial;
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

        // Barangay officials
        $officials = [
            ['name' => 'Hon. Juan Dela Cruz', 'position' => 'Barangay Captain', 'committee' => null, 'sort_order' => 0],
            ['name' => 'Maria Santos', 'position' => 'Barangay Secretary', 'committee' => null, 'sort_order' => 1],
            ['name' => 'Pedro Reyes', 'position' => 'Barangay Treasurer', 'committee' => null, 'sort_order' => 2],
            ['name' => 'Jose Garcia', 'position' => 'Kagawad', 'committee' => 'Peace and Order', 'sort_order' => 3],
            ['name' => 'Ana Ramos', 'position' => 'Kagawad', 'committee' => 'Health and Sanitation', 'sort_order' => 4],
            ['name' => 'Carlo Bautista', 'position' => 'Kagawad', 'committee' => 'Education', 'sort_order' => 5],
            ['name' => 'Luz Castillo', 'position' => 'Kagawad', 'committee' => 'Infrastructure', 'sort_order' => 6],
            ['name' => 'Roberto Flores', 'position' => 'SK Chairperson', 'committee' => 'Youth Affairs', 'sort_order' => 7],
        ];

        foreach ($officials as $official) {
            BarangayOfficial::create(array_merge($official, [
                'term_start' => '2023-01-01',
                'term_end' => '2026-01-01',
                'is_active' => true,
            ]));
        }
    }
}
