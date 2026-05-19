<?php

namespace App\Http\Controllers\Api;

use App\Enums\InventoryItemStatus;
use App\Enums\InventoryReferenceType;
use App\Enums\InventoryTransactionType;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Models\EquipmentProp;
use App\Models\InventoryItem;
use App\Models\MaintenanceTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceTicketController extends WorkflowController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::enum(MaintenanceStatus::class)],
            'maintenance_type' => ['nullable', Rule::enum(MaintenanceType::class)],
            'inventory_item_id' => ['nullable', 'integer', Rule::exists('inventory', 'id')],
            'item_id' => ['nullable', 'integer', Rule::exists('equipment_props', 'id')],
        ]);

        $query = MaintenanceTicket::query()->with(['inventoryItem', 'item']);

        foreach (['inventory_item_id', 'item_id'] as $field) {
            if (! empty($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (! empty($data['maintenance_type'])) {
            $query->where('maintenance_type', $data['maintenance_type']);
        }

        return $this->rawSuccess(
            $query->latest('id')
                ->get()
                ->map(fn (MaintenanceTicket $ticket) => $this->transformTicket($ticket))
                ->all()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id' => ['nullable', 'integer', Rule::exists('equipment_props', 'id')],
            'inventory_item_id' => ['nullable', 'integer', Rule::exists('inventory', 'id')],
            'maintenance_type' => ['required', Rule::enum(MaintenanceType::class)],
            'reported_date' => ['required', 'date'],
            'expected_return_date' => ['nullable', 'date'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ]);

        if (empty($data['item_id']) && empty($data['inventory_item_id'])) {
            throw ValidationException::withMessages([
                'item_id' => ['Can cung cap item_id hoac inventory_item_id.'],
            ]);
        }

        $inventoryItem = ! empty($data['inventory_item_id'])
            ? InventoryItem::query()->findOrFail($data['inventory_item_id'])
            : null;

        $itemId = $data['item_id'] ?? $inventoryItem?->item_id;

        if ($itemId === null || ! EquipmentProp::query()->whereKey($itemId)->exists()) {
            throw ValidationException::withMessages([
                'item_id' => ['Khong xac dinh duoc vat pham can bao hanh.'],
            ]);
        }

        $ticket = DB::transaction(function () use ($data, $inventoryItem, $itemId): MaintenanceTicket {
            $ticket = MaintenanceTicket::query()->create([
                'code' => $this->nextCode(MaintenanceTicket::class, 'PBH'),
                'item_id' => $itemId,
                'inventory_item_id' => $inventoryItem?->id,
                'maintenance_type' => $data['maintenance_type'],
                'reported_date' => $data['reported_date'],
                'expected_return_date' => $data['expected_return_date'] ?? null,
                'status' => MaintenanceStatus::OPEN->value,
                'vendor' => $data['vendor'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $this->workflowUserId(),
            ]);

            if ($inventoryItem !== null && $inventoryItem->status !== InventoryItemStatus::MAINTENANCE) {
                $this->transitionInventoryItem(
                    $inventoryItem,
                    InventoryItemStatus::MAINTENANCE,
                    InventoryTransactionType::MAINTENANCE_OUT,
                    InventoryReferenceType::MAINTENANCE_TICKET,
                    $ticket->id,
                    $data['remarks'] ?? null
                );
            }

            return $ticket;
        });

        return $this->success(
            ['maintenance_ticket' => $this->transformTicket($ticket->fresh(['inventoryItem', 'item']))],
            'Tao phieu bao hanh thanh cong.',
            Response::HTTP_CREATED
        );
    }

    public function show(int $id): JsonResponse
    {
        return $this->rawSuccess(
            $this->transformTicket(MaintenanceTicket::query()->with(['inventoryItem', 'item'])->findOrFail($id))
        );
    }

    public function start(Request $request, int $id): JsonResponse
    {
        $ticket = MaintenanceTicket::query()->with(['inventoryItem', 'item'])->findOrFail($id);

        $data = $request->validate([
            'started_date' => ['required', 'date'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ]);

        $ticket->update([
            'status' => MaintenanceStatus::IN_PROGRESS->value,
            'started_date' => $data['started_date'],
            'vendor' => $data['vendor'] ?? $ticket->vendor,
            'remarks' => $data['remarks'] ?? $ticket->remarks,
        ]);

        return $this->success([
            'maintenance_ticket' => $this->transformTicket($ticket->fresh(['inventoryItem', 'item'])),
        ], 'Bat dau xu ly bao hanh thanh cong.');
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        $ticket = MaintenanceTicket::query()->with(['inventoryItem', 'item'])->findOrFail($id);

        $data = $request->validate([
            'return_date' => ['required', 'date'],
            'cost' => ['required', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
        ]);

        $ticket->update([
            'status' => MaintenanceStatus::COMPLETED->value,
            'return_date' => $data['return_date'],
            'cost' => $data['cost'],
            'remarks' => $data['remarks'] ?? $ticket->remarks,
        ]);

        return $this->success([
            'maintenance_ticket' => $this->transformTicket($ticket->fresh(['inventoryItem', 'item'])),
        ], 'Hoan tat bao hanh thanh cong.');
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $ticket = MaintenanceTicket::query()->with(['inventoryItem', 'item'])->findOrFail($id);

        if (! in_array($ticket->status, [MaintenanceStatus::OPEN, MaintenanceStatus::IN_PROGRESS], true)) {
            throw ValidationException::withMessages([
                'status' => ['Chi huy duoc phieu bao hanh dang mo hoac dang xu ly.'],
            ]);
        }

        $data = $request->validate([
            'inventory_condition_id' => ['nullable', 'integer', Rule::exists('inventory_conditions', 'id')],
            'note' => ['nullable', 'string'],
        ]);

        $transactionId = DB::transaction(function () use ($ticket, $data): ?int {
            $transaction = null;

            if ($ticket->inventoryItem !== null && $ticket->inventoryItem->status === InventoryItemStatus::MAINTENANCE) {
                if (empty($data['inventory_condition_id'])) {
                    throw ValidationException::withMessages([
                        'inventory_condition_id' => ['Can cung cap tinh trang kho khi huy phieu bao hanh.'],
                    ]);
                }

                $transaction = $this->transitionInventoryItem(
                    $ticket->inventoryItem,
                    InventoryItemStatus::AVAILABLE,
                    InventoryTransactionType::MAINTENANCE_RETURN,
                    InventoryReferenceType::MAINTENANCE_TICKET,
                    $ticket->id,
                    $data['note'] ?? $ticket->remarks,
                    $data['inventory_condition_id']
                );
            }

            $ticket->update([
                'status' => MaintenanceStatus::CANCELLED->value,
                'remarks' => $data['note'] ?? $ticket->remarks,
            ]);

            return $transaction?->id;
        });

        return $this->success([
            'maintenance_ticket' => $this->transformTicket($ticket->fresh(['inventoryItem', 'item'])),
            'inventory_item' => $ticket->inventoryItem ? $this->transformInventoryItemSummary($ticket->inventoryItem->fresh()) : null,
            'transaction_id' => $transactionId,
        ], 'Huy phieu bao hanh thanh cong.');
    }

    public function returnToStock(Request $request, int $id): JsonResponse
    {
        $ticket = MaintenanceTicket::query()->with(['inventoryItem', 'item'])->findOrFail($id);

        if ($ticket->status !== MaintenanceStatus::COMPLETED) {
            throw ValidationException::withMessages([
                'status' => ['Chi tra kho duoc khi phieu bao hanh da hoan tat.'],
            ]);
        }

        if ($ticket->inventoryItem === null) {
            throw ValidationException::withMessages([
                'inventory_item_id' => ['Phieu bao hanh nay khong gan voi inventory item cu the.'],
            ]);
        }

        $data = $request->validate([
            'inventory_condition_id' => ['required', 'integer', Rule::exists('inventory_conditions', 'id')],
            'note' => ['nullable', 'string'],
        ]);

        $transactionId = DB::transaction(function () use ($ticket, $data): int {
            $transaction = $this->transitionInventoryItem(
                $ticket->inventoryItem,
                InventoryItemStatus::AVAILABLE,
                InventoryTransactionType::MAINTENANCE_RETURN,
                InventoryReferenceType::MAINTENANCE_TICKET,
                $ticket->id,
                $data['note'] ?? $ticket->remarks,
                $data['inventory_condition_id']
            );

            if ($ticket->return_date === null) {
                $ticket->update(['return_date' => today()]);
            }

            return $transaction->id;
        });

        return $this->success([
            'maintenance_ticket' => $this->transformTicket($ticket->fresh(['inventoryItem', 'item'])),
            'inventory_item' => $this->transformInventoryItemSummary($ticket->inventoryItem->fresh()),
            'transaction_id' => $transactionId,
        ], 'Nhap lai kho sau bao hanh thanh cong.');
    }

    private function transformTicket(MaintenanceTicket $ticket): array
    {
        $ticket->loadMissing(['inventoryItem', 'item']);

        return [
            ...$this->transformMaintenanceTicketSummary($ticket),
            'inventory_item' => $ticket->inventoryItem ? $this->transformInventoryItemSummary($ticket->inventoryItem) : null,
        ];
    }
}
