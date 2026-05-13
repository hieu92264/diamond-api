<?php

namespace Tests\Feature\Seeders;

use App\Models\Employee;
use App\Models\EquipmentProp;
use App\Models\GalleryImage;
use App\Models\ItemCategory;
use App\Models\User;
use Database\Seeders\JsonMockDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JsonMockDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_json_mock_data_seeder_imports_required_data(): void
    {
        $this->seed(JsonMockDataSeeder::class);

        $this->assertSame(7, User::query()->count());
        $this->assertSame(9, Employee::query()->count());
        $this->assertSame(7, ItemCategory::query()->count());
        $this->assertSame(12, GalleryImage::query()->count());
        $this->assertSame(6, EquipmentProp::query()->count());

        $this->assertDatabaseHas('users', [
            'id' => 1,
            'username' => 'quanghiep031',
            'employee_id' => 1,
        ]);

        $this->assertDatabaseHas('employees', [
            'id' => 1,
            'user_id' => 1,
            'employee_code' => 'D00001',
        ]);

        $this->assertDatabaseHas('equipment_prop_gallery_image', [
            'gallery_image_id' => 5,
        ]);
    }
}
