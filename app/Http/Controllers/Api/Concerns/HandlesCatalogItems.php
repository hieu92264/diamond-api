<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Enums\ItemCategoryType;
use App\Models\EquipmentProp;
use App\Models\GalleryImage;
use App\Models\ItemCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait HandlesCatalogItems
{
    protected function catalogItemsQuery(ItemCategoryType $type, bool $onlyActive = true): Builder
    {
        $query = EquipmentProp::query()
            ->with(['itemCategory', 'galleryImages.category', 'galleryImages.creator.employee'])
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
                'category_id' => ["Category must have type {$type->value}."],
            ]);
        }

        return $category;
    }

    protected function buildCatalogPayload(array $data, bool $isCostume): array
    {
        return [
            'sku' => $data['sku'] ?? $this->uniqueCatalogSku($data, $isCostume),
            'name' => $data['name'],
            'slug' => $this->uniqueSlug(EquipmentProp::query(), $data['slug'] ?? $data['name']),
            'color' => $isCostume ? ($data['color'] ?? null) : null,
            'sizes' => $isCostume ? ($data['sizes'] ?? []) : null,
            'gender' => $isCostume ? ($data['gender'] ?? null) : null,
            'category_id' => $data['category_id'],
            'unit' => $data['unit'] ?? ($isCostume ? 'SET' : null),
            'rental_price_per_day' => $data['rental_price_per_day'] ?? null,
            'weight_kg' => $isCostume ? null : ($data['weight_kg'] ?? null),
            'dimensions' => $isCostume ? null : ($data['dimensions'] ?? null),
            'is_fragile' => $isCostume ? false : ($data['is_fragile'] ?? false),
            'hashtags' => $data['hashtags'] ?? [],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];
    }

    protected function updateCatalogPayload(array $data, bool $isCostume, ?EquipmentProp $item = null): array
    {
        $payload = [];

        if (array_key_exists('name', $data)) {
            $payload['name'] = $data['name'];
        }

        if (array_key_exists('sku', $data)) {
            $payload['sku'] = $this->uniqueCatalogSku($data, $isCostume, $item?->id);
        }

        if (array_key_exists('slug', $data)) {
            $payload['slug'] = $this->uniqueSlug(EquipmentProp::query(), $data['slug'], $item?->id);
        }

        if (array_key_exists('category_id', $data)) {
            $payload['category_id'] = $data['category_id'];
        }

        if (array_key_exists('unit', $data)) {
            $payload['unit'] = $data['unit'];
        }

        if (array_key_exists('rental_price_per_day', $data)) {
            $payload['rental_price_per_day'] = $data['rental_price_per_day'];
        }

        if (array_key_exists('description', $data)) {
            $payload['description'] = $data['description'];
        }

        if (array_key_exists('hashtags', $data)) {
            $payload['hashtags'] = $data['hashtags'];
        }

        if (array_key_exists('is_active', $data)) {
            $payload['is_active'] = $data['is_active'];
        }

        if ($isCostume) {
            foreach (['color', 'sizes', 'gender'] as $field) {
                if (array_key_exists($field, $data)) {
                    $payload[$field] = $data[$field];
                }
            }
        } else {
            foreach (['weight_kg', 'dimensions', 'is_fragile'] as $field) {
                if (array_key_exists($field, $data)) {
                    $payload[$field] = $data[$field];
                }
            }
        }

        return $payload;
    }

    protected function syncGalleryImages(EquipmentProp $item, ?array $imageIds, ItemCategoryType $categoryType): void
    {
        if ($imageIds === null) {
            return;
        }

        $validImageIds = GalleryImage::query()
            ->active()
            ->whereHas('category', function (Builder $builder) use ($categoryType): void {
                $builder->where('type', $categoryType->value);
            })
            ->whereIn('id', $imageIds)
            ->pluck('id')
            ->all();

        if (count($validImageIds) !== count(array_unique($imageIds))) {
            throw ValidationException::withMessages([
                'images' => ['One or more images are inactive, missing, or belong to another category type.'],
            ]);
        }

        $item->galleryImages()->sync($imageIds);
    }

    protected function transformCatalogItem(EquipmentProp $item): array
    {
        $item->loadMissing(['itemCategory', 'galleryImages.category', 'galleryImages.creator.employee']);
        $images = $item->galleryImages
            ->where('is_active', true)
            ->values();

        return [
            'id' => $item->id,
            'sku' => $item->sku,
            'slug' => $item->slug,
            'name' => $item->name,
            'category_id' => $item->category_id,
            'category' => $item->itemCategory ? [
                'id' => $item->itemCategory->id,
                'name' => $item->itemCategory->name,
                'slug' => $item->itemCategory->slug,
                'type' => $item->itemCategory->type?->value,
            ] : null,
            'unit' => $item->unit,
            'color' => $item->color,
            'sizes' => $item->sizes ?? [],
            'gender' => $item->gender?->value,
            'image_ids' => $images->pluck('id')->values()->all(),
            'images' => $images
                ->map(fn (GalleryImage $image) => $this->transformGalleryImage($image))
                ->all(),
            'rental_price_per_day' => $item->rental_price_per_day !== null
                ? (float) $item->rental_price_per_day
                : null,
            'weight_kg' => $item->weight_kg !== null ? (float) $item->weight_kg : null,
            'dimensions' => $item->dimensions,
            'is_fragile' => $item->is_fragile,
            'description' => $item->description,
            'hashtags' => $item->hashtags ?? [],
            'is_active' => $item->is_active,
            'created_at' => $item->created_at?->toISOString(),
            'updated_at' => $item->updated_at?->toISOString(),
        ];
    }

    protected function transformGalleryImage(GalleryImage $image): array
    {
        $image->loadMissing(['category', 'creator.employee']);
        $publicPath = $this->galleryImagePublicPath($image);

        return [
            'id' => $image->id,
            'file_name' => $image->file_name,
            'size' => $image->size,
            'dest' => $publicPath,
            'url' => $publicPath,
            'mime_type' => $image->mime_type,
            'category_id' => $image->category_id,
            'category' => $image->category ? [
                'id' => $image->category->id,
                'name' => $image->category->name,
                'slug' => $image->category->slug,
                'type' => $image->category->type?->value,
            ] : null,
            'is_active' => $image->is_active,
            'created_by_id' => $image->created_by,
            'created_by' => $this->transformImageCreator($image->creator),
            'created_at' => $image->created_at?->toISOString(),
            'updated_at' => $image->updated_at?->toISOString(),
        ];
    }

    protected function galleryImagePublicPath(GalleryImage $image): string
    {
        $fileName = trim((string) $image->file_name, '/');

        if ($fileName === '') {
            return (string) $image->dest;
        }

        return '/storage/images-gallery/'.$image->category_id.'/'.$fileName;
    }

    protected function transformImageCreator(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $user->loadMissing('employee');

        return [
            'id' => $user->id,
            'username' => $user->username,
            'role' => $user->role?->value,
            'employee_id' => $user->employee_id,
            'employee' => $user->employee ? [
                'id' => $user->employee->id,
                'employee_code' => $user->employee->employee_code,
                'full_name' => $user->employee->full_name,
                'phone' => $user->employee->phone,
                'email' => $user->employee->email,
                'citizen_id_number' => $user->employee->citizen_id_number,
                'position' => $user->employee->position?->value,
                'work_status' => $user->employee->work_status?->value,
            ] : null,
            'is_active' => $user->is_active,
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }

    private function uniqueSlug(Builder $query, string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'item';
        $slug = $base;
        $sequence = 2;

        while (
            (clone $query)
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn (Builder $builder) => $builder->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$sequence;
            $sequence++;
        }

        return $slug;
    }

    private function uniqueCatalogSku(array $data, bool $isCostume, ?int $ignoreId = null): string
    {
        if (! empty($data['sku'])) {
            $base = strtoupper(trim((string) $data['sku']));
        } else {
            $source = (string) ($data['slug'] ?? $data['name'] ?? 'item');
            $prefix = $isCostume ? 'TP' : 'DC';
            $base = $prefix.'-'.strtoupper(Str::slug($source, '-'));
        }
        $sku = $base;
        $sequence = 2;

        while (
            EquipmentProp::query()
                ->where('sku', $sku)
                ->when($ignoreId !== null, fn (Builder $builder) => $builder->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $sku = $base.'-'.$sequence;
            $sequence++;
        }

        return $sku;
    }
}
