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
        // Add school transport specific fields to FleetOps vehicles table
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('bus_number')->nullable()->unique();
            $table->integer('capacity')->nullable();
            $table->uuid('route_uuid')->nullable();
            $table->enum('vehicle_type', ['truck', 'van', 'car', 'bus', 'other'])->default('other')->change();

            $table->index('bus_number', 'vehicles_bus_number_idx');
            $table->index('route_uuid', 'vehicles_route_uuid_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex('vehicles_bus_number_idx');
            $table->dropIndex('vehicles_route_uuid_idx');
            $table->dropColumn(['bus_number', 'capacity', 'route_uuid']);
        });
    }
};
