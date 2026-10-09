<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the awaiting_payment status, so `status` becomes a plain string,
     * plus the issuance details that are now stored instead of re-typed on
     * every download.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE certificates MODIFY status VARCHAR(30) NOT NULL DEFAULT 'pending'");
        } else {
            Schema::table('certificates', function (Blueprint $table): void {
                $table->string('status', 30)->default('pending')->change();
            });
        }

        Schema::table('certificates', function (Blueprint $table): void {
            $table->index('status');
            $table->timestamp('approved_at')->nullable()->after('remarks');
            $table->timestamp('cancelled_at')->nullable()->after('rejection_reason');
            $table->date('issued_at')->nullable()->after('completed_at');
            $table->string('ctc_number', 100)->nullable()->after('or_number');
            $table->string('ctc_place_issued', 200)->nullable()->after('ctc_number');
            $table->date('ctc_date_issued')->nullable()->after('ctc_place_issued');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn(['approved_at', 'cancelled_at', 'issued_at', 'ctc_number', 'ctc_place_issued', 'ctc_date_issued']);
        });

        DB::table('certificates')->where('status', 'awaiting_payment')->update(['status' => 'pending']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE certificates MODIFY status ENUM('pending','processing','ready_for_pickup','completed','rejected','cancelled') NOT NULL DEFAULT 'pending'");
        }
    }
};
