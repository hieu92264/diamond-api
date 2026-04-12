<?php

use App\Enums\InventoryTransactionStatus;
use App\Enums\InventoryTransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')
                ->constrained('items')
                ->restrictOnDelete();
            $table->enum('transaction_type', InventoryTransactionType::values())->nullable();
            $table->integer('quantity');
            $table->string('reference_table', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('note')->nullable();
            $table->enum('status', InventoryTransactionStatus::values())
                ->default(InventoryTransactionStatus::POSTED->value);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['reference_table', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
