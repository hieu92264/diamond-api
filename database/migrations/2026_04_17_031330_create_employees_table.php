<?php

use App\Enums\Gender;
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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->string('employee_code')->unique();
            $table->string('full_name');
            $table->date('dob')->nullable();
            $table->enum('gender', Gender::values())->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->enum('department', \App\Enums\Department::values());
            $table->enum('position', \App\Enums\Position::values());
            $table->date('hire_date')->nullable();
            $table->enum('work_status', \App\Enums\Work::values());
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['department', 'position']);
            $table->index('work_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
