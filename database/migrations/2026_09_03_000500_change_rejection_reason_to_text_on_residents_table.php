<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The reject form validates the reason at max:500 but the column was VARCHAR(255),
     * so a long reason was truncated (MySQL, non-strict) or errored.
     */
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table): void {
            $table->text('rejection_reason')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('residents', function (Blueprint $table): void {
            $table->string('rejection_reason')->nullable()->change();
        });
    }
};
