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
        Schema::create('school_transport_parent_guardians', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('public_id')->unique();
            $table->uuid('company_uuid');
            $table->uuid('student_uuid');
            $table->uuid('user_uuid')->nullable();
            $table->enum('relationship', ['mother', 'father', 'guardian', 'grandparent', 'other'])->default('guardian');
            $table->boolean('is_primary_contact')->default(false);
            $table->boolean('can_receive_notifications')->default(true);
            $table->boolean('can_pickup_student')->default(false);
            $table->string('phone');
            $table->string('alternate_phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->uuid('created_by_uuid')->nullable();
            $table->uuid('updated_by_uuid')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_uuid', 'student_uuid'], 'st_pg_company_student_idx');
            $table->index('user_uuid', 'st_pg_user_idx');
            $table->index('is_primary_contact', 'st_pg_primary_idx');
            $table->index('can_receive_notifications', 'st_pg_notifications_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_transport_parent_guardians');
    }
};
