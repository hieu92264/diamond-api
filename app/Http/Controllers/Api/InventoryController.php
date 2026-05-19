<?php

namespace App\Http\Controllers\Api;

use App\Enums\InventoryReferenceType;
use App\Enums\InventoryItemStatus;
use App\Enums\InventoryTransactionType;
use App\Enums\ItemCategoryType;
use App\Http\Controllers\Controller;
use App\Models\EquipmentProp;
use App\Models\InventoryCondition;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\InternalIncident;
use App\Models\MaintenanceTicket;
use App\Models\RentalIncident;
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

    public function show(int $id): JsonResponse
    {
        $item = InventoryItem::query()
            ->with(['item.itemCategory', 'inventoryCondition', 'warehouse'])
            ->findOrFail($id);

        return $this->rawSuccess($this->transformInventoryItem($item));
    }

    public function available(Request $request): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')],
            'item_type' => ['nullable', Rule::enum(ItemCategoryType::class)],
            'equipment_prop_id' => ['nullable', 'integer', Rule::exists('equipment_props', 'id')],
            'size' => ['nullable', 'string', 'max:20'],
            'inventory_condition_id' => ['nullable', 'integer', Rule::exists('inventory_conditions', 'id')],
            'keyword' => ['nullable', 'string', 'max:255'],
        ]);

        $query = InventoryItem::query()
            ->available()
            ->whereHas('inventoryCondition', fn (Builder $query) => $query->where('rentable', true))
            ->with(['item.itemCategory', 'inventoryCondition', 'warehouse']);

        foreach (['warehouse_id', 'equipment_prop_id' => 'item_id', 'inventory_condition_id', 'size'] as $key => $field) {
            $requestKey = is_int($key) ? $field : $key;
            $column = is_int($key) ? $field : $field;

            if (! empty($data[$requestKey])) {
                $query->where($column, $data[$requestKey]);
            }
        }

        if (! empty($data['item_type'])) {
            $query->where('item_type', $data['item_type']);
        }

        if (! empty($data['keyword'])) {
            $keyword = trim($data['keyword']);

            $query->where(function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('sku', 'like', "%{$keyword}%")
                    ->orWhereHas('item', fn (Builder $itemQuery) => $itemQuery->where('name', 'like', "%{$keyword}%"));
            });
        }

        return $this->rawSuccess(
            $query->orderBy('item_id')
                ->orderBy('size')
                ->orderBy('sku')
                ->get()
                ->map(fn (InventoryItem $item) => $this->transformInventoryItem($item))
                ->all()
        );
    }

    public function timeline(int $id): JsonResponse
    {
        $item = InventoryItem::query()
            ->with(['item.itemCategory', 'inventoryCondition', 'warehouse'])
            ->findOrFail($id);

        return $this->rawSuccess([
            'inventory_item' => $this->transformInventoryItem($item),
            'transactions' => InventoryTransaction::query()
                ->with(['inventoryItem', 'warehouse'])
                ->where('inventory_item_id', $item->id)
                ->latestFirst()
                ->get()
                ->map(fn (InventoryTransaction $transaction) => $this->transformInventoryTransaction($transaction))
                ->all(),
            'internal_incidents' => InternalIncident::query()
                ->with('inventoryItem')
                ->where('inventory_item_id', $item->id)
                ->latest('id')
                ->get()
                ->map(fn (InternalIncident $incident) => $this->transformIncident($incident))
                ->all(),
            'rental_incidents' => RentalIncident::query()
                ->with('inventoryItem')
                ->where('inventory_item_id', $item->id)
                ->latest('id')
                ->get()
                ->map(fn (RentalIncident $incident) => $this->transformIncident($incident))
                ->all(),
            'maintenance_tickets' => MaintenanceTicket::query()
                ->with(['inventoryItem', 'item'])
                ->where('inventory_item_id', $item->id)
                ->latest('id')
                ->get()
                ->map(fn (MaintenanceTicket $ticket) => $this->transformMaintenanceTicket($ticket))
                ->all(),
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'inventory_item_id' => ['nullable', 'integer', Rule::exists('inventory', 'id')],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')],
            'transaction_type' => ['nullable', Rule::enum(InventoryTransactionType::class)],
            'reference_type' => ['nullable', Rule::enum(InventoryReferenceType::class)],
            'reference_id' => ['nullable', 'integer'],
        ]);

        $query = InventoryTransaction::query()
            ->with(['equipmentProp', 'inventoryItem', 'warehouse'])
            ->latestFirst();

        foreach (['inventory_item_id', 'warehouse_id', 'reference_id'] as $field) {
            if (! empty($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }

        if (! empty($data['transaction_type'])) {
            $query->where('transaction_type', $data['transaction_type']);
        }

        if (! empty($data['reference_type'])) {
            $query->where('reference_type', $data['reference_type']);
        }

        return $this->rawSuccess(
            $query->get()
                ->map(fn (InventoryTransaction $transaction) => $this->transformInventoryTransaction($transaction))
                ->all()
        );
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

    private function transformInventoryTransaction(InventoryTransaction $transaction): array
    {
        $transaction->loadMissing(['equipmentProp', 'inventoryItem', 'warehouse']);

        return [
            'id' => $transaction->id,
            'equipment_prop_id' => $transaction->equipment_prop_id,
            'inventory_item_id' => $transaction->inventory_item_id,
            'sku' => $transaction->inventoryItem?->sku,
            'warehouse_id' => $transaction->warehouse_id,
            'warehouse_name' => $transaction->warehouse?->name,
            'transaction_type' => $transaction->transaction_type?->value,
            'quantity' => $transaction->quantity,
            'quantity_before' => $transaction->quantity_before,
            'quantity_after' => $transaction->quantity_after,
            'reference_type' => $transaction->reference_type?->value,
            'reference_id' => $transaction->reference_id,
            'note' => $transaction->note,
            'created_at' => $transaction->created_at?->toISOString(),
        ];
    }

    private function transformIncident(InternalIncident|RentalIncident $incident): array
    {
        $incident->loadMissing('inventoryItem');

        return [
            'id' => $incident->id,
            'code' => $incident->code,
            'incident_type' => $incident->incident_type?->value,
            'status' => $incident->status?->value,
            'inventory_item_id' => $incident->inventory_item_id,
            'sku' => $incident->inventoryItem?->sku,
            'compensation_amount' => $incident->compensation_amount,
            'incident_description' => $incident->incident_description,
            'resolved_at' => $incident->resolved_at?->toISOString(),
        ];
    }

    private function transformMaintenanceTicket(MaintenanceTicket $ticket): array
    {
        $ticket->loadMissing(['inventoryItem', 'item']);

        return [
            'id' => $ticket->id,
            'code' => $ticket->code,
            'item_id' => $ticket->item_id,
            'inventory_item_id' => $ticket->inventory_item_id,
            'sku' => $ticket->inventoryItem?->sku,
            'item_name' => $ticket->item?->name,
            'maintenance_type' => $ticket->maintenance_type?->value,
            'status' => $ticket->status?->value,
            'reported_date' => $ticket->reported_date?->toDateString(),
            'started_date' => $ticket->started_date?->toDateString(),
            'expected_return_date' => $ticket->expected_return_date?->toDateString(),
            'return_date' => $ticket->return_date?->toDateString(),
            'vendor' => $ticket->vendor,
            'cost' => $ticket->cost,
            'remarks' => $ticket->remarks,
        ];
    }
}
