<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('inventory')
            ->where('status', 'IN_USE')
            ->update(['status' => 'RENTED']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE inventory MODIFY status ENUM('AVAILABLE','RENTED','MAINTENANCE','LOST','DISPOSED') NOT NULL DEFAULT 'AVAILABLE'"
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE inventory MODIFY status ENUM('AVAILABLE','IN_USE','MAINTENANCE','LOST','DISPOSED') NOT NULL DEFAULT 'AVAILABLE'"
            );
        }

        DB::table('inventory')
            ->where('status', 'RENTED')
            ->update(['status' => 'IN_USE']);
    }
};
