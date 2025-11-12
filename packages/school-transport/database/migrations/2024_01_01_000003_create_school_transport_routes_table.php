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
            $table->uuid('uuid')->primary();
            $table->string('public_id')->unique();
            $table->uuid('company_uuid');
            $table->uuid('school_uuid');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('route_type', ['pickup', 'dropoff', 'round_trip'])->default('round_trip');
            $table->enum('direction', ['to_school', 'from_school'])->default('to_school');
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->integer('estimated_duration_minutes')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->json('weekdays')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('color')->default('#007bff');
            $table->uuid('created_by_uuid')->nullable();
            $table->uuid('updated_by_uuid')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_uuid', 'school_uuid'], 'st_routes_company_school_idx');
            $table->index('is_active', 'st_routes_active_idx');
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
