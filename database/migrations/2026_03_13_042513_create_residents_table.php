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
        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            
            // Personal Information
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix')->nullable(); // Jr., Sr., III, etc.
            $table->date('birthdate');
            $table->enum('gender', ['male', 'female']);
            $table->enum('civil_status', ['single', 'married', 'widowed', 'separated', 'divorced'])->default('single');
            $table->string('contact_number')->nullable();
            
            // Address Information
            $table->text('address');
            $table->string('purok')->nullable(); // Zone/Purok
            $table->unsignedInteger('years_of_residency')->default(0);
            
            // Additional Information
            $table->string('occupation')->nullable();
            $table->decimal('monthly_income', 10, 2)->nullable();
            $table->boolean('is_voter')->default(false);
            $table->foreignId('household_head_id')->nullable()->constrained('residents')->nullOnDelete();
            
            // Profile
            $table->string('profile_photo_path')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('residents');
    }
};
