<?php

namespace Tests\Feature\Api;

use App\Enums\CustomerType;
use App\Enums\IncidentType;
use App\Enums\InventoryItemStatus;
use App\Enums\ItemCategoryType;
use App\Enums\Position;
use App\Enums\UserRole;
use App\Enums\WarehouseType;
use App\Enums\Work;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\EquipmentProp;
use App\Models\InventoryCondition;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    protected function authenticate(): array
    {
        $user = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $token = auth('api')->login($user);

        return [
            'Authorization' => 'Bearer '.$token,
        ];
    }

    public function test_internal_borrow_flow_creates_incident_and_transactions(): void
    {
        $headers = $this->authenticate();
        $condition = $this->createCondition();
        [$warehouse, $employee, $prop] = $this->createInternalBorrowFixtures();

        $inventoryItem = InventoryItem::query()->create([
            'sku' => 'DC-INT-0001',
            'item_id' => $prop->id,
            'item_type' => ItemCategoryType::EQUIPMENT_PROPS,
            'inventory_condition_id' => $condition->id,
            'warehouse_id' => $warehouse->id,
            'status' => InventoryItemStatus::AVAILABLE,
            'size' => null,
            'is_active' => true,
        ]);

        $slipId = $this->withHeaders($headers)
            ->postJson('/api/internal-borrow-slips', [
                'employee_id' => $employee->id,
                'warehouse_id' => $warehouse->id,
                'borrow_date' => now()->toDateString(),
                'due_date' => now()->addDays(3)->toDateString(),
                'purpose' => 'Test internal borrow',
            ])
            ->assertCreated()
            ->json('slip.id');

        $detailId = $this->withHeaders($headers)
            ->postJson("/api/internal-borrow-slips/{$slipId}/details", [
                'equipment_prop_id' => $prop->id,
                'borrowed_quantity' => 1,
            ])
            ->assertCreated()
            ->json('detail.id');

        $this->withHeaders($headers)
            ->postJson("/api/internal-borrow-slips/{$slipId}/assign-items", [
                'assignments' => [
                    [
                        'detail_id' => $detailId,
                        'inventory_item_ids' => [$inventoryItem->id],
                        'condition_on_borrow_id' => $condition->id,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('details.0.assigned_count', 1);

        $this->withHeaders($headers)
            ->postJson("/api/internal-borrow-slips/{$slipId}/approve")
            ->assertOk()
            ->assertJsonPath('slip.status', 'APPROVED');

        $this->withHeaders($headers)
            ->postJson("/api/internal-borrow-slips/{$slipId}/checkout")
            ->assertOk()
            ->assertJsonPath('slip.status', 'BORROWING');

        $returnResponse = $this->withHeaders($headers)
            ->postJson("/api/internal-borrow-slips/{$slipId}/return", [
                'items' => [
                    [
                        'inventory_item_id' => $inventoryItem->id,
                        'return_action' => 'DAMAGED',
                        'condition_on_return_id' => $condition->id,
                        'compensation_amount' => 120000,
                        'note' => 'Bong duong chi',
                    ],
                ],
            ]);

        $incidentId = $returnResponse
            ->assertOk()
            ->assertJsonPath('slip.status', 'RETURNED')
            ->assertJsonPath('damaged_items', 1)
            ->json('incident_ids.0');

        $this->withHeaders($headers)
            ->getJson("/api/internal-incidents/{$incidentId}")
            ->assertOk()
            ->assertJsonPath('incident_type', IncidentType::DAMAGED->value)
            ->assertJsonPath('detail.slip_id', $slipId);

        $this->withHeaders($headers)
            ->postJson("/api/internal-incidents/{$incidentId}/resolve", [
                'resolution' => 'Da ghi nhan hu hong',
                'compensation_amount' => 120000,
            ])
            ->assertOk()
            ->assertJsonPath('incident.status', 'RESOLVED');

        $this->withHeaders($headers)
            ->postJson("/api/internal-incidents/{$incidentId}/close", [
                'note' => 'Dong su co noi bo',
            ])
            ->assertOk()
            ->assertJsonPath('incident.status', 'CLOSED');

        $this->withHeaders($headers)
            ->getJson('/api/inventory-transactions?inventory_item_id='.$inventoryItem->id)
            ->assertOk()
            ->assertJsonCount(2);

        $this->assertDatabaseHas('inventory', [
            'id' => $inventoryItem->id,
            'status' => InventoryItemStatus::MAINTENANCE->value,
        ]);
    }

    public function test_rental_flow_supports_payments_and_maintenance_cycle(): void
    {
        $headers = $this->authenticate();
        $condition = $this->createCondition();
        [$warehouse, $customer, $costume] = $this->createRentalFixtures();

        $itemOne = InventoryItem::query()->create([
            'sku' => 'TP-RENT-0001',
            'item_id' => $costume->id,
            'item_type' => ItemCategoryType::COSTUME,
            'inventory_condition_id' => $condition->id,
            'warehouse_id' => $warehouse->id,
            'status' => InventoryItemStatus::AVAILABLE,
            'size' => 'M',
            'is_active' => true,
        ]);

        $itemTwo = InventoryItem::query()->create([
            'sku' => 'TP-RENT-0002',
            'item_id' => $costume->id,
            'item_type' => ItemCategoryType::COSTUME,
            'inventory_condition_id' => $condition->id,
            'warehouse_id' => $warehouse->id,
            'status' => InventoryItemStatus::AVAILABLE,
            'size' => 'L',
            'is_active' => true,
        ]);

        $this->withHeaders($headers)
            ->getJson('/api/customers')
            ->assertOk()
            ->assertJsonFragment(['id' => $customer->id]);

        $this->withHeaders($headers)
            ->getJson('/api/customers?type=INDIVIDUAL&include_inactive=false')
            ->assertOk()
            ->assertJsonFragment(['id' => $customer->id]);

        $slipId = $this->withHeaders($headers)
            ->postJson('/api/rental-slips', [
                'customer_id' => $customer->id,
                'warehouse_id' => $warehouse->id,
                'rental_date' => now()->toDateString(),
                'start_date' => now()->toDateString(),
                'due_date' => now()->addDays(2)->toDateString(),
            ])
            ->assertCreated()
            ->json('slip.id');

        $detailId = $this->withHeaders($headers)
            ->postJson("/api/rental-slips/{$slipId}/details", [
                'equipment_prop_id' => $costume->id,
                'rented_quantity' => 2,
                'rental_unit_price' => 100000,
                'rental_days' => 2,
                'deposit_amount' => 50000,
            ])
            ->assertCreated()
            ->json('detail.id');

        $this->withHeaders($headers)
            ->getJson('/api/inventory/available?warehouse_id='.$warehouse->id.'&equipment_prop_id='.$costume->id)
            ->assertOk()
            ->assertJsonCount(2);

        $this->withHeaders($headers)
            ->postJson("/api/rental-slips/{$slipId}/assign-items", [
                'assignments' => [
                    [
                        'detail_id' => $detailId,
                        'inventory_item_ids' => [$itemOne->id, $itemTwo->id],
                        'condition_on_rent_id' => $condition->id,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('details.0.assigned_count', 2);

        $this->withHeaders($headers)->postJson("/api/rental-slips/{$slipId}/submit")->assertOk();
        $this->withHeaders($headers)->postJson("/api/rental-slips/{$slipId}/approve")->assertOk();
        $this->withHeaders($headers)
            ->postJson("/api/rental-slips/{$slipId}/checkout")
            ->assertOk()
            ->assertJsonPath('slip.status', 'ACTIVE');

        $this->withHeaders($headers)
            ->postJson("/api/rental-slips/{$slipId}/payments", [
                'payment_type' => 'DEPOSIT',
                'payment_date' => now()->toDateString(),
                'amount' => 50000,
                'payment_method' => 'CASH',
            ])
            ->assertCreated()
            ->assertJsonPath('summary.paid_amount', '50000.00');

        $returnResponse = $this->withHeaders($headers)
            ->postJson("/api/rental-slips/{$slipId}/return", [
                'items' => [
                    [
                        'inventory_item_id' => $itemOne->id,
                        'return_action' => 'RETURNED',
                        'condition_on_return_id' => $condition->id,
                    ],
                    [
                        'inventory_item_id' => $itemTwo->id,
                        'return_action' => 'DAMAGED',
                        'condition_on_return_id' => $condition->id,
                        'compensation_amount' => 150000,
                        'note' => 'Rach vai',
                    ],
                ],
            ]);

        $incidentId = $returnResponse
            ->assertOk()
            ->assertJsonPath('slip.status', 'RETURNED')
            ->assertJsonPath('damaged_items', 1)
            ->json('incident_ids.0');
        $remainingAmount = (float) $returnResponse->json('remaining_amount');

        $ticketId = $this->withHeaders($headers)
            ->postJson("/api/rental-incidents/{$incidentId}/create-maintenance-ticket", [
                'maintenance_type' => 'REPAIR',
                'reported_date' => now()->toDateString(),
                'expected_return_date' => now()->addDays(2)->toDateString(),
                'vendor' => 'May nhanh',
            ])
            ->assertCreated()
            ->json('maintenance_ticket.id');

        $this->withHeaders($headers)
            ->postJson("/api/maintenance-tickets/{$ticketId}/start", [
                'started_date' => now()->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('maintenance_ticket.status', 'IN_PROGRESS');

        $this->withHeaders($headers)
            ->postJson("/api/maintenance-tickets/{$ticketId}/complete", [
                'return_date' => now()->addDay()->toDateString(),
                'cost' => 80000,
            ])
            ->assertOk()
            ->assertJsonPath('maintenance_ticket.status', 'COMPLETED');

        $this->withHeaders($headers)
            ->postJson("/api/maintenance-tickets/{$ticketId}/return-to-stock", [
                'inventory_condition_id' => $condition->id,
            ])
            ->assertOk()
            ->assertJsonPath('inventory_item.status', InventoryItemStatus::AVAILABLE->value);

        $this->withHeaders($headers)
            ->postJson("/api/rental-incidents/{$incidentId}/resolve", [
                'resolution' => 'Da xu ly va cap nhat boi thuong',
                'compensation_amount' => 150000,
            ])
            ->assertOk()
            ->assertJsonPath('incident.status', 'RESOLVED');

        $this->withHeaders($headers)
            ->postJson("/api/rental-incidents/{$incidentId}/close", [
                'note' => 'Dong su co thue',
            ])
            ->assertOk()
            ->assertJsonPath('incident.status', 'CLOSED');

        $this->withHeaders($headers)
            ->postJson("/api/rental-slips/{$slipId}/payments", [
                'payment_type' => 'RENTAL_PAYMENT',
                'payment_date' => now()->toDateString(),
                'amount' => $remainingAmount,
                'payment_method' => 'BANK_TRANSFER',
            ])
            ->assertCreated()
            ->assertJsonPath('summary.remaining_amount', '0.00');

        $this->withHeaders($headers)
            ->postJson("/api/rental-slips/{$slipId}/close", [
                'note' => 'Dong phieu sau khi da quyet toan',
            ])
            ->assertOk()
            ->assertJsonPath('slip.status', 'CLOSED');

        $this->withHeaders($headers)
            ->getJson("/api/inventory/{$itemTwo->id}/timeline")
            ->assertOk()
            ->assertJsonPath('inventory_item.id', $itemTwo->id)
            ->assertJsonCount(3, 'transactions');
    }

    public function test_slips_can_be_cancelled_before_checkout(): void
    {
        $headers = $this->authenticate();
        $condition = $this->createCondition();
        [$propWarehouse, $employee, $prop] = $this->createInternalBorrowFixtures();
        [$costumeWarehouse, $customer, $costume] = $this->createRentalFixtures();

        InventoryItem::query()->create([
            'sku' => 'DC-CANCEL-0001',
            'item_id' => $prop->id,
            'item_type' => ItemCategoryType::EQUIPMENT_PROPS,
            'inventory_condition_id' => $condition->id,
            'warehouse_id' => $propWarehouse->id,
            'status' => InventoryItemStatus::AVAILABLE,
            'size' => null,
            'is_active' => true,
        ]);

        InventoryItem::query()->create([
            'sku' => 'TP-CANCEL-0001',
            'item_id' => $costume->id,
            'item_type' => ItemCategoryType::COSTUME,
            'inventory_condition_id' => $condition->id,
            'warehouse_id' => $costumeWarehouse->id,
            'status' => InventoryItemStatus::AVAILABLE,
            'size' => 'M',
            'is_active' => true,
        ]);

        $internalSlipId = $this->withHeaders($headers)
            ->postJson('/api/internal-borrow-slips', [
                'employee_id' => $employee->id,
                'warehouse_id' => $propWarehouse->id,
                'borrow_date' => now()->toDateString(),
                'due_date' => now()->addDays(2)->toDateString(),
            ])
            ->assertCreated()
            ->json('slip.id');

        $this->withHeaders($headers)
            ->postJson("/api/internal-borrow-slips/{$internalSlipId}/cancel", [
                'reason' => 'Khong con nhu cau muon',
            ])
            ->assertOk()
            ->assertJsonPath('slip.status', 'CANCELLED');

        $rentalSlipId = $this->withHeaders($headers)
            ->postJson('/api/rental-slips', [
                'customer_id' => $customer->id,
                'warehouse_id' => $costumeWarehouse->id,
                'rental_date' => now()->toDateString(),
                'start_date' => now()->toDateString(),
                'due_date' => now()->addDays(2)->toDateString(),
            ])
            ->assertCreated()
            ->json('slip.id');

        $this->withHeaders($headers)
            ->postJson("/api/rental-slips/{$rentalSlipId}/cancel", [
                'reason' => 'Khach doi lich',
            ])
            ->assertOk()
            ->assertJsonPath('slip.status', 'CANCELLED');
    }

    public function test_maintenance_ticket_can_be_cancelled_and_item_returns_to_available(): void
    {
        $headers = $this->authenticate();
        $condition = $this->createCondition();
        [$warehouse, , $costume] = $this->createRentalFixtures();

        $inventoryItem = InventoryItem::query()->create([
            'sku' => 'TP-MAIN-CANCEL-0001',
            'item_id' => $costume->id,
            'item_type' => ItemCategoryType::COSTUME,
            'inventory_condition_id' => $condition->id,
            'warehouse_id' => $warehouse->id,
            'status' => InventoryItemStatus::AVAILABLE,
            'size' => 'M',
            'is_active' => true,
        ]);

        $ticketId = $this->withHeaders($headers)
            ->postJson('/api/maintenance-tickets', [
                'inventory_item_id' => $inventoryItem->id,
                'maintenance_type' => 'REPAIR',
                'reported_date' => now()->toDateString(),
                'vendor' => 'Test vendor',
            ])
            ->assertCreated()
            ->assertJsonPath('maintenance_ticket.status', 'OPEN')
            ->json('maintenance_ticket.id');

        $this->withHeaders($headers)
            ->postJson("/api/maintenance-tickets/{$ticketId}/cancel", [
                'inventory_condition_id' => $condition->id,
                'note' => 'Khong can sua nua',
            ])
            ->assertOk()
            ->assertJsonPath('maintenance_ticket.status', 'CANCELLED')
            ->assertJsonPath('inventory_item.status', InventoryItemStatus::AVAILABLE->value);
    }

    private function createCondition(): InventoryCondition
    {
        return InventoryCondition::query()->create([
            'code' => 'A',
            'label' => 'Tot',
            'discount_rate' => 0,
            'rentable' => true,
            'disposable' => false,
            'is_active' => true,
        ]);
    }

    private function createInternalBorrowFixtures(): array
    {
        $employee = Employee::query()->create([
            'employee_code' => 'E'.Str::upper(Str::random(5)),
            'full_name' => 'Nhan vien muon noi bo',
            'email' => 'internal@example.com',
            'citizen_id_number' => '123456789999',
            'position' => Position::WAREHOUSE_STAFF,
            'work_status' => Work::ACTIVE,
            'is_active' => true,
        ]);

        $warehouse = Warehouse::query()->create([
            'code' => 'W-PROP-01',
            'name' => 'Kho dao cu test',
            'type' => WarehouseType::EQUIPMENT_PROPS,
            'manager_employee_id' => $employee->id,
            'is_active' => true,
        ]);

        $category = ItemCategory::query()->create([
            'name' => 'Dao cu test',
            'slug' => 'dao-cu-test',
            'type' => ItemCategoryType::EQUIPMENT_PROPS,
            'is_active' => true,
        ]);

        $prop = EquipmentProp::query()->create([
            'name' => 'Micro khong day',
            'slug' => 'micro-khong-day',
            'category_id' => $category->id,
            'unit' => 'CAI',
            'rental_price_per_day' => 50000,
            'is_active' => true,
        ]);

        return [$warehouse, $employee, $prop];
    }

    private function createRentalFixtures(): array
    {
        $employee = Employee::query()->create([
            'employee_code' => 'E'.Str::upper(Str::random(5)),
            'full_name' => 'Quan ly kho trang phuc',
            'email' => 'rental@example.com',
            'citizen_id_number' => '123456789888',
            'position' => Position::WAREHOUSE_MANAGER,
            'work_status' => Work::ACTIVE,
            'is_active' => true,
        ]);

        $warehouse = Warehouse::query()->create([
            'code' => 'W-COSTUME-01',
            'name' => 'Kho trang phuc test',
            'type' => WarehouseType::COSTUME,
            'manager_employee_id' => $employee->id,
            'is_active' => true,
        ]);

        $category = ItemCategory::query()->create([
            'name' => 'Trang phuc test',
            'slug' => 'trang-phuc-test',
            'type' => ItemCategoryType::COSTUME,
            'is_active' => true,
        ]);

        $costume = EquipmentProp::query()->create([
            'name' => 'Ao blazer xanh',
            'slug' => 'ao-blazer-xanh',
            'category_id' => $category->id,
            'unit' => 'BO',
            'sizes' => ['M', 'L'],
            'rental_price_per_day' => 100000,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'code' => 'KH-T-01',
            'type' => CustomerType::INDIVIDUAL,
            'full_name' => 'Khach thue test',
            'phone' => '0909999999',
            'is_active' => true,
        ]);

        return [$warehouse, $customer, $costume];
    }
}
