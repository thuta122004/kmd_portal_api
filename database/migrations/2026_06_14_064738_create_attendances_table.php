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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('restrict');
            $table->foreignId('timetable_id')
                ->constrained('timetables')
                ->onDelete('restrict');
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'late', 'excused'])
                ->default('present');
            $table->text('remark')
                ->nullable();
            $table->unique(['user_id', 'timetable_id', 'date']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
