<?php

use App\Enums\PaymentStatus;
use App\Enums\RentalStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_slips', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->string('code')->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->date('rental_date');
            $table->date('start_date');
            $table->date('due_date');
            $table->date('return_date')->nullable();
            $table->decimal('deposit_amount', 15, 2)->default(0);
            $table->decimal('total_rental_amount', 15, 2)->default(0);
            $table->decimal('total_compensation_amount', 15, 2)->default(0);
            $table->decimal('total_discount_amount', 15, 2)->default(0);
            $table->decimal('final_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->enum('payment_status', PaymentStatus::values())
                ->default(PaymentStatus::UNPAID->value);
            $table->enum('status', RentalStatus::values())
                ->default(RentalStatus::DRAFT->value);
            $table->foreignId('approved_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('terms_and_conditions')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['warehouse_id', 'status']);
            $table->index(['payment_status', 'status']);
            $table->index('rental_date');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_slips');
    }
};