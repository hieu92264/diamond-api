<?php

namespace App\Http\Controllers\Api;

use App\Enums\ItemCategoryType;
use App\Http\Controllers\Api\Concerns\InteractsWithCatalogItems;
use App\Http\Controllers\Controller;
use App\Http\Requests\EquipmentProp\StoreEquipmentPropRequest;
use App\Models\EquipmentProp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EquipmentPropController extends Controller
{
    use InteractsWithCatalogItems;

    public function index(Request $request): JsonResponse
    {
        $query = $this->catalogItemsQuery(ItemCategoryType::EQUIPMENT_PROPS);
        $this->applyCatalogFilters($query, $request, [
            'id' => 'id',
            'category_id' => 'item_category_id',
        ]);

        $props = $query->orderByDesc('id')
            ->get()
            ->map(fn (EquipmentProp $item) => $this->transformCatalogItem($item))
            ->all();

        return $this->success($props, 'Lấy danh sách đạo cụ thành công!');
    }

    public function store(StoreEquipmentPropRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->ensureCategoryType($data['category_id'], ItemCategoryType::EQUIPMENT_PROPS);

        $prop = EquipmentProp::query()->create(
            $this->buildCatalogPayload($data, 'PRP', false)
        )->fresh(['itemCategory', 'galleryImages']);

        return $this->success(
            $this->transformCatalogItem($prop),
            'Tạo đạo cụ thành công!',
            Response::HTTP_CREATED
        );
    }
}
