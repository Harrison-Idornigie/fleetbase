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
        Schema::create('school_transport_trips', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('public_id')->unique();
            $table->uuid('company_uuid');
            $table->uuid('bus_uuid');
            $table->uuid('driver_uuid');
            $table->uuid('route_uuid');
            $table->uuid('student_uuid');
            $table->date('trip_date');
            $table->datetime('scheduled_start_time');
            $table->datetime('actual_start_time')->nullable();
            $table->datetime('scheduled_end_time');
            $table->datetime('actual_end_time')->nullable();
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled', 'delayed'])->default('scheduled');
            $table->enum('direction', ['to_school', 'from_school'])->default('to_school');
            $table->decimal('distance_traveled_km', 8, 2)->nullable();
            $table->decimal('fuel_consumed_liters', 6, 2)->nullable();
            $table->string('weather_conditions')->nullable();
            $table->string('traffic_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by_uuid')->nullable();
            $table->uuid('updated_by_uuid')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_uuid', 'bus_uuid'], 'st_trips_bus_idx');
            $table->index(['company_uuid', 'driver_uuid'], 'st_trips_driver_idx');
            $table->index(['company_uuid', 'route_uuid'], 'st_trips_route_idx');
            $table->index(['company_uuid', 'student_uuid'], 'st_trips_student_idx');
            $table->index('trip_date', 'st_trips_date_idx');
            $table->index('status', 'st_trips_status_idx');
            $table->index('direction', 'st_trips_direction_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_transport_trips');
    }
};
