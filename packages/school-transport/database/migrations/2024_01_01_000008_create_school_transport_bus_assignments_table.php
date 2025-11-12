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
            $table->uuid('uuid')->primary();
            $table->string('public_id')->unique();
            $table->uuid('company_uuid');
            $table->uuid('bus_uuid');
            $table->uuid('driver_uuid');
            $table->uuid('route_uuid');
            $table->uuid('student_uuid');
            $table->date('assignment_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
            $table->text('notes')->nullable();
            $table->uuid('created_by_uuid')->nullable();
            $table->uuid('updated_by_uuid')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_uuid', 'bus_uuid'], 'st_assign_bus_idx');
            $table->index(['company_uuid', 'driver_uuid'], 'st_assign_driver_idx');
            $table->index(['company_uuid', 'route_uuid'], 'st_assign_route_idx');
            $table->index(['company_uuid', 'student_uuid'], 'st_assign_student_idx');
            $table->index('assignment_date', 'st_assign_date_idx');
            $table->index('status', 'st_assign_status_idx');
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
