<?php

namespace Tests\Feature\Api;

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
            ->assertJsonPath('metadata.slug', 'trang-phuc-truyen-thong')
            ->assertJsonPath('metadata.type', ItemCategoryType::COSTUME->value);

        $categoryId = $createResponse->json('metadata.id');

        $this->withHeaders($headers)
            ->patchJson("/api/item-categories/{$categoryId}", [
                'name' => 'Ao dai',
                'type' => ItemCategoryType::COSTUME->value,
            ])
            ->assertOk()
            ->assertJsonPath('metadata.name', 'Ao dai')
            ->assertJsonPath('metadata.slug', 'ao-dai');

        $this->withHeaders($headers)
            ->getJson('/api/categories?type:eq=COSTUME&_embed=costumes,equipment_props')
            ->assertOk()
            ->assertJsonCount(1, 'metadata')
            ->assertJsonPath('metadata.0.costumes', [])
            ->assertJsonPath('metadata.0.equipment_props', []);

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

        $category = ItemCategory::query()->create([
            'name' => 'Ao dai',
            'slug' => 'ao-dai',
            'type' => ItemCategoryType::COSTUME,
            'is_active' => true,
        ]);

        $uploadResponse = $this->withHeaders($headers)
            ->post('/api/images-gallery/upload', [
                'data' => json_encode(['category_id' => $category->id]),
                'files' => [
                    UploadedFile::fake()->image('costume-1.jpg'),
                    UploadedFile::fake()->image('costume-2.jpg'),
                ],
            ]);

        $uploadResponse
            ->assertCreated()
            ->assertJsonCount(2, 'metadata')
            ->assertJsonPath('metadata.0.category_id', $category->id);

        $imageId = $uploadResponse->json('metadata.0.id');

        $this->withHeaders($headers)
            ->getJson('/api/images-gallery?_expand=category')
            ->assertOk()
            ->assertJsonCount(2, 'metadata')
            ->assertJsonPath('metadata.0.category.id', $category->id);

        $this->withHeaders($headers)
            ->patchJson('/api/images-gallery/'.$imageId, [
                'file_name' => 'renamed.jpg',
                'category_id' => $category->id,
            ])
            ->assertOk()
            ->assertJsonPath('metadata.id', $imageId)
            ->assertJsonPath('metadata.file_name', 'renamed.jpg');

        $this->withHeaders($headers)
            ->delete('/api/images-gallery/'.$imageId)
            ->assertOk()
            ->assertJsonPath('metadata', null);

        $this->assertDatabaseHas('gallery_images', [
            'id' => $imageId,
            'is_active' => 0,
        ]);
    }

    public function test_authenticated_user_can_create_update_and_list_costumes_and_equipment_props(): void
    {
        $headers = $this->authenticate();

        $costumeCategory = ItemCategory::query()->create([
            'name' => 'Ao dai',
            'slug' => 'ao-dai',
            'type' => ItemCategoryType::COSTUME,
            'is_active' => true,
        ]);

        $propCategory = ItemCategory::query()->create([
            'name' => 'Hoa mua',
            'slug' => 'hoa-mua',
            'type' => ItemCategoryType::EQUIPMENT_PROPS,
            'is_active' => true,
        ]);

        $costumeImage = GalleryImage::query()->create([
            'category_id' => $costumeCategory->id,
            'file_name' => 'costume.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'dest' => '/storage/images-gallery/costume.jpg',
            'is_active' => true,
        ]);

        $propImage = GalleryImage::query()->create([
            'category_id' => $propCategory->id,
            'file_name' => 'prop.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'dest' => '/storage/images-gallery/prop.jpg',
            'is_active' => true,
        ]);

        $costumeResponse = $this->withHeaders($headers)
            ->postJson('/api/costumes', [
                'name' => 'Ao tac xanh bac ha',
                'category_id' => $costumeCategory->id,
                'color' => '#5b958c',
                'sizes' => ['S', 'M', 'L'],
                'unit' => 'SET',
                'gender' => 'FEMALE',
                'images' => [$costumeImage->id],
                'rental_price_per_day' => 200000,
                'description' => 'Ao tac xanh co truyen',
                'hashtags' => ['ao tac'],
            ]);

        $costumeResponse
            ->assertCreated()
            ->assertJsonPath('metadata.name', 'Ao tac xanh bac ha')
            ->assertJsonPath('metadata.category.type', ItemCategoryType::COSTUME->value)
            ->assertJsonPath('metadata.images.0', $costumeImage->id)
            ->assertJsonPath('metadata.hashtags.0', 'ao tac');

        $costumeId = $costumeResponse->json('metadata.id');

        $this->withHeaders($headers)
            ->getJson('/api/costumes?id:in='.$costumeId)
            ->assertOk()
            ->assertJsonCount(1, 'metadata')
            ->assertJsonPath('metadata.0.id', $costumeId);

        $this->withHeaders($headers)
            ->patchJson("/api/costumes/{$costumeId}", [
                'images' => [],
            ])
            ->assertOk()
            ->assertJsonPath('metadata.images', []);

        $propResponse = $this->withHeaders($headers)
            ->postJson('/api/equipment-props', [
                'name' => 'Hoa mua cam tay',
                'category_id' => $propCategory->id,
                'unit' => 'Cap',
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
                'image_id' => $propImage->id,
            ]);

        $propResponse
            ->assertCreated()
            ->assertJsonPath('metadata.category.type', ItemCategoryType::EQUIPMENT_PROPS->value)
            ->assertJsonPath('metadata.dimensions.width_cm', 60)
            ->assertJsonPath('metadata.weight_kg', 0.5)
            ->assertJsonPath('metadata.images.0', $propImage->id);

        $propId = $propResponse->json('metadata.id');

        $this->withHeaders($headers)
            ->patchJson("/api/equipment-props/{$propId}", [
                'dimensions' => [
                    'width_cm' => 40,
                    'height_cm' => 40,
                    'depth_cm' => 8,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('metadata.dimensions.width_cm', 40);

        $this->withHeaders($headers)
            ->getJson('/api/equipment-props')
            ->assertOk()
            ->assertJsonCount(1, 'metadata');
    }
}
