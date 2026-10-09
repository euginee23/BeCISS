<?php

use App\Models\Blotter;
use App\Models\Certificate;
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
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->morphs('payable');
            $table->string('or_number', 50)->index();
            $table->decimal('amount', 10, 2);
            $table->timestamp('paid_at')->index();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payor_name')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        $this->backfill('certificates', Certificate::class);
        $this->backfill('blotters', Blotter::class);
    }

    /**
     * Record a payment for every row already marked paid, dated on release.
     */
    private function backfill(string $table, string $payableType): void
    {
        $residentNames = DB::table('residents')
            ->selectRaw("id, TRIM(first_name || ' ' || last_name) as name");

        if (DB::getDriverName() === 'mysql') {
            $residentNames = DB::table('residents')
                ->selectRaw("id, TRIM(CONCAT(first_name, ' ', last_name)) as name");
        }

        DB::table($table)
            ->leftJoinSub($residentNames, 'payor', 'payor.id', '=', "{$table}.resident_id")
            ->where("{$table}.is_paid", true)
            ->whereNotNull("{$table}.or_number")
            ->orderBy("{$table}.id")
            ->select("{$table}.*", 'payor.name as payor_name')
            ->chunk(500, function ($rows) use ($payableType): void {
                DB::table('payments')->insert($rows->map(fn ($row): array => [
                    'payable_type' => $payableType,
                    'payable_id' => $row->id,
                    'or_number' => $row->or_number,
                    'amount' => $row->fee,
                    'paid_at' => $row->completed_at ?? $row->updated_at,
                    'received_by' => $row->processed_by,
                    'payor_name' => $row->payor_name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
