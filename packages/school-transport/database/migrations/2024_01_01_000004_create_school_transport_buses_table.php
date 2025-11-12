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
        Schema::create('school_transport_buses', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('public_id')->unique();
            $table->uuid('company_uuid');
            $table->string('bus_number')->unique();
            $table->string('license_plate')->unique();
            $table->string('make');
            $table->string('model');
            $table->year('year');
            $table->integer('capacity');
            $table->decimal('current_location_latitude', 10, 8)->nullable();
            $table->decimal('current_location_longitude', 11, 8)->nullable();
            $table->enum('status', ['active', 'maintenance', 'out_of_service'])->default('active');
            $table->date('last_maintenance_date')->nullable();
            $table->date('next_maintenance_date')->nullable();
            $table->date('insurance_expiry')->nullable();
            $table->date('registration_expiry')->nullable();
            $table->enum('fuel_type', ['diesel', 'gasoline', 'electric', 'hybrid'])->default('diesel');
            $table->decimal('mileage', 10, 2)->nullable();
            $table->json('features')->nullable();
            $table->uuid('created_by_uuid')->nullable();
            $table->uuid('updated_by_uuid')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_uuid', 'st_buses_company_idx');
            $table->index('bus_number', 'st_buses_number_idx');
            $table->index('license_plate', 'st_buses_plate_idx');
            $table->index('status', 'st_buses_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_transport_buses');
    }
};
