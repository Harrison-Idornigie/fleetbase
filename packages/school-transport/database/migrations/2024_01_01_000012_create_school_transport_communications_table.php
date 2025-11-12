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
            $table->uuid('uuid')->primary();
            $table->string('public_id')->unique();
            $table->uuid('company_uuid');
            $table->uuid('student_uuid')->nullable();
            $table->uuid('parent_guardian_uuid')->nullable();
            $table->uuid('bus_uuid')->nullable();
            $table->uuid('trip_uuid')->nullable();
            $table->enum('communication_type', ['notification', 'alert', 'update', 'reminder', 'emergency'])->default('notification');
            $table->enum('channel', ['sms', 'email', 'push', 'in_app'])->default('in_app');
            $table->string('subject');
            $table->text('message');
            $table->timestamp('scheduled_send_time')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed'])->default('pending');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->string('recipient_phone')->nullable();
            $table->string('recipient_email')->nullable();
            $table->uuid('sender_uuid')->nullable();
            $table->uuid('created_by_uuid')->nullable();
            $table->uuid('updated_by_uuid')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_uuid', 'student_uuid'], 'st_comm_student_idx');
            $table->index(['company_uuid', 'parent_guardian_uuid'], 'st_comm_parent_idx');
            $table->index(['company_uuid', 'bus_uuid'], 'st_comm_bus_idx');
            $table->index(['company_uuid', 'trip_uuid'], 'st_comm_trip_idx');
            $table->index('communication_type', 'st_comm_type_idx');
            $table->index('channel', 'st_comm_channel_idx');
            $table->index('status', 'st_comm_status_idx');
            $table->index('priority', 'st_comm_priority_idx');
            $table->index('scheduled_send_time', 'st_comm_scheduled_idx');
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
