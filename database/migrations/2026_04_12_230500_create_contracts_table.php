<?php

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\SalaryType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();
            $table->string('contract_no', 30)->unique();
            $table->enum('contract_type', ContractType::values())->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('salary_type', SalaryType::values())->nullable();
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->decimal('insurance_salary', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->enum('status', ContractStatus::values())->default(ContractStatus::ACTIVE->value);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
