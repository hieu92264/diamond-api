<?php

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('content')->nullable();
            $table->enum('notification_type', NotificationType::values())
                ->default(NotificationType::GENERAL->value);
            $table->string('related_table', 50)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->dateTime('remind_at')->nullable();
            $table->boolean('is_read')->default(false);
            $table->enum('status', NotificationStatus::values())->default(NotificationStatus::SENT->value);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['related_table', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
