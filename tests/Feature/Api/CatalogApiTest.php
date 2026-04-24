<?php

namespace Tests\Feature\Api;

use App\Enums\GalleryItemType;
use App\Enums\ItemCategoryType;
use App\Enums\UserRole;
use App\Models\GalleryImage;
use App\Models\ItemCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogApiTest extends TestCase
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

    public function test_authenticated_user_can_manage_item_categories(): void
    {
        $headers = $this->authenticate();

        $createResponse = $this->withHeaders($headers)
            ->postJson('/api/item-categories', [
                'name' => 'Trang phuc truyen thong',
                'type' => ItemCategoryType::COSTUME->value,
            ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('metadata.name', 'Trang phuc truyen thong')
            ->assertJsonPath('metadata.type', ItemCategoryType::COSTUME->value);

        $categoryId = $createResponse->json('metadata.id');

        $this->withHeaders($headers)
            ->patchJson("/api/item-categories/{$categoryId}", [
                'remarks' => 'Danh muc costume',
            ])
            ->assertOk()
            ->assertJsonPath('metadata.remarks', 'Danh muc costume');

        $this->withHeaders($headers)
            ->getJson('/api/item-categories')
            ->assertOk()
            ->assertJsonCount(1, 'metadata');

        $this->withHeaders($headers)
            ->deleteJson("/api/item-categories/{$categoryId}")
            ->assertOk()
            ->assertJsonPath('metadata', null);

        $this->assertDatabaseHas('item_categories', [
            'id' => $categoryId,
            'is_active' => 0,
        ]);
    }

    public function test_authenticated_user_can_upload_update_and_soft_delete_gallery_images(): void
    {
        Storage::fake('public');
        $headers = $this->authenticate();

        $uploadResponse = $this->withHeaders($headers)
            ->post('/api/images-gallery/upload', [
                'item_type' => GalleryItemType::COSTUME->value,
                'file' => [
                    UploadedFile::fake()->image('costume-1.jpg'),
                    UploadedFile::fake()->image('costume-2.jpg'),
                ],
            ]);

        $uploadResponse
            ->assertCreated()
            ->assertJsonCount(2, 'metadata');

        $imageId = $uploadResponse->json('metadata.0.id');

        $this->withHeaders($headers)
            ->getJson('/api/images-gallery?_page=1&_per_page=10')
            ->assertOk()
            ->assertJsonCount(2, 'metadata.items')
            ->assertJsonPath('metadata.pagination.total', 2);

        $this->withHeaders($headers)
            ->patch('/api/images-gallery/update/'.$imageId, [
                'item_type' => GalleryItemType::COSTUME->value,
            ])
            ->assertOk()
            ->assertJsonPath('metadata.id', $imageId)
            ->assertJsonPath('metadata.item_type', GalleryItemType::COSTUME->value);

        $this->withHeaders($headers)
            ->delete('/api/images/delete/'.$imageId)
            ->assertOk()
            ->assertJsonPath('metadata', null);

        $this->assertDatabaseHas('gallery_images', [
            'id' => $imageId,
            'is_active' => 0,
        ]);
    }

    public function test_authenticated_user_can_create_and_list_costumes_and_equipment_props(): void
    {
        Storage::fake('public');
        $headers = $this->authenticate();

        $costumeCategory = ItemCategory::query()->create([
            'code' => 'CAT0001',
            'name' => 'Ao dai',
            'type' => ItemCategoryType::COSTUME,
            'is_active' => true,
        ]);

        $propCategory = ItemCategory::query()->create([
            'code' => 'CAT0002',
            'name' => 'Hoa mua',
            'type' => ItemCategoryType::EQUIPMENT_PROPS,
            'is_active' => true,
        ]);

        $image = GalleryImage::query()->create([
            'item_type' => GalleryItemType::COSTUME,
            'disk' => 'public',
            'path' => 'images-gallery/costume/sample.jpg',
            'original_name' => 'sample.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'is_active' => true,
        ]);

        $costumeResponse = $this->withHeaders($headers)
            ->postJson('/api/costumes', [
                'name' => 'Ao tac xanh bac ha',
                'category_id' => $costumeCategory->id,
                'color' => '#5b958c',
                'sizes' => ['S', 'M', 'L'],
                'gender' => 'FEMALE',
                'images_ids' => $image->id,
                'rental_price_per_day' => 200000,
                'description' => 'Ao tac xanh co truyen',
                'tags' => ['ao tac'],
            ]);

        $costumeResponse
            ->assertCreated()
            ->assertJsonPath('metadata.name', 'Ao tac xanh bac ha')
            ->assertJsonPath('metadata.item_type', ItemCategoryType::COSTUME->value)
            ->assertJsonPath('metadata.image_ids.0', $image->id);

        $costumeId = $costumeResponse->json('metadata.id');

        $this->withHeaders($headers)
            ->getJson('/api/costumes?id:in='.$costumeId)
            ->assertOk()
            ->assertJsonCount(1, 'metadata')
            ->assertJsonPath('metadata.0.id', $costumeId);

        $this->withHeaders($headers)
            ->patchJson("/api/costumes/{$costumeId}", [
                'image_ids' => [],
            ])
            ->assertOk()
            ->assertJsonPath('metadata.image_ids', []);

        $this->withHeaders($headers)
            ->postJson('/api/equipment-props', [
                'name' => 'Hoa mua cam tay',
                'category_id' => $propCategory->id,
                'rental_price_per_day' => 50000,
                'weight_kg' => 0.5,
                'demensions' => [
                    'width_cm' => 60,
                    'height_cm' => 60,
                    'depth_cm' => 10,
                ],
                'is_fragile' => false,
                'description' => 'Dao cu bieu dien',
                'tags' => ['hoa mua'],
            ])
            ->assertCreated()
            ->assertJsonPath('metadata.item_type', ItemCategoryType::EQUIPMENT_PROPS->value)
            ->assertJsonPath('metadata.dimensions.width_cm', 60)
            ->assertJsonPath('metadata.weight_kg', 0.5);

        $this->withHeaders($headers)
            ->getJson('/api/equipment-props')
            ->assertOk()
            ->assertJsonCount(1, 'metadata');
    }
}
