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
        Schema::create('school_transport_communications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('public_id')->unique()->index();
            $table->enum('type', ['notification', 'alert', 'reminder', 'update', 'emergency']);
            $table->string('title');
            $table->text('message');
            $table->json('recipients'); // Array of student/parent UUIDs or 'all'
            $table->json('delivery_channels'); // email, sms, app_notification
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->uuid('route_uuid')->nullable(); // If route-specific
            $table->uuid('student_uuid')->nullable(); // If student-specific
            $table->enum('status', ['draft', 'scheduled', 'sent', 'delivered', 'failed'])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('delivery_status')->nullable(); // Per-recipient delivery status
            $table->json('template_data')->nullable(); // Variables for message templates
            $table->boolean('requires_acknowledgment')->default(false);
            $table->json('acknowledgments')->nullable(); // Parent acknowledgment tracking
            $table->uuid('created_by_uuid'); // User who created the communication
            $table->json('meta')->nullable();
            $table->uuid('company_uuid');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_uuid', 'type']);
            $table->index(['route_uuid', 'status']);
            $table->index(['student_uuid', 'type']);
            $table->index(['status', 'scheduled_at']);
            $table->index(['priority', 'created_at']);
            $table->foreign('route_uuid')->references('id')->on('school_transport_routes');
            $table->foreign('student_uuid')->references('id')->on('school_transport_students');
            $table->foreign('company_uuid')->references('uuid')->on('companies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_transport_communications');
    }
};
