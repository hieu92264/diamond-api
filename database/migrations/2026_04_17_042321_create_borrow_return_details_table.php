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
        Schema::create('borrow_return_details', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->foreignId('borrow_return_slip_id')->constrained('borrow_return_slips')->cascadeOnDelete();
            $table->foreignId('equipment_prop_id')->constrained('equipment_props')->restrictOnDelete();
            $table->unsignedInteger('borrowed_quantity')->default(1);
            $table->unsignedInteger('returned_quantity')->default(0);
            $table->unsignedInteger('lost_quantity')->default(0);
            $table->unsignedInteger('damaged_quantity')->default(0);
            $table->string('condition_on_borrow')->nullable();
            $table->string('condition_on_return')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['borrow_return_slip_id', 'equipment_prop_id'], 'borrow_return_slip_item_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('borrow_return_details');
    }
};
