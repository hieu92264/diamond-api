<?php

namespace App\Http\Controllers\Api;

use App\Enums\InventoryItemStatus;
use App\Enums\ItemCategoryType;
use App\Http\Controllers\Controller;
use App\Models\EquipmentProp;
use App\Models\InventoryCondition;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class InventoryController extends Controller
{
    public function costumes(Request $request): JsonResponse
    {
        return $this->rawSuccess($this->inventoryList($request, ItemCategoryType::COSTUME));
    }

    public function props(Request $request): JsonResponse
    {
        return $this->rawSuccess($this->inventoryList($request, ItemCategoryType::EQUIPMENT_PROPS));
    }

    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer', Rule::exists('equipment_props', 'id')],
            'item_type' => ['required', Rule::enum(ItemCategoryType::class)],
            'inventory_condition_id' => ['required', 'integer', Rule::exists('inventory_conditions', 'id')],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'size' => ['nullable', 'string', Rule::in(['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $this->ensureItemType((int) $data['item_id'], ItemCategoryType::from($data['item_type']));

        $items = DB::transaction(function () use ($data): array {
            $created = [];

            for ($i = 0; $i < (int) $data['quantity']; $i++) {
                $created[] = InventoryItem::query()->create([
                    'sku' => $this->nextSku($data),
                    'item_id' => $data['item_id'],
                    'item_type' => $data['item_type'],
                    'inventory_condition_id' => $data['inventory_condition_id'],
                    'warehouse_id' => $data['warehouse_id'],
                    'status' => InventoryItemStatus::AVAILABLE->value,
                    'size' => $data['size'] ?? null,
                    'is_active' => true,
                ])->load(['item.itemCategory', 'inventoryCondition', 'warehouse']);
            }

            return array_map(fn(InventoryItem $item) => $this->transformInventoryItem($item), $created);
        });

        return $this->success($items, 'Nhập kho thành công!', Response::HTTP_CREATED);
    }

    public function updateCondition(Request $request, string $sku): JsonResponse
    {
        $data = $request->validate([
            'inventory_condition_id' => ['required', 'integer', Rule::exists('inventory_conditions', 'id')],
        ]);

        $item = InventoryItem::query()->where('sku', $sku)->firstOrFail();
        $item->update(['inventory_condition_id' => $data['inventory_condition_id']]);

        return $this->success($this->transformInventoryItem($item->fresh(['item.itemCategory', 'inventoryCondition', 'warehouse'])), 'Cập nhật tình trạng tồn kho thành công!');
    }

    public function conditions(Request $request): JsonResponse
    {
        $query = InventoryCondition::query();

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        return $this->rawSuccess($query->orderBy('code')->get()->toArray());
    }

    private function inventoryList(Request $request, ItemCategoryType $type): array
    {
        $query = InventoryItem::query()
            ->where('item_type', $type->value)
            ->with(['item.itemCategory', 'inventoryCondition', 'warehouse']);

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        foreach (['item_id', 'inventory_condition_id', 'warehouse_id', 'status', 'size', 'sku'] as $field) {
            $inValue = $request->query($field . ':in');

            if ($inValue !== null && $inValue !== '') {
                $query->whereIn($field, array_filter(array_map('trim', explode(',', (string) $inValue))));
            }

            $eqValue = $request->query($field . ':eq', $request->query($field));

            if ($eqValue !== null && $eqValue !== '') {
                $query->where($field, $eqValue);
            }
        }

        $keyword = trim((string) $request->query('keyword', ''));

        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('sku', 'like', "%{$keyword}%")
                    ->orWhereHas('item', fn(Builder $itemQuery) => $itemQuery->where('name', 'like', "%{$keyword}%"));
            });
        }

        return $query->orderByDesc('id')
            ->get()
            ->map(fn(InventoryItem $item) => $this->transformInventoryItem($item))
            ->all();
    }

    private function transformInventoryItem(InventoryItem $item): array
    {
        $item->loadMissing(['item.itemCategory', 'inventoryCondition', 'warehouse']);

        return [
            'id' => $item->id,
            'sku' => $item->sku,
            'item_id' => $item->item_id,
            'item_type' => $item->item_type?->value,
            'item' => $item->item,
            'inventory_condition_id' => $item->inventory_condition_id,
            'inventory_condition' => $item->inventoryCondition,
            'warehouse_id' => $item->warehouse_id,
            'warehouse' => $item->warehouse,
            'status' => $item->status?->value,
            'size' => $item->size,
            'is_active' => $item->is_active,
            'created_at' => $item->created_at?->toISOString(),
            'updated_at' => $item->updated_at?->toISOString(),
        ];
    }

    private function ensureItemType(int $itemId, ItemCategoryType $type): void
    {
        $exists = EquipmentProp::query()
            ->whereKey($itemId)
            ->whereHas('itemCategory', fn(Builder $query) => $query->where('type', $type->value))
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'item_id' => ["Vật phẩm không thuộc loại danh mục {$type->value}."],
            ]);
        }
    }

    private function nextSku(array $data): string
    {
        $prefix = ($data['item_type'] === ItemCategoryType::COSTUME->value ? 'TP' : 'DC')
            . -$data['item_id']
            . (! empty($data['size']) ? '-' . $data['size'] : '');
        $count = InventoryItem::query()
            ->where('sku', 'like', $prefix . '-%')
            ->lockForUpdate()
            ->count() + 1;

        return $prefix . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    public function available(Request $request): JsonResponse
    {
        $query = InventoryItem::query();

        $data = $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'item_type' => 'required|string|exists:inventory,item_type',
            'equipment_prop_id' => 'required|integer|exists:equipment_props,id',
            'size' => 'nullable|string',
            'inventory_condition_id'
        ]);
    }
}
