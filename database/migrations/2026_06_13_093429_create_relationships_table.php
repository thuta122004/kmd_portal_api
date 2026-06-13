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
        Schema::create('relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained('students')
                ->onDelete('restrict');
            $table->foreignId('guardian_id')
                ->constrained('guardians')
                ->onDelete('restrict');
            $table->string('relationship_type')
                ->nullable();
            $table->boolean('is_primary_contact')
                ->default(false);
            $table->unique(['student_id', 'guardian_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('relationships');
    }
};
