<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table): void {
            $table->string('place_of_birth')->nullable()->after('birthdate');
            $table->string('citizenship', 100)->default('Filipino')->after('civil_status');
            $table->string('religion', 100)->nullable()->after('citizenship');
            $table->string('blood_type', 5)->nullable()->after('religion');
            $table->string('email')->nullable()->after('contact_number');

            $table->string('educational_attainment', 50)->nullable()->after('residency_start_date');
            $table->string('employment_status', 50)->nullable()->after('educational_attainment');

            $table->boolean('is_pwd')->default(false)->after('is_voter');
            $table->string('pwd_id_number', 50)->nullable()->after('is_pwd');
            $table->boolean('is_solo_parent')->default(false)->after('pwd_id_number');
            $table->boolean('is_4ps_beneficiary')->default(false)->after('is_solo_parent');
            $table->boolean('is_indigenous')->default(false)->after('is_4ps_beneficiary');
            $table->boolean('is_ofw')->default(false)->after('is_indigenous');
            $table->boolean('is_out_of_school_youth')->default(false)->after('is_ofw');

            $table->index('purok');
            $table->index('status');
        });

        /**
         * Residents added by staff have no account and were never meant to
         * wait for approval; they landed in Pending only because the column
         * defaults to it.
         */
        DB::table('residents')
            ->whereNull('user_id')
            ->where('status', 'pending')
            ->update(['status' => 'approved', 'approved_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('residents', function (Blueprint $table): void {
            $table->dropIndex(['purok']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'place_of_birth', 'citizenship', 'religion', 'blood_type', 'email',
                'educational_attainment', 'employment_status',
                'is_pwd', 'pwd_id_number', 'is_solo_parent', 'is_4ps_beneficiary',
                'is_indigenous', 'is_ofw', 'is_out_of_school_youth',
            ]);
        });
    }
};
