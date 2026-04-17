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
        Schema::create('maintenance_tickets', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->string('code')->unique();
            $table->foreignId('item_id')->constrained('equipment_props')->restrictOnDelete();
            $table->string('maintenance_type');
            $table->date('reported_date');
            $table->date('started_date')->nullable();
            $table->date('expected_return_date')->nullable();
            $table->date('return_date')->nullable();
            $table->string('status', 30)->default('OPEN');
            $table->string('vendor')->nullable();
            $table->decimal('cost', 15, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['item_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_tickets');
    }
};
