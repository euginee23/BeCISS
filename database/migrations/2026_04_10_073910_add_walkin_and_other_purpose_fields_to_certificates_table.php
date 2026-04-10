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
        Schema::table('certificates', function (Blueprint $table) {
            $table->foreignId('resident_id')->nullable()->change();

            $table->boolean('is_walkin')->default(false)->after('resident_id');
            $table->string('walkin_name')->nullable()->after('is_walkin');
            $table->string('walkin_purok')->nullable()->after('walkin_name');
            $table->string('walkin_street')->nullable()->after('walkin_purok');
            $table->string('walkin_house_number')->nullable()->after('walkin_street');
            $table->string('walkin_contact')->nullable()->after('walkin_house_number');

            $table->string('purpose_other')->nullable()->after('purpose');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn([
                'is_walkin',
                'walkin_name',
                'walkin_purok',
                'walkin_street',
                'walkin_house_number',
                'walkin_contact',
                'purpose_other',
            ]);

            $table->foreignId('resident_id')->nullable(false)->change();
        });
    }
};
