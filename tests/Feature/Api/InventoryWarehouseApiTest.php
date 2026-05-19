<?php

namespace Tests\Feature\Api;

use App\Enums\ItemCategoryType;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\EquipmentProp;
use App\Models\GalleryImage;
use App\Models\InventoryCondition;
use App\Models\ItemCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryWarehouseApiTest extends TestCase
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

    public function test_admin_can_manage_warehouses_and_inventory(): void
    {
        $headers = $this->authenticate();

        $manager = Employee::query()->create([
            'employee_code' => 'D00001',
            'full_name' => 'Kho Manager',
            'email' => 'warehouse@example.com',
            'citizen_id_number' => '123456789012',
            'position' => 'WAREHOUSE_MANAGER',
            'work_status' => 'ACTIVE',
            'is_active' => true,
        ]);

        $warehouseResponse = $this->withHeaders($headers)
            ->postJson('/api/warehouses', [
                'name' => 'Kho dao cu',
                'type' => ItemCategoryType::EQUIPMENT_PROPS->value,
                'managed_by' => $manager->id,
            ]);

        $warehouseResponse
            ->assertCreated()
            ->assertJsonPath('type', ItemCategoryType::EQUIPMENT_PROPS->value)
            ->assertJsonPath('managed_by', $manager->id);

        $warehouseId = $warehouseResponse->json('id');

        $this->withHeaders($headers)
            ->getJson('/api/warehouses')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $warehouseId);

        $category = ItemCategory::query()->create([
            'name' => 'Hoa mua',
            'slug' => 'hoa-mua',
            'type' => ItemCategoryType::EQUIPMENT_PROPS,
            'is_active' => true,
        ]);

        $prop = EquipmentProp::query()->create([
            'name' => 'Hoa mua cam tay',
            'slug' => 'hoa-mua-cam-tay',
            'category_id' => $category->id,
            'unit' => 'Cap',
            'rental_price_per_day' => 50000,
            'is_active' => true,
        ]);

        $image = GalleryImage::query()->create([
            'category_id' => $category->id,
            'file_name' => 'prop.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'dest' => "/{$category->id}/prop.jpg",
            'is_active' => true,
        ]);

        $prop->galleryImages()->attach($image->id);

        $condition = InventoryCondition::query()->create([
            'code' => 'A',
            'label' => 'Tot',
            'discount_rate' => 0,
            'rentable' => true,
            'is_active' => true,
        ]);

        $conditionB = InventoryCondition::query()->create([
            'code' => 'B',
            'label' => 'Trung binh',
            'discount_rate' => 0.2,
            'rentable' => true,
            'is_active' => true,
        ]);

        $importResponse = $this->withHeaders($headers)
            ->postJson('/api/inventory/import', [
                'item_id' => $prop->id,
                'item_type' => ItemCategoryType::EQUIPMENT_PROPS->value,
                'inventory_condition_id' => $condition->id,
                'warehouse_id' => $warehouseId,
                'quantity' => 2,
            ]);

        $importResponse
            ->assertCreated()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.item_id', $prop->id)
            ->assertJsonPath('data.0.warehouse_id', $warehouseId);

        $sku = $importResponse->json('data.0.sku');

        $this->withHeaders($headers)
            ->getJson('/api/inventory/props?warehouse_id:eq='.$warehouseId)
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.item_type', ItemCategoryType::EQUIPMENT_PROPS->value)
            ->assertJsonPath('0.item.images.0.id', $image->id)
            ->assertJsonPath('0.item.images.0.dest', "/{$category->id}/prop.jpg");

        $this->withHeaders($headers)
            ->patchJson('/api/inventory/condition/'.$sku, [
                'inventory_condition_id' => $conditionB->id,
            ])
            ->assertOk()
            ->assertJsonPath('inventory_condition_id', $conditionB->id);
    }
}
