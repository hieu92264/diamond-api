<?php

use App\Enums\MaintenanceResultStatus;
use App\Enums\MaintenanceType;
use App\Enums\RecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')
                ->constrained('items')
                ->restrictOnDelete();
            $table->enum('maintenance_type', MaintenanceType::values())->nullable();
            $table->date('sent_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->decimal('cost', 15, 2)->default(0);
            $table->string('vendor', 150)->nullable();
            $table->enum('result_status', MaintenanceResultStatus::values())
                ->default(MaintenanceResultStatus::PENDING->value);
            $table->text('note')->nullable();
            $table->enum('status', RecordStatus::values())->default(RecordStatus::ACTIVE->value);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_maintenances');
    }
};
