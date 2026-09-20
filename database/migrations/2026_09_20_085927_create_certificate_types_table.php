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
        Schema::create('certificate_types', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('fee', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('available_to_residents')->default(true);
            $table->boolean('requires_ctc')->default(false);

            $table->string('template_disk', 20)->default('local');
            $table->string('template_path')->nullable();
            $table->string('template_original_name')->nullable();
            $table->json('template_placeholders')->nullable();
            $table->timestamp('template_uploaded_at')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            /**
             * Soft deletes let a retired type still resolve a label for the
             * historical certificates that reference its slug.
             */
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_types');
    }
};
