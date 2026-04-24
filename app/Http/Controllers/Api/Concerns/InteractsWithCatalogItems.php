<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Enums\EquipmentStatus;
use App\Enums\GalleryItemType;
use App\Enums\ItemCategoryType;
use App\Models\EquipmentProp;
use App\Models\GalleryImage;
use App\Models\ItemCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait InteractsWithCatalogItems
{
    protected function catalogItemsQuery(ItemCategoryType $type, bool $onlyActive = true): Builder
    {
        $query = EquipmentProp::query()
            ->with(['itemCategory', 'galleryImages'])
            ->whereHas('itemCategory', function (Builder $builder) use ($type): void {
                $builder->where('type', $type->value);
            });

        if ($onlyActive) {
            $query->where('is_active', true);
        }

        return $query;
    }

    protected function applyCatalogFilters(Builder $query, Request $request, array $fields): Builder
    {
        foreach ($fields as $requestField => $column) {
            $inValue = $request->query($requestField.':in');

            if ($inValue !== null && $inValue !== '') {
                $values = collect(explode(',', (string) $inValue))
                    ->map(fn (string $value) => trim($value))
                    ->filter(fn (string $value) => $value !== '')
                    ->values()
                    ->all();

                if ($values !== []) {
                    $query->whereIn($column, $values);
                }
            }

            $eqValue = $request->query($requestField.':eq');

            if ($eqValue === null || $eqValue === '') {
                continue;
            }

            if (strtolower((string) $eqValue) === 'null') {
                $query->whereNull($column);
                continue;
            }

            $query->where($column, $eqValue);
        }

        return $query;
    }

    protected function ensureCategoryType(int $categoryId, ItemCategoryType $type): ItemCategory
    {
        $category = ItemCategory::query()->findOrFail($categoryId);

        if (($category->type?->value ?? $category->type) !== $type->value) {
            throw ValidationException::withMessages([
                'category_id' => ["Danh mục phải có type {$type->value}."],
            ]);
        }

        return $category;
    }

    protected function buildCatalogPayload(array $data, string $codePrefix, bool $isCostume): array
    {
        return [
            'code' => $this->nextEquipmentCode($codePrefix),
            'name' => $data['name'],
            'color' => $data['color'] ?? null,
            'sizes' => $isCostume ? ($data['sizes'] ?? null) : null,
            'gender' => $isCostume ? ($data['gender'] ?? null) : null,
            'item_category_id' => $data['category_id'],
            'unit' => 'piece',
            'status' => EquipmentStatus::AVAILABLE->value,
            'quantity_total' => $data['quantity_total'] ?? 1,
            'quantity_available' => $data['quantity_available'] ?? ($data['quantity_total'] ?? 1),
            'minimum_quantity' => $data['minimum_quantity'] ?? 0,
            'default_rental_price' => $data['rental_price_per_day'],
            'weight_kg' => $isCostume ? null : ($data['weight_kg'] ?? null),
            'dimensions' => $isCostume ? null : ($data['dimensions'] ?? null),
            'is_fragile' => $isCostume ? false : ($data['is_fragile'] ?? false),
            'tags' => $data['tags'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];
    }

    protected function updateCatalogPayload(array $data, bool $isCostume): array
    {
        $payload = [];

        if (array_key_exists('name', $data)) {
            $payload['name'] = $data['name'];
        }

        if (array_key_exists('color', $data)) {
            $payload['color'] = $data['color'];
        }

        if (array_key_exists('category_id', $data)) {
            $payload['item_category_id'] = $data['category_id'];
        }

        if (array_key_exists('rental_price_per_day', $data)) {
            $payload['default_rental_price'] = $data['rental_price_per_day'];
        }

        if (array_key_exists('description', $data)) {
            $payload['description'] = $data['description'];
        }

        if (array_key_exists('tags', $data)) {
            $payload['tags'] = $data['tags'];
        }

        if (array_key_exists('is_active', $data)) {
            $payload['is_active'] = $data['is_active'];
        }

        if ($isCostume) {
            if (array_key_exists('sizes', $data)) {
                $payload['sizes'] = $data['sizes'];
            }

            if (array_key_exists('gender', $data)) {
                $payload['gender'] = $data['gender'];
            }
        } else {
            if (array_key_exists('weight_kg', $data)) {
                $payload['weight_kg'] = $data['weight_kg'];
            }

            if (array_key_exists('dimensions', $data)) {
                $payload['dimensions'] = $data['dimensions'];
            }

            if (array_key_exists('is_fragile', $data)) {
                $payload['is_fragile'] = $data['is_fragile'];
            }
        }

        return $payload;
    }

    protected function syncGalleryImages(EquipmentProp $item, ?array $imageIds, ItemCategoryType $categoryType): void
    {
        if ($imageIds === null) {
            return;
        }

        $expectedType = $this->galleryTypeForCategory($categoryType);

        $validImageIds = GalleryImage::query()
            ->active()
            ->where('item_type', $expectedType->value)
            ->whereIn('id', $imageIds)
            ->pluck('id')
            ->all();

        if (count($validImageIds) !== count($imageIds)) {
            throw ValidationException::withMessages([
                'image_ids' => ['Một hoặc nhiều ảnh không hợp lệ hoặc không đúng item_type.'],
            ]);
        }

        $item->galleryImages()->sync($imageIds);
    }

    protected function transformCatalogItem(EquipmentProp $item): array
    {
        $item->loadMissing(['itemCategory', 'galleryImages']);
        $images = $item->galleryImages
            ->where('is_active', true)
            ->values();

        return [
            'id' => $item->id,
            'code' => $item->code,
            'name' => $item->name,
            'item_type' => $item->itemCategory?->type?->value,
            'category_id' => $item->item_category_id,
            'category' => $item->itemCategory ? [
                'id' => $item->itemCategory->id,
                'code' => $item->itemCategory->code,
                'name' => $item->itemCategory->name,
                'type' => $item->itemCategory->type?->value,
            ] : null,
            'color' => $item->color,
            'sizes' => $item->sizes ?? [],
            'gender' => $item->gender?->value,
            'rental_price_per_day' => (float) $item->default_rental_price,
            'weight_kg' => $item->weight_kg !== null ? (float) $item->weight_kg : null,
            'dimensions' => $item->dimensions,
            'is_fragile' => $item->is_fragile,
            'description' => $item->description,
            'tags' => $item->tags ?? [],
            'status' => $item->status?->value,
            'quantity_total' => $item->quantity_total,
            'quantity_available' => $item->quantity_available,
            'image_ids' => $images->pluck('id')->values()->all(),
            'images' => $images
                ->map(fn (GalleryImage $image) => $this->transformGalleryImage($image))
                ->values()
                ->all(),
            'is_active' => $item->is_active,
            'created_at' => $item->created_at?->toISOString(),
            'updated_at' => $item->updated_at?->toISOString(),
        ];
    }

    protected function transformGalleryImage(GalleryImage $image): array
    {
        return [
            'id' => $image->id,
            'item_type' => $image->item_type?->value,
            'original_name' => $image->original_name,
            'mime_type' => $image->mime_type,
            'size' => $image->size,
            'url' => $image->url,
            'is_active' => $image->is_active,
            'created_at' => $image->created_at?->toISOString(),
            'updated_at' => $image->updated_at?->toISOString(),
        ];
    }

    private function galleryTypeForCategory(ItemCategoryType $type): GalleryItemType
    {
        return match ($type) {
            ItemCategoryType::COSTUME => GalleryItemType::COSTUME,
            ItemCategoryType::EQUIPMENT_PROPS => GalleryItemType::EQUIPMENT_PROPS,
        };
    }

    private function nextEquipmentCode(string $prefix): string
    {
        $lastCode = EquipmentProp::query()
            ->where('code', 'like', $prefix.'%')
            ->max('code');

        $sequence = $lastCode
            ? ((int) preg_replace('/\D+/', '', $lastCode)) + 1
            : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
