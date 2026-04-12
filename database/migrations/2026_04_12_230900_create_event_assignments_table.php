<?php

use App\Enums\AttendanceStatus;
use App\Enums\EventAssignmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();
            $table->string('assignment_role', 100)->nullable();
            $table->dateTime('call_time')->nullable();
            $table->dateTime('checkin_time')->nullable();
            $table->dateTime('checkout_time')->nullable();
            $table->enum('attendance_status', AttendanceStatus::values())
                ->default(AttendanceStatus::ASSIGNED->value);
            $table->decimal('performance_fee', 15, 2)->default(0);
            $table->decimal('allowance', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->enum('status', EventAssignmentStatus::values())
                ->default(EventAssignmentStatus::ACTIVE->value);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['event_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_assignments');
    }
};
