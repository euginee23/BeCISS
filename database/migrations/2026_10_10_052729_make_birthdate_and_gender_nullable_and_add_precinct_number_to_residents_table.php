<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Imported household lists carry names and puroks but rarely a birthdate
     * or gender, so both become optional and the record is flagged incomplete
     * until staff fill them in.
     */
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table): void {
            $table->date('birthdate')->nullable()->change();
            $table->enum('gender', ['male', 'female'])->nullable()->change();
            $table->string('precinct_number', 20)->nullable()->after('is_voter');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Restoring NOT NULL fails while incomplete residents exist; complete or
     * remove them first.
     */
    public function down(): void
    {
        Schema::table('residents', function (Blueprint $table): void {
            $table->dropColumn('precinct_number');
            $table->date('birthdate')->nullable(false)->change();
            $table->enum('gender', ['male', 'female'])->nullable(false)->change();
        });
    }
};
