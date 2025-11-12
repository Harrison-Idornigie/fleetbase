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
        Schema::create('school_transport_drivers', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('public_id')->unique();
            $table->uuid('company_uuid');
            $table->uuid('user_uuid')->unique();
            $table->string('driver_license_number')->unique();
            $table->date('license_expiry_date');
            $table->string('license_class')->default('CDL');
            $table->integer('years_of_experience')->default(0);
            $table->string('phone');
            $table->string('emergency_contact_name');
            $table->string('emergency_contact_phone');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->date('background_check_date')->nullable();
            $table->date('training_completion_date')->nullable();
            $table->json('certifications')->nullable();
            $table->uuid('created_by_uuid')->nullable();
            $table->uuid('updated_by_uuid')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_uuid', 'st_drivers_company_idx');
            $table->index('user_uuid', 'st_drivers_user_idx');
            $table->index('driver_license_number', 'st_drivers_license_idx');
            $table->index('status', 'st_drivers_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_transport_drivers');
    }
};
