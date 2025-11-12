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
        Schema::create('school_transport_stops', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('public_id')->unique();
            $table->uuid('company_uuid');
            $table->uuid('school_uuid');
            $table->uuid('route_uuid');
            $table->string('name');
            $table->text('address');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->integer('stop_order')->default(0);
            $table->time('estimated_arrival_time')->nullable();
            $table->time('estimated_departure_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by_uuid')->nullable();
            $table->uuid('updated_by_uuid')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_uuid', 'school_uuid', 'route_uuid'], 'st_stops_company_school_route_idx');
            $table->index('stop_order', 'st_stops_order_idx');
            $table->index('is_active', 'st_stops_active_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_transport_stops');
    }
};
