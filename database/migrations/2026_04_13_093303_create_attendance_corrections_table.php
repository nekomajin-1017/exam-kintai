<?php

use App\Constants\ApprovalStatusCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('attendance_corrections', function ($table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->onDelete('cascade');
            $table->foreignId('request_user_id')->constrained('users')->onDelete('cascade');
            $table->dateTime('requested_check_in_at')->nullable();
            $table->dateTime('requested_check_out_at')->nullable();
            $table->text('reason')->nullable();
            $table->string('approval_status_code', 50)
                ->default(ApprovalStatusCode::PENDING);
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendance_corrections');
    }
};
