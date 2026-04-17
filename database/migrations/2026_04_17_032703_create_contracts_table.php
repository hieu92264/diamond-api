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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->string('code')->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('type', \App\Enums\Contract::values());
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('signed_date');
            $table->decimal('salary_agreement', 15, 2)->nullable();
            $table->string('file_path')->nullable();
            $table->enum('status', \App\Enums\ContractStatus::values())
                ->default(\App\Enums\ContractStatus::ACTIVE->value);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
