<?php

use App\Enums\EmployeeType;
use App\Enums\EventStaffRequirementStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_staff_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();
            $table->enum('required_type', EmployeeType::values())->nullable();
            $table->integer('quantity_required')->default(1);
            $table->text('note')->nullable();
            $table->enum('status', EventStaffRequirementStatus::values())
                ->default(EventStaffRequirementStatus::OPEN->value);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_staff_requirements');
    }
};
