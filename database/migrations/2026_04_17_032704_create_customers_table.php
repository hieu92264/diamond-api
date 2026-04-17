<?php

use App\Enums\CustomerType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->string('code')->unique();
            $table->enum('type', CustomerType::values())
                ->default(CustomerType::INDIVIDUAL->value);
            $table->string('full_name');
            $table->string('company_name')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('tax_code')->nullable();
            $table->string('address')->nullable();
            $table->string('identity_no')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index('full_name');
            $table->index('company_name');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};