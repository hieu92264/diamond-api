<?php

use App\Enums\PayrollStatus;
use App\Enums\PayrollType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->string('payroll_period', 20);
            $table->enum('payroll_type', PayrollType::values())->default(PayrollType::MONTHLY->value);
            $table->foreignId('generated_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('generated_at');
            $table->dateTime('approved_at')->nullable();
            $table->text('note')->nullable();
            $table->enum('status', PayrollStatus::values())->default(PayrollStatus::DRAFT->value);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
