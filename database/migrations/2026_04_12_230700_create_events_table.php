<?php

use App\Enums\EventStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();
            $table->foreignId('category_id')
                ->constrained('event_categories')
                ->restrictOnDelete();
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('event_code', 20)->unique();
            $table->string('event_name', 150);
            $table->string('event_type', 50)->nullable();
            $table->string('organizer', 150)->nullable();
            $table->string('contact_name', 100)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('location', 255)->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->integer('guest_quantity')->default(0);
            $table->decimal('budget', 15, 2)->default(0);
            $table->decimal('revenue_estimate', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->enum('status', EventStatus::values())->default(EventStatus::DRAFT->value);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
