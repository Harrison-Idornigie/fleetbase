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
        Schema::table('school_transport_tracking_logs', function (Blueprint $table) {
            // ETA and proximity fields
            $table->json('eta_data')->nullable()->comment('Calculated ETA data including provider and destination info');
            $table->json('proximity_alerts')->nullable()->comment('Array of proximity alert events');
            $table->decimal('next_stop_eta_minutes', 8, 2)->nullable()->comment('ETA to next stop in minutes');
            $table->decimal('next_stop_distance_km', 8, 2)->nullable()->comment('Distance to next stop in kilometers');

            // Add indexes for performance
            $table->index(['next_stop_eta_minutes']);
            $table->index(['next_stop_distance_km']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_transport_tracking_logs', function (Blueprint $table) {
            $table->dropIndex(['next_stop_eta_minutes']);
            $table->dropIndex(['next_stop_distance_km']);
            $table->dropColumn([
                'eta_data',
                'proximity_alerts',
                'next_stop_eta_minutes',
                'next_stop_distance_km'
            ]);
        });
    }
};
