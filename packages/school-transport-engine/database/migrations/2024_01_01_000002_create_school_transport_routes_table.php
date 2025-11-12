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
        Schema::create('school_transport_routes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('public_id')->unique()->index();
            $table->string('route_name');
            $table->string('route_number')->nullable();
            $table->text('description')->nullable();
            $table->string('school');
            $table->enum('route_type', ['pickup', 'dropoff', 'both'])->default('both');
            $table->time('start_time'); // Route start time
            $table->time('end_time'); // Route end time
            $table->integer('estimated_duration')->nullable(); // in minutes
            $table->decimal('estimated_distance', 8, 2)->nullable(); // in miles/km
            $table->json('stops'); // Array of stops with coordinates and timing
            $table->json('waypoints')->nullable(); // Optimized route waypoints
            $table->uuid('vehicle_uuid')->nullable(); // Assigned vehicle/bus
            $table->uuid('driver_uuid')->nullable(); // Assigned driver
            $table->integer('capacity')->default(72); // Student capacity
            $table->boolean('wheelchair_accessible')->default(false);
            $table->boolean('is_active')->default(true);
            $table->enum('status', ['draft', 'active', 'suspended', 'archived'])->default('draft');
            $table->json('days_of_week')->default('["monday","tuesday","wednesday","thursday","friday"]'); // Operating days
            $table->date('effective_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('special_instructions')->nullable();
            $table->json('meta')->nullable();
            $table->uuid('company_uuid');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_uuid', 'is_active']);
            $table->index(['school', 'route_type']);
            $table->index(['vehicle_uuid']);
            $table->index(['driver_uuid']);
            $table->index(['status', 'is_active']);
            $table->foreign('company_uuid')->references('uuid')->on('companies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_transport_routes');
    }
};
