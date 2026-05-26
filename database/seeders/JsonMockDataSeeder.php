<?php

namespace Database\Seeders;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\InventoryItemStatus;
use App\Enums\InventoryReferenceType;
use App\Enums\InventoryTransactionType;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Enums\PaymentStatus;
use App\Enums\RentalPaymentType;
use App\Enums\RentalStatus;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\EquipmentProp;
use App\Models\GalleryImage;
use App\Models\InventoryCondition;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\InternalBorrowDetail;
use App\Models\InternalBorrowDetailItem;
use App\Models\InternalBorrowSlip;
use App\Models\InternalIncident;
use App\Models\Invoice;
use App\Models\ItemCategory;
use App\Models\LoanForm;
use App\Models\LoanFormItem;
use App\Models\MaintenanceTicket;
use App\Models\PenaltyForm;
use App\Models\RentalDetail;
use App\Models\RentalDetailItem;
use App\Models\RentalIncident;
use App\Models\RentalPayment;
use App\Models\RentalSlip;
use App\Models\ReturnForm;
use App\Models\ReturnFormItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class JsonMockDataSeeder extends Seeder
{
    /**
     * @var array<int, int>
     */
    private array $catalogItemIdMap = [];

    /**
     * @var array<string, array<int, int>>
     */
    private array $catalogItemIdMapByType = [];

    public function run(): void
    {
        $path = database_path('seeders/data/costume-rental-db.json');

        if (! file_exists($path)) {
            $this->command?->warn("Seed file not found: {$path}");

            return;
        }

        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        Model::unguarded(function () use ($data): void {
            DB::transaction(function () use ($data): void {
                $this->seedEmployees($data['employees'] ?? []);
                $this->seedUsers($data['users'] ?? []);
                $this->syncEmployeeUsers($data['employees'] ?? []);
                $this->seedWarehouses($data['warehouses'] ?? []);
                $this->seedCategories($data['categories'] ?? []);
                $this->seedInventoryConditions($data['inventory_conditions'] ?? []);
                $this->seedImages($data['images'] ?? []);
                $this->seedCatalogItems($data['costumes'] ?? [], true);
                $this->seedCatalogItems($data['equipment_props'] ?? [], false);
                $this->seedInventory($data['inventory'] ?? []);
                $this->seedCustomers();
                $this->seedFrontendWorkflow($data);
                $this->seedWorkflowSamples();
            });
        });
    }

    private function seedEmployees(array $employees): void
    {
        foreach ($employees as $employee) {
            Employee::query()->updateOrCreate(
                ['id' => $employee['id']],
                [
                    'is_active' => $employee['is_active'] ?? true,
                    'user_id' => null,
                    'employee_code' => $employee['employee_code'],
                    'full_name' => $employee['full_name'],
                    'email' => $employee['email'],
                    'phone' => $employee['phone'] ?? null,
                    'address' => $employee['address'] ?? null,
                    'citizen_id_number' => $employee['citizen_id_number'],
                    'position' => $employee['position'],
                    'hire_date' => $employee['hire_date'] ?? null,
                    'work_status' => $employee['work_status'] ?? 'ACTIVE',
                    'remarks' => $employee['remark'] ?? $employee['remarks'] ?? null,
                    'created_at' => $employee['created_at'] ?? now(),
                    'updated_at' => $employee['updated_at'] ?? now(),
                ]
            );
        }
    }

    private function seedUsers(array $users): void
    {
        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['id' => $user['id']],
                [
                    'username' => $user['username'],
                    'password' => $this->passwordValue($user['password'] ?? 'password'),
                    'role' => $user['role'] ?? 'USER',
                    'employee_id' => $user['employee_id'] ?? null,
                    'is_active' => $user['is_active'] ?? true,
                    'created_at' => $user['created_at'] ?? now(),
                    'updated_at' => $user['updated_at'] ?? now(),
                ]
            );
        }
    }

    private function syncEmployeeUsers(array $employees): void
    {
        foreach ($employees as $employee) {
            if (empty($employee['user_id'])) {
                continue;
            }

            Employee::query()
                ->whereKey($employee['id'])
                ->update(['user_id' => $employee['user_id']]);
        }
    }

    private function seedCategories(array $categories): void
    {
        foreach ($categories as $category) {
            ItemCategory::query()->updateOrCreate(
                ['id' => $category['id']],
                [
                    'name' => $category['name'],
                    'slug' => $category['slug'],
                    'type' => $category['type'],
                    'is_active' => $category['is_active'] ?? true,
                    'created_at' => $category['created_at'] ?? now(),
                    'updated_at' => $category['updated_at'] ?? now(),
                ]
            );
        }
    }

    private function seedWarehouses(array $warehouses): void
    {
        foreach ($warehouses as $warehouse) {
            Warehouse::query()->updateOrCreate(
                ['id' => $warehouse['id']],
                [
                    'is_active' => $warehouse['is_active'] ?? true,
                    'code' => $warehouse['code'] ?? $this->warehouseCode($warehouse),
                    'name' => $warehouse['name'],
                    'type' => $warehouse['type'],
                    'location' => $warehouse['location'] ?? null,
                    'manager_employee_id' => $warehouse['manager_employee_id'] ?? $warehouse['managed_by'] ?? null,
                    'remarks' => $warehouse['remark'] ?? $warehouse['remarks'] ?? null,
                    'created_at' => $warehouse['created_at'] ?? now(),
                    'updated_at' => $warehouse['updated_at'] ?? now(),
                ]
            );
        }
    }

    private function seedInventoryConditions(array $conditions): void
    {
        foreach ($conditions as $condition) {
            InventoryCondition::query()->updateOrCreate(
                ['id' => $condition['id']],
                [
                    'is_active' => $condition['is_active'] ?? true,
                    'code' => $condition['code'],
                    'label' => $condition['label'],
                    'discount_rate' => $condition['discount_rate'] ?? 0,
                    'rentable' => $condition['rentable'] ?? true,
                    'disposable' => $condition['disposable'] ?? false,
                    'badge_color' => $condition['badge_color'] ?? null,
                    'created_at' => $condition['created_at'] ?? now(),
                    'updated_at' => $condition['updated_at'] ?? now(),
                ]
            );
        }
    }

    private function seedImages(array $images): void
    {
        foreach ($images as $image) {
            $storedImage = $this->storeSeedImage($image);

            GalleryImage::query()->updateOrCreate(
                ['id' => $image['id']],
                [
                    'file_name' => $storedImage['file_name'] ?? $image['file_name'],
                    'mime_type' => $storedImage['mime_type'] ?? $image['mime_type'] ?? null,
                    'size' => $storedImage['size'] ?? $image['size'] ?? null,
                    'dest' => $storedImage['dest'] ?? $this->seedImagePublicPath($image),
                    'category_id' => $image['category_id'],
                    'created_by' => $image['created_by'] ?? null,
                    'is_active' => $image['is_active'] ?? true,
                    'created_at' => $image['created_at'] ?? now(),
                    'updated_at' => $image['updated_at'] ?? now(),
                ]
            );
        }
    }

    private function seedCatalogItems(array $items, bool $isCostume): void
    {
        foreach ($items as $item) {
            $catalogItem = EquipmentProp::query()->updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'sku' => $item['sku'] ?? null,
                    'name' => $item['name'],
                    'category_id' => $item['category_id'],
                    'unit' => $item['unit'] ?? ($isCostume ? 'SET' : null),
                    'price' => $item['price'] ?? 0,
                    'color' => $isCostume ? $this->normalizeColor($item['color'] ?? null) : null,
                    'sizes' => $isCostume ? ($item['sizes'] ?? []) : null,
                    'gender' => $isCostume ? ($item['gender'] ?? null) : null,
                    'rental_price_per_day' => $item['rental_price_per_day'] ?? null,
                    'weight_kg' => $isCostume ? null : ($item['weight_kg'] ?? null),
                    'dimensions' => $isCostume ? null : ($item['dimensions'] ?? null),
                    'is_fragile' => $isCostume ? false : ($item['is_fragile'] ?? false),
                    'description' => $item['description'] ?? null,
                    'hashtags' => $item['hashtags'] ?? [],
                    'is_active' => $item['is_active'] ?? true,
                    'created_at' => $item['created_at'] ?? now(),
                    'updated_at' => $item['updated_at'] ?? now(),
                ]
            );

            $imageIds = collect($item['images'] ?? [])
                ->filter(fn ($id) => GalleryImage::query()->whereKey($id)->exists())
                ->values()
                ->all();

            $catalogItem->galleryImages()->sync($imageIds);

            if (isset($item['id'])) {
                $type = $isCostume ? 'COSTUME' : 'EQUIPMENT_PROPS';
                $this->catalogItemIdMapByType[$type][(int) $item['id']] = $catalogItem->id;
                $this->catalogItemIdMap[(int) $item['id']] ??= $catalogItem->id;
            }
        }
    }

    private function seedInventory(array $items): void
    {
        foreach ($items as $item) {
            $resolvedItemId = $this->mappedCatalogIdForType((int) $item['item_id'], (string) $item['item_type'])
                ?? (int) $item['item_id'];

            if (
                ! EquipmentProp::query()->whereKey($resolvedItemId)->exists()
                || ! InventoryCondition::query()->whereKey($item['inventory_condition_id'])->exists()
                || ! Warehouse::query()->whereKey($item['warehouse_id'])->exists()
            ) {
                continue;
            }

            InventoryItem::query()->updateOrCreate(
                ['id' => $item['id']],
                [
                    'is_active' => $item['is_active'] ?? true,
                    'sku' => $item['sku'],
                    'item_id' => $resolvedItemId,
                    'item_type' => $item['item_type'],
                    'inventory_condition_id' => $item['inventory_condition_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'status' => $item['status'] ?? 'AVAILABLE',
                    'size' => $item['size'] ?? null,
                    'created_at' => $item['created_at'] ?? now(),
                    'updated_at' => $item['updated_at'] ?? now(),
                ]
            );
        }
    }

    private function seedCustomers(): void
    {
        $customers = [
            [
                'id' => 1,
                'code' => 'KH0001',
                'type' => 'INDIVIDUAL',
                'full_name' => 'Nguyen Minh Chau',
                'phone' => '0901234567',
                'email' => 'chau@example.com',
                'address' => 'Quan 1, TP.HCM',
                'identity_no' => '079123456789',
                'remarks' => 'Khach le thuong xuyen',
            ],
            [
                'id' => 2,
                'code' => 'KH0002',
                'type' => 'COMPANY',
                'full_name' => 'Cong ty TNHH Anh Sao',
                'company_name' => 'Cong ty TNHH Anh Sao',
                'contact_person' => 'Tran Thu Ha',
                'phone' => '02838445566',
                'email' => 'booking@anhsao.vn',
                'tax_code' => '0312345678',
                'address' => 'Quan 3, TP.HCM',
                'remarks' => 'Khach doanh nghiep',
            ],
            [
                'id' => 3,
                'code' => 'KH0003',
                'type' => 'INDIVIDUAL',
                'full_name' => 'Le Hoang Phuc',
                'phone' => '0911112233',
                'email' => 'phuc@example.com',
                'address' => 'Thu Duc, TP.HCM',
                'identity_no' => '079987654321',
            ],
        ];

        foreach ($customers as $customer) {
            Customer::query()->updateOrCreate(
                ['id' => $customer['id']],
                [
                    'is_active' => true,
                    'code' => $customer['code'],
                    'type' => $customer['type'],
                    'full_name' => $customer['full_name'],
                    'company_name' => $customer['company_name'] ?? null,
                    'contact_person' => $customer['contact_person'] ?? null,
                    'phone' => $customer['phone'] ?? null,
                    'email' => $customer['email'] ?? null,
                    'tax_code' => $customer['tax_code'] ?? null,
                    'address' => $customer['address'] ?? null,
                    'identity_no' => $customer['identity_no'] ?? null,
                    'remarks' => $customer['remarks'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function seedFrontendWorkflow(array $data): void
    {
        foreach ($data['loan_forms'] ?? [] as $row) {
            LoanForm::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'id' => $row['id'] ?? null,
                    'is_active' => $row['is_active'] ?? true,
                    'borrower_name' => $row['borrower_name'],
                    'borrower_phone' => $row['borrower_phone'],
                    'borrower_citizen_id_number' => $row['borrower_citizen_id_number'] ?? null,
                    'borrower_role' => $row['borrower_role'],
                    'method' => $row['method'],
                    'due_date' => $row['due_date'] ?? null,
                    'rental_days' => $row['rental_days'] ?? 0,
                    'total_rental_amount' => $row['total_rental_amount'] ?? 0,
                    'total_item_price_amount' => $row['total_item_price_amount'] ?? 0,
                    'deposit_amount' => $row['deposit_amount'] ?? 0,
                    'created_by' => $this->existingEmployeeId($row['created_by'] ?? null),
                    'updated_by' => $this->existingEmployeeId($row['updated_by'] ?? null),
                    'status' => $row['status'] ?? 'DEPOSIT_PENDING',
                    'remark' => $row['remark'] ?? null,
                    'created_at' => $row['created_at'] ?? now(),
                    'updated_at' => $row['updated_at'] ?? now(),
                ]
            );
        }

        foreach ($data['loan_form_items'] ?? [] as $row) {
            LoanFormItem::query()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'is_active' => $row['is_active'] ?? true,
                    'loan_form_code' => $row['loan_form_code'],
                    'sku' => $row['sku'],
                    'loan_item_name' => $row['loan_item_name'],
                    'rental_price_per_day' => $row['rental_price_per_day'] ?? 0,
                    'item_price' => $row['item_price'] ?? 0,
                    'inventory_id' => $this->existingInventoryId($row['inventory_id'] ?? null),
                    'item_id' => $this->mappedCatalogIdForType((int) ($row['item_id'] ?? 0), (string) ($row['item_type'] ?? '')) ?? null,
                    'item_type' => $row['item_type'] ?? null,
                    'warehouse_id' => $this->existingWarehouseId($row['warehouse_id'] ?? null),
                    'size' => $row['size'] ?? null,
                    'is_returned' => $row['is_returned'] ?? false,
                    'created_by' => $this->existingEmployeeId($row['created_by'] ?? null),
                    'updated_by' => $this->existingEmployeeId($row['updated_by'] ?? null),
                    'remark' => $row['remark'] ?? null,
                    'created_at' => $row['created_at'] ?? now(),
                    'updated_at' => $row['updated_at'] ?? now(),
                ]
            );
        }

        foreach ($data['return_forms'] ?? [] as $row) {
            ReturnForm::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'id' => $row['id'] ?? null,
                    'is_active' => $row['is_active'] ?? true,
                    'loan_form_code' => $row['loan_form_code'],
                    'returnee_name' => $row['returnee_name'],
                    'returnee_phone' => $row['returnee_phone'],
                    'returnee_citizen_id_number' => $row['returnee_citizen_id_number'] ?? null,
                    'remark' => $row['remark'] ?? null,
                    'returnee_role' => $row['returnee_role'] ?? null,
                    'method' => $row['method'] ?? null,
                    'created_by' => $this->existingEmployeeId($row['created_by'] ?? null),
                    'updated_by' => $this->existingEmployeeId($row['updated_by'] ?? null),
                    'status' => $row['status'] ?? 'INSPECTED',
                    'created_at' => $row['created_at'] ?? now(),
                    'updated_at' => $row['updated_at'] ?? now(),
                ]
            );
        }

        foreach ($data['return_form_items'] ?? [] as $row) {
            ReturnFormItem::query()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'is_active' => $row['is_active'] ?? true,
                    'return_form_code' => $row['return_form_code'],
                    'sku' => $row['sku'],
                    'return_item_name' => $row['return_item_name'],
                    'rental_price_per_day' => $row['rental_price_per_day'] ?? 0,
                    'condition_on_return' => $row['condition_on_return'] ?? 'GOOD',
                    'inventory_id' => $this->existingInventoryId($row['inventory_id'] ?? null),
                    'item_id' => $this->mappedCatalogIdForType((int) ($row['item_id'] ?? 0), (string) ($row['item_type'] ?? '')) ?? null,
                    'item_type' => $row['item_type'] ?? null,
                    'warehouse_id' => $this->existingWarehouseId($row['warehouse_id'] ?? null),
                    'size' => $row['size'] ?? null,
                    'created_by' => $this->existingEmployeeId($row['created_by'] ?? null),
                    'updated_by' => $this->existingEmployeeId($row['updated_by'] ?? null),
                    'remark' => $row['remark'] ?? null,
                    'created_at' => $row['created_at'] ?? now(),
                    'updated_at' => $row['updated_at'] ?? now(),
                ]
            );
        }

        foreach ($data['penalty_forms'] ?? [] as $row) {
            PenaltyForm::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'id' => $row['id'] ?? null,
                    'is_active' => $row['is_active'] ?? true,
                    'loan_form_code' => $row['loan_form_code'] ?? null,
                    'return_form_code' => $row['return_form_code'] ?? null,
                    'reason' => $row['reason'],
                    'amount' => $row['amount'] ?? 0,
                    'created_by' => $this->existingEmployeeId($row['created_by'] ?? null),
                    'updated_by' => $this->existingEmployeeId($row['updated_by'] ?? null),
                    'status' => $row['status'] ?? 'ISSUED',
                    'remark' => $row['remark'] ?? null,
                    'created_at' => $row['created_at'] ?? now(),
                    'updated_at' => $row['updated_at'] ?? now(),
                ]
            );
        }

        foreach ($data['invoices'] ?? [] as $row) {
            Invoice::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'id' => $row['id'] ?? null,
                    'is_active' => $row['is_active'] ?? true,
                    'loan_form_code' => $row['loan_form_code'] ?? null,
                    'return_form_code' => $row['return_form_code'] ?? null,
                    'penalty_form_code' => $row['penalty_form_code'] ?? null,
                    'total_amount' => $row['total_amount'] ?? 0,
                    'payment_amount' => $row['payment_amount'] ?? 0,
                    'rental_amount' => $row['rental_amount'] ?? 0,
                    'penalty_amount' => $row['penalty_amount'] ?? 0,
                    'refund_amount' => $row['refund_amount'] ?? 0,
                    'payment_method' => $row['payment_method'] ?? null,
                    'payer_name' => $row['payer_name'] ?? null,
                    'payer_phone' => $row['payer_phone'] ?? null,
                    'payer_citizen_id_number' => $row['payer_citizen_id_number'] ?? null,
                    'paid_at' => $row['paid_at'] ?? null,
                    'note' => $row['note'] ?? null,
                    'created_by' => $this->existingEmployeeId($row['created_by'] ?? null),
                    'updated_by' => $this->existingEmployeeId($row['updated_by'] ?? null),
                    'status' => $row['status'] ?? 'ISSUED',
                    'created_at' => $row['created_at'] ?? now(),
                    'updated_at' => $row['updated_at'] ?? now(),
                ]
            );
        }
    }

    private function seedWorkflowSamples(): void
    {
        $conditionGoodId = (int) InventoryCondition::query()->where('code', 'A')->value('id');
        $createdBy = User::query()->orderBy('id')->value('id');

        if ($conditionGoodId <= 0) {
            return;
        }

        $this->seedInternalBorrowBorrowingSample($conditionGoodId, $createdBy);
        $this->seedInternalBorrowLostSample($conditionGoodId, $createdBy);
        $this->seedRentalActiveSample($conditionGoodId, $createdBy);
        $this->seedRentalDamagedSample($conditionGoodId, $createdBy);
    }

    private function seedInternalBorrowBorrowingSample(int $conditionGoodId, ?int $createdBy): void
    {
        $equipmentPropId = $this->mappedCatalogId(1);

        if ($equipmentPropId === null) {
            return;
        }

        $inventoryItem = InventoryItem::query()
            ->where('warehouse_id', 2)
            ->where('item_id', $equipmentPropId)
            ->orderBy('id')
            ->first();

        if ($inventoryItem === null) {
            return;
        }

        $slip = InternalBorrowSlip::query()->updateOrCreate(
            ['code' => 'PMN-SAMPLE-001'],
            [
                'is_active' => true,
                'employee_id' => 2,
                'employee_name' => Employee::query()->find(2)?->full_name ?? 'Nhan vien kho',
                'warehouse_id' => 2,
                'borrow_date' => now()->subDays(2)->toDateString(),
                'due_date' => now()->addDays(5)->toDateString(),
                'return_date' => null,
                'status' => 'BORROWING',
                'created_by' => $createdBy,
                'purpose' => 'Sample API internal borrow',
                'remarks' => 'Dang muon',
                'created_at' => now()->subDays(2),
                'updated_at' => now(),
            ]
        );

        $detail = InternalBorrowDetail::query()->updateOrCreate(
            [
                'internal_borrow_slip_id' => $slip->id,
                'equipment_prop_id' => $equipmentPropId,
            ],
            [
                'is_active' => true,
                'borrowed_quantity' => 1,
                'returned_quantity' => 0,
                'lost_quantity' => 0,
                'damaged_quantity' => 0,
                'condition_on_borrow' => 'Tot',
                'condition_on_return' => null,
                'remarks' => 'Mau dang muon',
                'created_at' => now()->subDays(2),
                'updated_at' => now(),
            ]
        );

        InternalBorrowDetailItem::query()->updateOrCreate(
            [
                'internal_borrow_detail_id' => $detail->id,
                'inventory_item_id' => $inventoryItem->id,
            ],
            [
                'is_active' => true,
                'condition_on_borrow_id' => $conditionGoodId,
                'condition_on_return_id' => null,
                'borrowed_at' => now()->subDays(2),
                'returned_at' => null,
                'remarks' => 'Item dang muon',
                'created_at' => now()->subDays(2),
                'updated_at' => now(),
            ]
        );

        $this->applyInventoryState(
            $inventoryItem,
            InventoryItemStatus::RENTED->value,
            $conditionGoodId,
            InventoryTransactionType::INTERNAL_BORROW_OUT->value,
            InventoryReferenceType::INTERNAL_BORROW_SLIP->value,
            $slip->id,
            'Seed internal borrow sample'
        );
    }

    private function seedInternalBorrowLostSample(int $conditionGoodId, ?int $createdBy): void
    {
        $equipmentPropId = $this->mappedCatalogId(2);

        if ($equipmentPropId === null) {
            return;
        }

        $inventoryItem = InventoryItem::query()
            ->where('warehouse_id', 2)
            ->where('item_id', $equipmentPropId)
            ->orderBy('id')
            ->first();

        if ($inventoryItem === null) {
            return;
        }

        $slip = InternalBorrowSlip::query()->updateOrCreate(
            ['code' => 'PMN-SAMPLE-002'],
            [
                'is_active' => true,
                'employee_id' => 4,
                'employee_name' => Employee::query()->find(4)?->full_name ?? 'Nhan vien xu ly don',
                'warehouse_id' => 2,
                'borrow_date' => now()->subDays(6)->toDateString(),
                'due_date' => now()->subDays(4)->toDateString(),
                'return_date' => now()->subDays(3)->toDateString(),
                'status' => 'RETURNED',
                'created_by' => $createdBy,
                'purpose' => 'Sample incident internal borrow',
                'remarks' => 'That lac',
                'created_at' => now()->subDays(6),
                'updated_at' => now(),
            ]
        );

        $detail = InternalBorrowDetail::query()->updateOrCreate(
            [
                'internal_borrow_slip_id' => $slip->id,
                'equipment_prop_id' => $equipmentPropId,
            ],
            [
                'is_active' => true,
                'borrowed_quantity' => 1,
                'returned_quantity' => 0,
                'lost_quantity' => 1,
                'damaged_quantity' => 0,
                'condition_on_borrow' => 'Tot',
                'condition_on_return' => null,
                'remarks' => 'Mat dao cu',
                'created_at' => now()->subDays(6),
                'updated_at' => now(),
            ]
        );

        InternalBorrowDetailItem::query()->updateOrCreate(
            [
                'internal_borrow_detail_id' => $detail->id,
                'inventory_item_id' => $inventoryItem->id,
            ],
            [
                'is_active' => true,
                'condition_on_borrow_id' => $conditionGoodId,
                'condition_on_return_id' => null,
                'borrowed_at' => now()->subDays(6),
                'returned_at' => now()->subDays(3),
                'remarks' => 'Seed lost item',
                'created_at' => now()->subDays(6),
                'updated_at' => now(),
            ]
        );

        $incident = InternalIncident::query()->updateOrCreate(
            ['code' => 'SCNB-SAMPLE-001'],
            [
                'is_active' => true,
                'internal_borrow_detail_id' => $detail->id,
                'inventory_item_id' => $inventoryItem->id,
                'incident_description' => 'That lac trong qua trinh muon',
                'incident_type' => IncidentType::LOST->value,
                'status' => IncidentStatus::OPEN->value,
                'compensation_amount' => 150000,
                'resolved_by_id' => null,
                'resolved_at' => null,
                'resolution' => null,
                'created_at' => now()->subDays(3),
                'updated_at' => now(),
            ]
        );

        $this->applyInventoryState(
            $inventoryItem,
            InventoryItemStatus::RENTED->value,
            $conditionGoodId,
            InventoryTransactionType::INTERNAL_BORROW_OUT->value,
            InventoryReferenceType::INTERNAL_BORROW_SLIP->value,
            $slip->id,
            'Seed internal borrow lost sample out'
        );

        $this->applyInventoryState(
            $inventoryItem,
            InventoryItemStatus::LOST->value,
            $conditionGoodId,
            InventoryTransactionType::LOST_WRITE_OFF->value,
            InventoryReferenceType::INTERNAL_INCIDENT->value,
            $incident->id,
            'Seed internal borrow lost sample'
        );
    }

    private function seedRentalActiveSample(int $conditionGoodId, ?int $createdBy): void
    {
        $equipmentPropId = $this->mappedCatalogId(6);

        if ($equipmentPropId === null) {
            return;
        }

        $inventoryItems = InventoryItem::query()
            ->where('warehouse_id', 1)
            ->where('item_id', $equipmentPropId)
            ->orderBy('id')
            ->take(2)
            ->get();

        if ($inventoryItems->count() < 2) {
            return;
        }

        $slip = RentalSlip::query()->updateOrCreate(
            ['code' => 'PTH-SAMPLE-001'],
            [
                'is_active' => true,
                'customer_id' => 1,
                'customer_name' => Customer::query()->find(1)?->full_name ?? 'Khach hang 1',
                'customer_phone' => Customer::query()->find(1)?->phone,
                'warehouse_id' => 1,
                'rental_date' => now()->subDay()->toDateString(),
                'start_date' => now()->subDay()->toDateString(),
                'due_date' => now()->addDays(4)->toDateString(),
                'return_date' => null,
                'deposit_amount' => 300000,
                'total_rental_amount' => 1080000,
                'total_compensation_amount' => 0,
                'total_discount_amount' => 0,
                'final_amount' => 1080000,
                'paid_amount' => 300000,
                'remaining_amount' => 780000,
                'payment_status' => PaymentStatus::PARTIALLY_PAID->value,
                'status' => RentalStatus::ACTIVE->value,
                'approved_user_id' => $createdBy,
                'approved_at' => now()->subDay(),
                'created_by' => $createdBy,
                'terms_and_conditions' => 'Seed sample active rental',
                'remarks' => 'Dang cho tra',
                'created_at' => now()->subDay(),
                'updated_at' => now(),
            ]
        );

        $detail = RentalDetail::query()->updateOrCreate(
            [
                'rental_slip_id' => $slip->id,
                'equipment_prop_id' => $equipmentPropId,
            ],
            [
                'is_active' => true,
                'rented_quantity' => 2,
                'returned_quantity' => 0,
                'lost_quantity' => 0,
                'damaged_quantity' => 0,
                'rental_unit_price' => 180000,
                'rental_days' => 3,
                'line_rental_amount' => 1080000,
                'deposit_amount' => 300000,
                'compensation_amount' => 0,
                'condition_on_rent' => 'Tot',
                'condition_on_return' => null,
                'remarks' => 'Mau dang thue',
                'created_at' => now()->subDay(),
                'updated_at' => now(),
            ]
        );

        foreach ($inventoryItems as $inventoryItem) {
            RentalDetailItem::query()->updateOrCreate(
                [
                    'rental_detail_id' => $detail->id,
                    'inventory_item_id' => $inventoryItem->id,
                ],
                [
                    'is_active' => true,
                    'condition_on_rent_id' => $conditionGoodId,
                    'condition_on_return_id' => null,
                    'rented_at' => now()->subDay(),
                    'returned_at' => null,
                    'remarks' => 'Seed active rental item',
                    'created_at' => now()->subDay(),
                    'updated_at' => now(),
                ]
            );

            $this->applyInventoryState(
                $inventoryItem,
                InventoryItemStatus::RENTED->value,
                $conditionGoodId,
                InventoryTransactionType::RENTAL_OUT->value,
                InventoryReferenceType::RENTAL_SLIP->value,
                $slip->id,
                'Seed active rental sample'
            );
        }

        RentalPayment::query()->updateOrCreate(
            [
                'rental_slip_id' => $slip->id,
                'payment_type' => RentalPaymentType::DEPOSIT->value,
                'reference_no' => 'SEED-DEP-001',
            ],
            [
                'is_active' => true,
                'payment_date' => now()->subDay(),
                'amount' => 300000,
                'payment_method' => 'CASH',
                'received_by' => $createdBy,
                'note' => 'Tien coc mau',
                'created_at' => now()->subDay(),
                'updated_at' => now(),
            ]
        );
    }

    private function seedRentalDamagedSample(int $conditionGoodId, ?int $createdBy): void
    {
        $equipmentPropId = $this->mappedCatalogId(7);

        if ($equipmentPropId === null) {
            return;
        }

        $inventoryItems = InventoryItem::query()
            ->where('warehouse_id', 1)
            ->where('item_id', $equipmentPropId)
            ->orderBy('id')
            ->take(2)
            ->get()
            ->values();

        if ($inventoryItems->count() < 2) {
            return;
        }

        $returnedItem = $inventoryItems[0];
        $damagedItem = $inventoryItems[1];

        $slip = RentalSlip::query()->updateOrCreate(
            ['code' => 'PTH-SAMPLE-002'],
            [
                'is_active' => true,
                'customer_id' => 2,
                'customer_name' => Customer::query()->find(2)?->full_name ?? 'Khach hang 2',
                'customer_phone' => Customer::query()->find(2)?->phone,
                'warehouse_id' => 1,
                'rental_date' => now()->subDays(7)->toDateString(),
                'start_date' => now()->subDays(7)->toDateString(),
                'due_date' => now()->subDays(3)->toDateString(),
                'return_date' => now()->subDays(2)->toDateString(),
                'deposit_amount' => 100000,
                'total_rental_amount' => 480000,
                'total_compensation_amount' => 250000,
                'total_discount_amount' => 0,
                'final_amount' => 730000,
                'paid_amount' => 300000,
                'remaining_amount' => 430000,
                'payment_status' => PaymentStatus::PARTIALLY_PAID->value,
                'status' => RentalStatus::RETURNED->value,
                'approved_user_id' => $createdBy,
                'approved_at' => now()->subDays(7),
                'created_by' => $createdBy,
                'terms_and_conditions' => 'Seed sample damaged rental',
                'remarks' => 'Da tra, con xu ly hu hong',
                'created_at' => now()->subDays(7),
                'updated_at' => now(),
            ]
        );

        $detail = RentalDetail::query()->updateOrCreate(
            [
                'rental_slip_id' => $slip->id,
                'equipment_prop_id' => $equipmentPropId,
            ],
            [
                'is_active' => true,
                'rented_quantity' => 2,
                'returned_quantity' => 1,
                'lost_quantity' => 0,
                'damaged_quantity' => 1,
                'rental_unit_price' => 120000,
                'rental_days' => 2,
                'line_rental_amount' => 480000,
                'deposit_amount' => 100000,
                'compensation_amount' => 250000,
                'condition_on_rent' => 'Tot',
                'condition_on_return' => 'Rach nhe',
                'remarks' => 'Mau co hu hong',
                'created_at' => now()->subDays(7),
                'updated_at' => now(),
            ]
        );

        RentalDetailItem::query()->updateOrCreate(
            [
                'rental_detail_id' => $detail->id,
                'inventory_item_id' => $returnedItem->id,
            ],
            [
                'is_active' => true,
                'condition_on_rent_id' => $conditionGoodId,
                'condition_on_return_id' => $conditionGoodId,
                'rented_at' => now()->subDays(7),
                'returned_at' => now()->subDays(2),
                'remarks' => 'Tra binh thuong',
                'created_at' => now()->subDays(7),
                'updated_at' => now(),
            ]
        );

        RentalDetailItem::query()->updateOrCreate(
            [
                'rental_detail_id' => $detail->id,
                'inventory_item_id' => $damagedItem->id,
            ],
            [
                'is_active' => true,
                'condition_on_rent_id' => $conditionGoodId,
                'condition_on_return_id' => $conditionGoodId,
                'rented_at' => now()->subDays(7),
                'returned_at' => now()->subDays(2),
                'remarks' => 'Bi rach khi tra',
                'created_at' => now()->subDays(7),
                'updated_at' => now(),
            ]
        );

        $incident = RentalIncident::query()->updateOrCreate(
            ['code' => 'SCTH-SAMPLE-001'],
            [
                'is_active' => true,
                'rental_detail_id' => $detail->id,
                'inventory_item_id' => $damagedItem->id,
                'incident_description' => 'Trang phuc bi rach khi tra',
                'incident_type' => IncidentType::DAMAGED->value,
                'status' => IncidentStatus::OPEN->value,
                'compensation_amount' => 250000,
                'resolved_by_id' => null,
                'resolved_at' => null,
                'resolution' => null,
                'created_at' => now()->subDays(2),
                'updated_at' => now(),
            ]
        );

        MaintenanceTicket::query()->updateOrCreate(
            ['code' => 'PBH-SAMPLE-001'],
            [
                'is_active' => true,
                'item_id' => $equipmentPropId,
                'inventory_item_id' => $damagedItem->id,
                'maintenance_type' => MaintenanceType::REPAIR->value,
                'reported_date' => now()->subDays(2)->toDateString(),
                'started_date' => null,
                'expected_return_date' => now()->addDays(2)->toDateString(),
                'return_date' => null,
                'status' => MaintenanceStatus::OPEN->value,
                'vendor' => 'TiEM SUA MAU',
                'cost' => null,
                'remarks' => 'Cho xu ly vet rach',
                'created_by' => $createdBy,
                'created_at' => now()->subDays(2),
                'updated_at' => now(),
            ]
        );

        $this->applyInventoryState(
            $returnedItem,
            InventoryItemStatus::RENTED->value,
            $conditionGoodId,
            InventoryTransactionType::RENTAL_OUT->value,
            InventoryReferenceType::RENTAL_SLIP->value,
            $slip->id,
            'Seed damaged rental sample out returned item'
        );

        $this->applyInventoryState(
            $damagedItem,
            InventoryItemStatus::RENTED->value,
            $conditionGoodId,
            InventoryTransactionType::RENTAL_OUT->value,
            InventoryReferenceType::RENTAL_SLIP->value,
            $slip->id,
            'Seed damaged rental sample out damaged item'
        );

        $this->applyInventoryState(
            $returnedItem,
            InventoryItemStatus::AVAILABLE->value,
            $conditionGoodId,
            InventoryTransactionType::RENTAL_RETURN->value,
            InventoryReferenceType::RENTAL_SLIP->value,
            $slip->id,
            'Seed damaged rental sample normal return'
        );

        $this->applyInventoryState(
            $damagedItem,
            InventoryItemStatus::MAINTENANCE->value,
            $conditionGoodId,
            InventoryTransactionType::MAINTENANCE_OUT->value,
            InventoryReferenceType::RENTAL_INCIDENT->value,
            $incident->id,
            'Seed damaged rental sample maintenance'
        );

        RentalPayment::query()->updateOrCreate(
            [
                'rental_slip_id' => $slip->id,
                'payment_type' => RentalPaymentType::RENTAL_PAYMENT->value,
                'reference_no' => 'SEED-PAY-002',
            ],
            [
                'is_active' => true,
                'payment_date' => now()->subDays(2),
                'amount' => 300000,
                'payment_method' => 'BANK_TRANSFER',
                'received_by' => $createdBy,
                'note' => 'Thanh toan mau',
                'created_at' => now()->subDays(2),
                'updated_at' => now(),
            ]
        );
    }

    private function applyInventoryState(
        InventoryItem $inventoryItem,
        string $status,
        int $conditionId,
        string $transactionType,
        string $referenceType,
        int $referenceId,
        string $note
    ): void {
        $freshItem = $inventoryItem->fresh();

        if ($freshItem === null) {
            return;
        }

        $before = InventoryItem::query()
            ->where('item_id', $freshItem->item_id)
            ->where('warehouse_id', $freshItem->warehouse_id)
            ->where('status', InventoryItemStatus::AVAILABLE->value)
            ->count();

        $freshItem->update([
            'inventory_condition_id' => $conditionId,
            'status' => $status,
        ]);

        $after = InventoryItem::query()
            ->where('item_id', $freshItem->item_id)
            ->where('warehouse_id', $freshItem->warehouse_id)
            ->where('status', InventoryItemStatus::AVAILABLE->value)
            ->count();

        InventoryTransaction::query()->updateOrCreate(
            [
                'inventory_item_id' => $freshItem->id,
                'transaction_type' => $transactionType,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ],
            [
                'equipment_prop_id' => $freshItem->item_id,
                'warehouse_id' => $freshItem->warehouse_id,
                'quantity' => 1,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'note' => $note,
                'performed_by' => User::query()->orderBy('id')->value('id'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function mappedCatalogId(int $jsonItemId): ?int
    {
        return $this->catalogItemIdMap[$jsonItemId] ?? null;
    }

    private function mappedCatalogIdForType(int $jsonItemId, string $itemType): ?int
    {
        return $this->catalogItemIdMapByType[$itemType][$jsonItemId] ?? null;
    }

    private function existingEmployeeId(mixed $id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }

        $employeeId = (int) $id;

        return Employee::query()->whereKey($employeeId)->exists() ? $employeeId : null;
    }

    private function existingInventoryId(mixed $id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }

        $inventoryId = (int) $id;

        return InventoryItem::query()->whereKey($inventoryId)->exists() ? $inventoryId : null;
    }

    private function existingWarehouseId(mixed $id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }

        $warehouseId = (int) $id;

        return Warehouse::query()->whereKey($warehouseId)->exists() ? $warehouseId : null;
    }

    private function warehouseCode(array $warehouse): string
    {
        $base = strtoupper(Str::slug((string) ($warehouse['name'] ?? 'warehouse'), '-')) ?: 'WAREHOUSE';

        return $base.'-'.$warehouse['id'];
    }

    private function passwordValue(string $password): string
    {
        if (Hash::isHashed($password) || preg_match('/^\$2[aby]\$/', $password) === 1) {
            return Hash::make((string) env('SEED_USER_PASSWORD', '123123'));
        }

        return Hash::make($password);
    }

    private function normalizeColor(mixed $color): ?array
    {
        if ($color === null || $color === '') {
            return null;
        }

        if (is_array($color)) {
            return $color;
        }

        return [
            'hex' => (string) $color,
        ];
    }

    private function storeSeedImage(array $image): ?array
    {
        $sourcePath = $this->findSeedImagePath($image);

        if ($sourcePath === null) {
            return null;
        }

        $fileName = pathinfo((string) ($image['file_name'] ?? basename($sourcePath)), PATHINFO_FILENAME).'.webp';
        $path = "images-gallery/{$image['category_id']}/{$fileName}";
        $contents = $this->webpContents($sourcePath);

        if ($contents === null) {
            $this->command?->warn("Cannot convert seed image to WebP: {$sourcePath}");

            return null;
        }

        Storage::disk('public')->put($path, $contents);

        return [
            'file_name' => basename($path),
            'mime_type' => 'image/webp',
            'size' => strlen($contents),
            'dest' => '/'.$image['category_id'].'/'.basename($path),
        ];
    }

    private function seedImagePublicPath(array $image): string
    {
        $fileName = basename((string) ($image['file_name'] ?? $image['dest'] ?? ''));

        if ($fileName !== '') {
            return "/{$image['category_id']}/{$fileName}";
        }

        $dest = (string) ($image['dest'] ?? '');
        $path = parse_url($dest, PHP_URL_PATH);

        if (is_string($path) && $path !== '') {
            return $path;
        }

        return $dest;
    }

    private function findSeedImagePath(array $image): ?string
    {
        $sourceDir = $this->seedImageSourceDir();

        if ($sourceDir === null) {
            return null;
        }

        $fileName = basename((string) ($image['file_name'] ?? $image['dest'] ?? ''));

        if ($fileName === '') {
            return null;
        }

        $candidate = $sourceDir.DIRECTORY_SEPARATOR.$fileName;

        if (is_file($candidate)) {
            return $candidate;
        }

        foreach (scandir($sourceDir) ?: [] as $entry) {
            if (strcasecmp($entry, $fileName) === 0) {
                return $sourceDir.DIRECTORY_SEPARATOR.$entry;
            }
        }

        $this->command?->warn("Seed image not found: {$fileName}");

        return null;
    }

    private function seedImageSourceDir(): ?string
    {
        $sourceDir = (string) env('SEED_IMAGE_SOURCE_DIR', base_path('../FE-COSTUME-RENTAL/mock/images'));

        if (! is_dir($sourceDir)) {
            return null;
        }

        return rtrim($sourceDir, DIRECTORY_SEPARATOR.'/\\');
    }

    private function webpContents(string $sourcePath): ?string
    {
        if (strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) === 'webp') {
            $contents = file_get_contents($sourcePath);

            return is_string($contents) && $contents !== '' ? $contents : null;
        }

        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            return null;
        }

        $sourceContents = file_get_contents($sourcePath);
        $image = is_string($sourceContents) ? @imagecreatefromstring($sourceContents) : false;

        if ($image === false) {
            return null;
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $success = imagewebp($image, null, 85);
        $contents = ob_get_clean();

        imagedestroy($image);

        return $success && is_string($contents) && $contents !== '' ? $contents : null;
    }
}
