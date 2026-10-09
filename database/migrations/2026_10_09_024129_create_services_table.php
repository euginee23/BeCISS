<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The appointment service types that shipped hardcoded.
     *
     * @var list<array{slug: string, name: string, description: string, website: bool, system: bool}>
     */
    private const array LEGACY_SERVICES = [
        ['slug' => 'certificate_request', 'name' => 'Certificate Request / Pickup', 'description' => 'Pay for and claim barangay certificates.', 'website' => false, 'system' => true],
        ['slug' => 'complaint', 'name' => 'Complaint/Blotter', 'description' => 'File a complaint or blotter report with the barangay.', 'website' => true, 'system' => true],
        ['slug' => 'mediation', 'name' => 'Mediation/Settlement', 'description' => 'Settle disputes through the Lupong Tagapamayapa.', 'website' => true, 'system' => false],
        ['slug' => 'business_permit', 'name' => 'Business Permit', 'description' => 'Barangay business clearance for new and renewing businesses.', 'website' => true, 'system' => false],
        ['slug' => 'health_services', 'name' => 'Health Services', 'description' => 'Consultations and programs at the barangay health center.', 'website' => true, 'system' => false],
        ['slug' => 'legal_assistance', 'name' => 'Legal Assistance', 'description' => 'Referral and guidance on legal concerns.', 'website' => true, 'system' => false],
        ['slug' => 'consultation', 'name' => 'Consultation', 'description' => 'Meet barangay officials about community concerns.', 'website' => true, 'system' => false],
        ['slug' => 'other', 'name' => 'Other', 'description' => 'Any other concern for the barangay.', 'website' => false, 'system' => true],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->decimal('fee', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_bookable')->default(true);
            $table->boolean('show_on_website')->default(true);
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });

        $now = now();

        DB::table('services')->insert(array_map(fn (array $service, int $index): array => [
            'slug' => $service['slug'],
            'name' => $service['name'],
            'description' => $service['description'],
            'is_active' => true,
            'is_bookable' => true,
            'show_on_website' => $service['website'],
            'is_system' => $service['system'],
            'sort_order' => $index + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], self::LEGACY_SERVICES, array_keys(self::LEGACY_SERVICES)));

        /**
         * Admin-created services need slugs the old ENUM cannot hold.
         */
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE appointments MODIFY service_type VARCHAR(100) NOT NULL');
        } else {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->string('service_type', 100)->change();
            });
        }

        Schema::table('appointments', function (Blueprint $table): void {
            $table->index('service_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropIndex(['service_type']);
        });

        DB::table('appointments')
            ->whereNotIn('service_type', array_column(self::LEGACY_SERVICES, 'slug'))
            ->update(['service_type' => 'other']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE appointments MODIFY service_type ENUM('certificate_request','complaint','mediation','business_permit','health_services','legal_assistance','consultation','other') NOT NULL");
        }

        Schema::dropIfExists('services');
    }
};
