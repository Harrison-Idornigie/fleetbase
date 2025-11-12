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
        Schema::create('school_transport_students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('public_id')->unique()->index();
            $table->string('student_id')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('grade');
            $table->string('school');
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->text('home_address');
            $table->point('home_coordinates')->nullable();
            $table->string('parent_name');
            $table->string('parent_email')->nullable();
            $table->string('parent_phone');
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->json('special_needs')->nullable(); // JSON array of special needs
            $table->json('medical_info')->nullable(); // Medical conditions, allergies, medications
            $table->string('pickup_location')->nullable(); // Custom pickup location if different from home
            $table->point('pickup_coordinates')->nullable();
            $table->string('dropoff_location')->nullable(); // Custom dropoff location if different from school
            $table->point('dropoff_coordinates')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('photo_url')->nullable();
            $table->json('meta')->nullable();
            $table->uuid('company_uuid');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_uuid', 'is_active']);
            $table->index(['student_id', 'company_uuid']);
            $table->index(['school', 'grade']);
            $table->foreign('company_uuid')->references('uuid')->on('companies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_transport_students');
    }
};
