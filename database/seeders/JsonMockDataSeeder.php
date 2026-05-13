<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EquipmentProp;
use App\Models\GalleryImage;
use App\Models\ItemCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class JsonMockDataSeeder extends Seeder
{
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
                $this->seedCategories($data['categories'] ?? []);
                $this->seedImages($data['images'] ?? []);
                $this->seedCatalogItems($data['costumes'] ?? [], true);
                $this->seedCatalogItems($data['equipment_props'] ?? [], false);
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

    private function seedImages(array $images): void
    {
        foreach ($images as $image) {
            GalleryImage::query()->updateOrCreate(
                ['id' => $image['id']],
                [
                    'file_name' => $image['file_name'],
                    'mime_type' => $image['mime_type'] ?? null,
                    'size' => $image['size'] ?? null,
                    'dest' => $image['dest'],
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
                    'name' => $item['name'],
                    'category_id' => $item['category_id'],
                    'unit' => $item['unit'] ?? ($isCostume ? 'SET' : null),
                    'color' => $isCostume ? ($item['color'] ?? null) : null,
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
        }
    }

    private function passwordValue(string $password): string
    {
        return Hash::isHashed($password) ? $password : Hash::make($password);
    }
}
