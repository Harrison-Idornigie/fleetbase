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
        Schema::create('school_transport_stop_students', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('stop_uuid');
            $table->uuid('student_uuid');
            $table->timestamps();

            $table->unique(['stop_uuid', 'student_uuid']);
            $table->index('stop_uuid');
            $table->index('student_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_transport_stop_students');
    }
};
