<?php

use App\Enums\EquipmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_props', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('item_category_id')->constrained('item_categories')->restrictOnDelete();
            $table->string('unit')->default('piece');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->enum('status', EquipmentStatus::values())
                ->default(EquipmentStatus::AVAILABLE->value);
            $table->unsignedInteger('quantity_total')->default(0);
            $table->unsignedInteger('quantity_available')->default(0);
            $table->unsignedInteger('minimum_quantity')->default(0);
            $table->date('purchase_date')->nullable();
            $table->decimal('item_value', 15, 2)->nullable();
            $table->decimal('default_rental_price', 15, 2)->default(0);
            $table->decimal('default_deposit_price', 15, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
            $table->index(['item_category_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_props');
    }
};