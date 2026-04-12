<?php

use App\Enums\ItemCategoryType;
use App\Enums\RecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_categories', function (Blueprint $table) {
            $table->id();
            $table->string('category_code', 20)->unique();
            $table->string('name', 100);
            $table->enum('category_type', ItemCategoryType::values())->nullable();
            $table->text('description')->nullable();
            $table->enum('status', RecordStatus::values())->default(RecordStatus::ACTIVE->value);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_categories');
    }
};
