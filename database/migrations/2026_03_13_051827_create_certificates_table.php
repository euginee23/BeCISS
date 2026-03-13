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
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();

            // Certificate Information
            $table->string('certificate_number')->unique();
            $table->enum('type', [
                'barangay_clearance',
                'certificate_of_residency',
                'certificate_of_indigency',
                'business_permit',
                'building_permit',
                'cedula',
                'other',
            ]);
            $table->string('purpose');
            
            // Request Status
            $table->enum('status', [
                'pending',
                'processing',
                'ready_for_pickup',
                'completed',
                'rejected',
                'cancelled',
            ])->default('pending');
            
            // Additional Information
            $table->text('remarks')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Fees
            $table->decimal('fee', 10, 2)->default(0);
            $table->boolean('is_paid')->default(false);
            $table->string('or_number')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
