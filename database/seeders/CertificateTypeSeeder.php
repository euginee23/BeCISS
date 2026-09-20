<?php

namespace Database\Seeders;

use App\Models\CertificateType;
use Illuminate\Database\Seeder;

class CertificateTypeSeeder extends Seeder
{
    /**
     * The types the barangay issues out of the box. Their slugs match the
     * values already stored on existing certificates.
     *
     * @var list<array{slug: string, name: string, fee: float, sort_order: int}>
     */
    private const array TYPES = [
        ['slug' => 'barangay_clearance', 'name' => 'Barangay Clearance', 'fee' => 50.00, 'sort_order' => 1],
        ['slug' => 'barangay_certification', 'name' => 'Barangay Certification', 'fee' => 50.00, 'sort_order' => 2],
        ['slug' => 'certificate_of_residency', 'name' => 'Certificate of Residency', 'fee' => 30.00, 'sort_order' => 3],
        ['slug' => 'certificate_of_indigency', 'name' => 'Certificate of Indigency', 'fee' => 0.00, 'sort_order' => 4],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::TYPES as $type) {
            CertificateType::updateOrCreate(
                ['slug' => $type['slug']],
                [
                    'name' => $type['name'],
                    'fee' => $type['fee'],
                    'sort_order' => $type['sort_order'],
                    'is_active' => true,
                    'available_to_residents' => true,
                    'requires_ctc' => true,
                ],
            );
        }
    }
}
