<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_borrow_detail_items', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->foreignId('internal_borrow_detail_id')->constrained('internal_borrow_details')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory')->restrictOnDelete();
            $table->foreignId('condition_on_borrow_id')->nullable()->constrained('inventory_conditions')->nullOnDelete();
            $table->foreignId('condition_on_return_id')->nullable()->constrained('inventory_conditions')->nullOnDelete();
            $table->dateTime('borrowed_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(
                ['internal_borrow_detail_id', 'inventory_item_id'],
                'internal_borrow_detail_inventory_unique'
            );
            $table->index(['inventory_item_id', 'returned_at'], 'internal_borrow_item_returned_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_borrow_detail_items');
    }
};
