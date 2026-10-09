<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The service fees page re-created ₱0 rows for the certificate types after
     * their fees moved to certificate_types. Those rows were never read, so
     * they are dropped to avoid two places that look like they set the fee.
     */
    public function up(): void
    {
        DB::table('service_fees')
            ->whereIn('service_type', DB::table('certificate_types')->select('slug'))
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
