<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ItemCategory\StoreItemCategoryRequest;
use App\Http\Requests\ItemCategory\UpdateItemCategoryRequest;
use App\Models\ItemCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ItemCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ItemCategory::query()->orderBy('name');

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        if ($request->filled('type')) {
            $query->where('type', strtoupper((string) $request->query('type')));
        }

        $categories = $query->get()
            ->map(fn (ItemCategory $category) => $this->transformCategory($category))
            ->all();

        return $this->success($categories, 'Lấy danh sách danh mục thành công!');
    }

    public function store(StoreItemCategoryRequest $request): JsonResponse
    {
        $category = ItemCategory::query()->create([
            ...$request->validated(),
            'code' => $this->nextCategoryCode(),
            'is_active' => $request->validated('is_active', true),
        ]);

        return $this->success(
            $this->transformCategory($category),
            'Tạo danh mục thành công!',
            Response::HTTP_CREATED
        );
    }

    public function update(UpdateItemCategoryRequest $request, int $id): JsonResponse
    {
        $category = ItemCategory::query()->findOrFail($id);
        $category->update($request->validated());

        return $this->success($this->transformCategory($category->fresh()), 'Cập nhật danh mục thành công!');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $category = ItemCategory::query()->findOrFail($id);

        if ($this->shouldDeletePermanently($request)) {
            $category->delete();
        } else {
            $category->update(['is_active' => false]);
        }

        return $this->success(null, 'Xóa danh mục thành công!');
    }

    private function transformCategory(ItemCategory $category): array
    {
        return [
            'id' => $category->id,
            'code' => $category->code,
            'name' => $category->name,
            'type' => $category->type?->value,
            'remarks' => $category->remarks,
            'is_active' => $category->is_active,
            'created_at' => $category->created_at?->toISOString(),
            'updated_at' => $category->updated_at?->toISOString(),
        ];
    }

    private function nextCategoryCode(): string
    {
        $lastCode = ItemCategory::query()
            ->where('code', 'like', 'CAT%')
            ->max('code');

        $sequence = $lastCode
            ? ((int) preg_replace('/\D+/', '', $lastCode)) + 1
            : 1;

        return 'CAT'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function shouldDeletePermanently(Request $request): bool
    {
        if (
            ($request->has('permanently') && in_array($request->query('permanently'), [null, ''], true))
            || ($request->has('permanantly') && in_array($request->query('permanantly'), [null, ''], true))
        ) {
            return true;
        }

        return $request->boolean('permanently') || $request->boolean('permanantly');
    }
}
