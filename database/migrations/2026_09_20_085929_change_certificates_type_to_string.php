<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen `certificates.type` so admin-defined certificate type slugs fit.
     *
     * A raw MODIFY is used rather than `$table->string('type')->change()`:
     * Laravel re-derives a changed column from introspection, and ENUM
     * round-trips are where the value list gets lost.
     *
     * WARNING: `down()` collapses any admin-created slug back to
     * `barangay_certification`, because the restored ENUM cannot hold it.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE certificates MODIFY type VARCHAR(100) NOT NULL');
        }

        Schema::table('certificates', function (Blueprint $table): void {
            $table->index('type', 'certificates_type_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table): void {
            $table->dropIndex('certificates_type_index');
        });

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $legacyTypes = [
            'barangay_clearance',
            'certificate_of_indigency',
            'certificate_of_residency',
            'barangay_certification',
        ];

        DB::table('certificates')
            ->whereNotIn('type', $legacyTypes)
            ->update(['type' => 'barangay_certification']);

        DB::statement("ALTER TABLE certificates MODIFY type ENUM('barangay_clearance','certificate_of_indigency','certificate_of_residency','barangay_certification') NOT NULL");
    }
};
