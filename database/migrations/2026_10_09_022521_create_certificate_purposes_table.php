<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The purposes that shipped hardcoded on the Certificate model.
     *
     * @var list<string>
     */
    private const array LEGACY_PURPOSES = [
        'Employment / Job Application',
        'Loan Application',
        'Bank Account Opening',
        'Scholarship / School Enrollment',
        'Travel / Passport Application',
        'Business Permit Application',
        'Government Benefits (SSS, PhilHealth, GSIS)',
        'Medical / Health Services',
        'Legal / Court Purposes',
        'Senior Citizen Benefits',
        'PWD (Person with Disability) Benefits',
        'Voter Registration',
        'Police Clearance / NBI Clearance',
        'TESDA / Training Requirements',
        'Housing / Real Estate Transaction',
        'Insurance Claim',
        'Postal ID / ID Application',
        'Transfer of School / Work Records',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('certificate_purposes', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        $now = now();

        DB::table('certificate_purposes')->insert(array_map(
            fn (string $name, int $index): array => [
                'name' => $name,
                'is_active' => true,
                'sort_order' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            self::LEGACY_PURPOSES,
            array_keys(self::LEGACY_PURPOSES),
        ));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_purposes');
    }
};
