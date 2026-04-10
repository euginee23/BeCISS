<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('blotters', function (Blueprint $table) {
            // Make resident nullable to support walk-in complainants
            $table->foreignId('resident_id')->nullable()->change();

            // Walk-in complainant fields (used when resident_id is null)
            $table->string('complainant_name')->nullable()->after('resident_id');
            $table->string('complainant_purok')->nullable()->after('complainant_name');
            $table->string('complainant_street')->nullable()->after('complainant_purok');
            $table->string('complainant_house_number')->nullable()->after('complainant_street');
            $table->string('complainant_contact')->nullable()->after('complainant_house_number');

            // Location of incident (free text, not barangay address)
            $table->string('incident_location')->nullable()->after('incident_datetime');

            // Custom incident type when 'other' is selected
            $table->string('incident_type_other')->nullable()->after('incident_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blotters', function (Blueprint $table) {
            $table->dropColumn([
                'complainant_name',
                'complainant_purok',
                'complainant_street',
                'complainant_house_number',
                'complainant_contact',
                'incident_location',
                'incident_type_other',
            ]);

            $table->foreignId('resident_id')->nullable(false)->change();
        });
    }
};
