<?php

use App\Enums\EmployeeStatus;
use App\Enums\EmployeeType;
use App\Enums\Gender;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->unique()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('employee_code', 20)->unique();
            $table->string('full_name', 100);
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', Gender::values())->nullable();
            $table->enum('employee_type', EmployeeType::values())->nullable();
            $table->string('identity_number', 30)->nullable();
            $table->string('address', 255)->nullable();
            $table->date('join_date')->nullable();
            $table->string('emergency_contact_name', 100)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->enum('status', EmployeeStatus::values())->default(EmployeeStatus::ACTIVE->value);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
