<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The four types that shipped hardcoded, with the fees seeded by
     * ServiceFeeSeeder as a fallback when no service_fees row exists.
     *
     * @var list<array{slug: string, name: string, fee: float, template: string}>
     */
    private const array LEGACY_TYPES = [
        ['slug' => 'barangay_clearance', 'name' => 'Barangay Clearance', 'fee' => 50.00, 'template' => 'BARANGAY_CLEARANCE_TEMPLATE.docx'],
        ['slug' => 'barangay_certification', 'name' => 'Barangay Certification', 'fee' => 50.00, 'template' => 'BARANGAY_CERTIFICATION_TEMPLATE.docx'],
        ['slug' => 'certificate_of_residency', 'name' => 'Certificate of Residency', 'fee' => 30.00, 'template' => 'BARANGAY_RESIDENCY_TEMPLATE.docx'],
        ['slug' => 'certificate_of_indigency', 'name' => 'Certificate of Indigency', 'fee' => 0.00, 'template' => 'BARANGAY_IDIGENCY_TEMPLATE.docx'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        foreach (self::LEGACY_TYPES as $index => $type) {
            $existingFee = DB::table('service_fees')
                ->where('service_type', $type['slug'])
                ->first();

            DB::table('certificate_types')->updateOrInsert(
                ['slug' => $type['slug']],
                [
                    'name' => $existingFee->label ?? $type['name'],
                    'fee' => $existingFee->fee ?? $type['fee'],
                    'is_active' => $existingFee->is_active ?? true,
                    'available_to_residents' => true,
                    /**
                     * The shipped export modal requires CTC details for every
                     * type, and CertificatesTest asserts that. Keep it on for
                     * the legacy four; admin-created types default to off.
                     */
                    'requires_ctc' => true,
                    'template_disk' => 'local',
                    'template_path' => null,
                    'template_original_name' => $type['template'],
                    'sort_order' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        /**
         * Certificate fees now live on certificate_types. The blotter row is
         * deliberately left behind for ServiceFee to keep managing.
         */
        DB::table('service_fees')
            ->whereIn('service_type', array_column(self::LEGACY_TYPES, 'slug'))
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $now = now();

        foreach (DB::table('certificate_types')->get() as $type) {
            DB::table('service_fees')->updateOrInsert(
                ['service_type' => $type->slug],
                [
                    'label' => $type->name,
                    'fee' => $type->fee,
                    'is_active' => $type->is_active,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        DB::table('certificate_types')->delete();
    }
};
