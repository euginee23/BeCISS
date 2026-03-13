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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();

            // Appointment Details
            $table->string('reference_number')->unique();
            $table->enum('service_type', [
                'certificate_request',
                'complaint',
                'mediation',
                'business_permit',
                'building_permit',
                'health_services',
                'legal_assistance',
                'consultation',
                'other',
            ]);
            $table->text('description');
            
            // Schedule
            $table->date('appointment_date');
            $table->time('appointment_time');
            $table->integer('duration_minutes')->default(30);
            
            // Status
            $table->enum('status', [
                'scheduled',
                'confirmed',
                'in_progress',
                'completed',
                'cancelled',
                'no_show',
            ])->default('scheduled');
            
            // Notes
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
