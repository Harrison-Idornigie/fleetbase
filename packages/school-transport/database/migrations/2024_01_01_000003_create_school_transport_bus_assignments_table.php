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
        Schema::create('school_transport_bus_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('public_id')->unique()->index();
            $table->uuid('student_uuid');
            $table->uuid('route_uuid');
            $table->integer('stop_sequence')->default(1); // Order of pickup/dropoff
            $table->string('pickup_stop')->nullable(); // Specific stop name/description
            $table->point('pickup_coordinates')->nullable();
            $table->time('pickup_time')->nullable(); // Scheduled pickup time
            $table->string('dropoff_stop')->nullable();
            $table->point('dropoff_coordinates')->nullable();
            $table->time('dropoff_time')->nullable(); // Scheduled dropoff time
            $table->enum('assignment_type', ['regular', 'temporary', 'emergency'])->default('regular');
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->boolean('requires_assistance')->default(false); // Special needs assistance
            $table->text('special_instructions')->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->json('attendance_tracking')->nullable(); // Daily attendance records
            $table->json('meta')->nullable();
            $table->uuid('company_uuid');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_uuid', 'route_uuid']);
            $table->index(['route_uuid', 'stop_sequence']);
            $table->index(['company_uuid', 'status']);
            $table->index(['effective_date', 'end_date']);
            $table->foreign('student_uuid')->references('id')->on('school_transport_students');
            $table->foreign('route_uuid')->references('id')->on('school_transport_routes');
            $table->foreign('company_uuid')->references('uuid')->on('companies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_transport_bus_assignments');
    }
};
