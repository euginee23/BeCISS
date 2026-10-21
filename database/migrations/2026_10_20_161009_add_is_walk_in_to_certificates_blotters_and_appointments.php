<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables whose records can be filed online by a resident or entered by
     * staff for someone at the counter.
     *
     * @var list<string>
     */
    private array $tables = ['certificates', 'blotters', 'appointments'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->boolean('is_walk_in')->default(false)->after('resident_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('is_walk_in');
            });
        }
    }
};
