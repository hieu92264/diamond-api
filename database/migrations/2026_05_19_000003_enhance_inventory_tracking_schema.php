<?php

use App\Enums\IncidentType;
use App\Enums\InventoryItemStatus;
use App\Enums\MaintenanceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internal_incidents', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')->nullable()->after('internal_borrow_detail_id')
                ->constrained('inventory')->nullOnDelete();
            $table->enum('incident_type', IncidentType::values())
                ->default(IncidentType::DAMAGED->value)
                ->after('incident_description');
            $table->index('incident_type');
        });

        Schema::table('rental_incidents', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')->nullable()->after('rental_detail_id')
                ->constrained('inventory')->nullOnDelete();
            $table->enum('incident_type', IncidentType::values())
                ->default(IncidentType::DAMAGED->value)
                ->after('incident_description');
            $table->index('incident_type');
        });

        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')->nullable()->after('item_id')
                ->constrained('inventory')->nullOnDelete();
            $table->index(['inventory_item_id', 'status'], 'maintenance_tickets_inventory_status_idx');
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')->nullable()->after('equipment_prop_id')
                ->constrained('inventory')->nullOnDelete();
            $table->index(
                ['inventory_item_id', 'transaction_type'],
                'inventory_transactions_item_type_idx'
            );
        });

        $this->upgradeInventoryStatusColumn();
        $this->upgradeMaintenanceTypeColumn();
    }

    public function down(): void
    {
        DB::table('inventory')
            ->where('status', InventoryItemStatus::LOST->value)
            ->update(['status' => InventoryItemStatus::DISPOSED->value]);

        $this->downgradeInventoryStatusColumn();
        $this->downgradeMaintenanceTypeColumn();

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropIndex('inventory_transactions_item_type_idx');
            $table->dropConstrainedForeignId('inventory_item_id');
        });

        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->dropIndex('maintenance_tickets_inventory_status_idx');
            $table->dropConstrainedForeignId('inventory_item_id');
        });

        Schema::table('rental_incidents', function (Blueprint $table) {
            $table->dropIndex(['incident_type']);
            $table->dropColumn('incident_type');
            $table->dropConstrainedForeignId('inventory_item_id');
        });

        Schema::table('internal_incidents', function (Blueprint $table) {
            $table->dropIndex(['incident_type']);
            $table->dropColumn('incident_type');
            $table->dropConstrainedForeignId('inventory_item_id');
        });
    }

    private function upgradeInventoryStatusColumn(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $values = implode("','", InventoryItemStatus::values());
            $default = InventoryItemStatus::AVAILABLE->value;

            DB::statement(
                "ALTER TABLE inventory MODIFY status ENUM('{$values}') NOT NULL DEFAULT '{$default}'"
            );

            return;
        }

        Schema::table('inventory', function (Blueprint $table) {
            $table->string('status', 32)->default(InventoryItemStatus::AVAILABLE->value)->change();
        });
    }

    private function downgradeInventoryStatusColumn(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE inventory MODIFY status ENUM('AVAILABLE','RENTED','MAINTENANCE','DISPOSED') NOT NULL DEFAULT 'AVAILABLE'"
            );

            return;
        }

        Schema::table('inventory', function (Blueprint $table) {
            $table->string('status', 32)->default(InventoryItemStatus::AVAILABLE->value)->change();
        });
    }

    private function upgradeMaintenanceTypeColumn(): void
    {
        DB::table('maintenance_tickets')->whereNotNull('maintenance_type')->update([
            'maintenance_type' => DB::raw('UPPER(maintenance_type)'),
        ]);

        if (DB::getDriverName() === 'mysql') {
            $values = implode("','", MaintenanceType::values());

            DB::statement(
                "ALTER TABLE maintenance_tickets MODIFY maintenance_type ENUM('{$values}') NOT NULL"
            );

            return;
        }

        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->string('maintenance_type', 32)->change();
        });
    }

    private function downgradeMaintenanceTypeColumn(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE maintenance_tickets MODIFY maintenance_type VARCHAR(255) NOT NULL'
            );

            return;
        }

        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->string('maintenance_type')->change();
        });
    }
};
