<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_details', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->foreignId('rental_slip_id')->constrained('rental_slips')->cascadeOnDelete();
            $table->foreignId('equipment_prop_id')->constrained('equipment_props')->restrictOnDelete();
            $table->unsignedInteger('rented_quantity')->default(1);
            $table->unsignedInteger('returned_quantity')->default(0);
            $table->unsignedInteger('lost_quantity')->default(0);
            $table->unsignedInteger('damaged_quantity')->default(0);
            $table->decimal('rental_unit_price', 15, 2)->default(0);
            $table->unsignedInteger('rental_days')->default(1);
            $table->decimal('line_rental_amount', 15, 2)->default(0);
            $table->decimal('deposit_amount', 15, 2)->default(0);
            $table->decimal('compensation_amount', 15, 2)->default(0);
            $table->string('condition_on_rent')->nullable();
            $table->string('condition_on_return')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['rental_slip_id', 'equipment_prop_id'], 'rental_slip_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_details');
    }
};