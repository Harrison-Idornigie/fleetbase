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
        Schema::create('school_transport_attendance', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('public_id')->unique()->index();
            $table->uuid('student_uuid');
            $table->uuid('route_uuid');
            $table->uuid('assignment_uuid'); // Reference to bus assignment
            $table->date('date');
            $table->enum('session', ['morning', 'afternoon']); // AM pickup/dropoff or PM
            $table->enum('event_type', ['pickup', 'dropoff', 'no_show', 'early_dismissal']);
            $table->timestamp('scheduled_time')->nullable();
            $table->timestamp('actual_time')->nullable();
            $table->boolean('present')->default(false);
            $table->text('notes')->nullable(); // Driver notes, parent notes, etc.
            $table->uuid('recorded_by_uuid')->nullable(); // Driver or staff UUID
            $table->string('location')->nullable(); // Stop name/description where event occurred
            $table->point('coordinates')->nullable(); // GPS coordinates where recorded
            $table->enum('status', ['scheduled', 'completed', 'missed', 'cancelled'])->default('scheduled');
            $table->json('parent_notification')->nullable(); // Notification sent to parents
            $table->json('meta')->nullable();
            $table->uuid('company_uuid');
            $table->timestamps();

            $table->unique(['student_uuid', 'route_uuid', 'date', 'session', 'event_type']);
            $table->index(['student_uuid', 'date']);
            $table->index(['route_uuid', 'date', 'session']);
            $table->index(['assignment_uuid', 'date']);
            $table->index(['company_uuid', 'date']);
            $table->index(['present', 'event_type']);
            $table->foreign('student_uuid')->references('id')->on('school_transport_students');
            $table->foreign('route_uuid')->references('id')->on('school_transport_routes');
            $table->foreign('assignment_uuid')->references('id')->on('school_transport_bus_assignments');
            $table->foreign('company_uuid')->references('uuid')->on('companies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_transport_attendance');
    }
};
