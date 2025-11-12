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
        Schema::create('school_transport_tracking_logs', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('public_id')->unique();
            $table->uuid('company_uuid');
            $table->uuid('bus_uuid');
            $table->uuid('trip_uuid')->nullable();
            $table->uuid('driver_uuid');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('speed_kmh', 5, 2)->nullable();
            $table->decimal('heading', 5, 2)->nullable();
            $table->decimal('altitude', 7, 2)->nullable();
            $table->decimal('accuracy', 6, 2)->nullable();
            $table->timestamp('timestamp');
            $table->enum('status', ['moving', 'stopped', 'idle', 'offline'])->default('moving');
            $table->decimal('odometer_reading', 10, 2)->nullable();
            $table->decimal('fuel_level', 5, 2)->nullable();
            $table->enum('engine_status', ['on', 'off', 'idle'])->default('on');
            $table->integer('gps_signal_strength')->nullable();
            $table->uuid('created_by_uuid')->nullable();
            $table->uuid('updated_by_uuid')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_uuid', 'bus_uuid'], 'st_tracking_bus_idx');
            $table->index(['company_uuid', 'trip_uuid'], 'st_tracking_trip_idx');
            $table->index(['company_uuid', 'driver_uuid'], 'st_tracking_driver_idx');
            $table->index('timestamp', 'st_tracking_timestamp_idx');
            $table->index('status', 'st_tracking_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_transport_tracking_logs');
    }
};
