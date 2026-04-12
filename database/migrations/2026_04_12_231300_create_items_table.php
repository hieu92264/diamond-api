<?php

use App\Enums\ItemConditionStatus;
use App\Enums\ItemStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                ->constrained('item_categories')
                ->restrictOnDelete();
            $table->string('item_code', 20)->unique();
            $table->string('item_name', 150);
            $table->string('unit', 20);
            $table->integer('quantity_total')->default(0);
            $table->integer('quantity_available')->default(0);
            $table->enum('condition_status', ItemConditionStatus::values())
                ->default(ItemConditionStatus::GOOD->value);
            $table->string('storage_location', 255)->nullable();
            $table->decimal('rental_price', 15, 2)->default(0);
            $table->enum('status', ItemStatus::values())->default(ItemStatus::AVAILABLE->value);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
