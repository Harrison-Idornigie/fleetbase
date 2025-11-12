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
        Schema::create('school_transport_schools', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('public_id')->unique();
            $table->uuid('company_uuid');
            $table->string('name');
            $table->text('address');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('principal_name')->nullable();
            $table->string('school_district')->nullable();
            $table->enum('school_type', ['elementary', 'middle', 'high', 'k12'])->default('elementary');
            $table->json('grade_levels')->nullable();
            $table->time('operating_hours_start')->nullable();
            $table->time('operating_hours_end')->nullable();
            $table->string('timezone')->default('UTC');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->uuid('created_by_uuid')->nullable();
            $table->uuid('updated_by_uuid')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_uuid', 'st_schools_company_idx');
            $table->index('status', 'st_schools_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_transport_schools');
    }
};
