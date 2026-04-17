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
        Schema::create('borrow_return_slips', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->string('code')->unique();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->string('employee_name');
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->date('borrow_date');
            $table->date('due_date');
            $table->date('return_date')->nullable();
            $table->enum('status', \App\Enums\BorrowStatus::values())
                ->default(\App\Enums\BorrowStatus::PENDING->value);
            $table->foreignId('approved_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('rejected_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('rejected_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['warehouse_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('borrow_return_slips');
    }
};
