<?php

use App\Enums\PaymentMethod;
use App\Enums\RentalPaymentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_payments', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->foreignId('rental_slip_id')->constrained('rental_slips')->cascadeOnDelete();
            $table->enum('payment_type', RentalPaymentType::values());
            $table->dateTime('payment_date');
            $table->decimal('amount', 15, 2);
            $table->enum('payment_method', PaymentMethod::values())->nullable();
            $table->string('reference_no')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['rental_slip_id', 'payment_date']);
            $table->index('payment_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_payments');
    }
};