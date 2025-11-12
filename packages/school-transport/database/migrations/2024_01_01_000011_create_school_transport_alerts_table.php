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
        Schema::create('school_transport_alerts', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('public_id')->unique();
            $table->uuid('company_uuid');
            $table->uuid('bus_uuid')->nullable();
            $table->uuid('trip_uuid')->nullable();
            $table->uuid('student_uuid')->nullable();
            $table->uuid('driver_uuid')->nullable();
            $table->enum('alert_type', ['delay', 'emergency', 'maintenance', 'behavior', 'location', 'system'])->default('system');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->string('title');
            $table->text('message');
            $table->decimal('location_latitude', 10, 8)->nullable();
            $table->decimal('location_longitude', 11, 8)->nullable();
            $table->timestamp('timestamp');
            $table->boolean('is_acknowledged')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->uuid('acknowledged_by_uuid')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->uuid('created_by_uuid')->nullable();
            $table->uuid('updated_by_uuid')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_uuid', 'bus_uuid'], 'st_alerts_bus_idx');
            $table->index(['company_uuid', 'trip_uuid'], 'st_alerts_trip_idx');
            $table->index(['company_uuid', 'student_uuid'], 'st_alerts_student_idx');
            $table->index(['company_uuid', 'driver_uuid'], 'st_alerts_driver_idx');
            $table->index('alert_type', 'st_alerts_type_idx');
            $table->index('severity', 'st_alerts_severity_idx');
            $table->index('timestamp', 'st_alerts_timestamp_idx');
            $table->index('is_acknowledged', 'st_alerts_acknowledged_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_transport_alerts');
    }
};
