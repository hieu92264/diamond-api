<?php

use App\Enums\EventItemRequestDetailStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_item_request_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')
                ->constrained('event_item_requests')
                ->cascadeOnDelete();
            $table->foreignId('item_id')
                ->constrained('items')
                ->restrictOnDelete();
            $table->integer('quantity')->default(1);
            $table->string('condition_before', 50)->nullable();
            $table->string('condition_after', 50)->nullable();
            $table->integer('lost_qty')->default(0);
            $table->integer('damaged_qty')->default(0);
            $table->decimal('compensation_amount', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->enum('status', EventItemRequestDetailStatus::values())
                ->default(EventItemRequestDetailStatus::NORMAL->value);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_item_request_details');
    }
};
