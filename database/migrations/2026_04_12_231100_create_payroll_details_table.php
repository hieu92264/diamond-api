<?php

use App\Enums\PaymentStatus;
use App\Enums\RecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')
                ->constrained('payrolls')
                ->cascadeOnDelete();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->restrictOnDelete();
            $table->foreignId('event_assignment_id')
                ->nullable()
                ->constrained('event_assignments')
                ->nullOnDelete();
            $table->integer('work_days')->default(0);
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->decimal('allowance', 15, 2)->default(0);
            $table->decimal('deduction', 15, 2)->default(0);
            $table->decimal('bonus', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->dateTime('paid_at')->nullable();
            $table->enum('payment_status', PaymentStatus::values())->default(PaymentStatus::PENDING->value);
            $table->text('note')->nullable();
            $table->enum('status', RecordStatus::values())->default(RecordStatus::ACTIVE->value);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_details');
    }
};
