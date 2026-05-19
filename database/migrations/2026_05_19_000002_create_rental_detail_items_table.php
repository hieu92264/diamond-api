<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_detail_items', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->foreignId('rental_detail_id')->constrained('rental_details')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory')->restrictOnDelete();
            $table->foreignId('condition_on_rent_id')->nullable()->constrained('inventory_conditions')->nullOnDelete();
            $table->foreignId('condition_on_return_id')->nullable()->constrained('inventory_conditions')->nullOnDelete();
            $table->dateTime('rented_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['rental_detail_id', 'inventory_item_id'], 'rental_detail_inventory_unique');
            $table->index(['inventory_item_id', 'returned_at'], 'rental_item_returned_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_detail_items');
    }
};
