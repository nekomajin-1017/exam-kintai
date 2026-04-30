<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('break_corrections', function ($table) {
            $table->id();
            $table->foreignId('attendance_correction_id')->constrained()->onDelete('cascade');
            $table->dateTime('break_start_at');
            $table->dateTime('break_end_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('break_corrections');
    }
};
