<?php

namespace App\Http\Controllers\Api;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\InventoryItemStatus;
use App\Enums\InventoryReferenceType;
use App\Enums\InventoryTransactionType;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Models\InternalBorrowDetail;
use App\Models\InternalIncident;
use App\Models\InventoryItem;
use App\Models\MaintenanceTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class InternalIncidentController extends WorkflowController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::enum(IncidentStatus::class)],
            'incident_type' => ['nullable', Rule::enum(IncidentType::class)],
            'inventory_item_id' => ['nullable', 'integer', Rule::exists('inventory', 'id')],
        ]);

        $query = $this->baseQuery();

        foreach (['inventory_item_id'] as $field) {
            if (! empty($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (! empty($data['incident_type'])) {
            $query->where('incident_type', $data['incident_type']);
        }

        return $this->rawSuccess(
            $query->latest('id')
                ->get()
                ->map(fn (InternalIncident $incident) => $this->transformIncident($incident))
                ->all()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'internal_borrow_detail_id' => ['required', 'integer', Rule::exists('internal_borrow_details', 'id')],
            'inventory_item_id' => ['required', 'integer', Rule::exists('inventory', 'id')],
            'incident_type' => ['required', Rule::enum(IncidentType::class)],
            'incident_description' => ['required', 'string'],
            'compensation_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $detail = InternalBorrowDetail::query()
            ->with(['borrowSlip', 'detailItems'])
            ->findOrFail($data['internal_borrow_detail_id']);

        $belongsToDetail = $detail->detailItems->contains('inventory_item_id', (int) $data['inventory_item_id']);

        if (! $belongsToDetail) {
            throw ValidationException::withMessages([
                'inventory_item_id' => ['Item khong thuoc dong hang muon nay.'],
            ]);
        }

        $incident = DB::transaction(function () use ($detail, $data): InternalIncident {
            $incident = InternalIncident::query()->create([
                'code' => $this->nextCode(InternalIncident::class, 'SCNB'),
                'internal_borrow_detail_id' => $detail->id,
                'inventory_item_id' => $data['inventory_item_id'],
                'incident_description' => $data['incident_description'],
                'incident_type' => $data['incident_type'],
                'status' => IncidentStatus::OPEN->value,
                'compensation_amount' => $data['compensation_amount'] ?? null,
            ]);

            $item = InventoryItem::query()->findOrFail($data['inventory_item_id']);

            if ($data['incident_type'] === IncidentType::DAMAGED && $item->status !== InventoryItemStatus::MAINTENANCE) {
                $this->transitionInventoryItem(
                    $item,
                    InventoryItemStatus::MAINTENANCE,
                    InventoryTransactionType::MAINTENANCE_OUT,
                    InventoryReferenceType::INTERNAL_INCIDENT,
                    $incident->id,
                    $data['incident_description']
                );
            }

            if ($data['incident_type'] === IncidentType::LOST && $item->status !== InventoryItemStatus::LOST) {
                $this->transitionInventoryItem(
                    $item,
                    InventoryItemStatus::LOST,
                    InventoryTransactionType::LOST_WRITE_OFF,
                    InventoryReferenceType::INTERNAL_INCIDENT,
                    $incident->id,
                    $data['incident_description']
                );
            }

            return $incident;
        });

        return $this->success(
            ['incident' => $this->transformIncident($this->baseQuery()->findOrFail($incident->id))],
            'Tao su co noi bo thanh cong.',
            Response::HTTP_CREATED
        );
    }

    public function show(int $id): JsonResponse
    {
        return $this->rawSuccess($this->transformIncident($this->baseQuery()->findOrFail($id)));
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        $incident = $this->baseQuery()->findOrFail($id);

        $data = $request->validate([
            'resolution' => ['required', 'string'],
            'compensation_amount' => ['nullable', 'numeric', 'min:0'],
            'resolved_at' => ['nullable', 'date'],
        ]);

        $incident->update([
            'status' => IncidentStatus::RESOLVED->value,
            'resolution' => $data['resolution'],
            'compensation_amount' => $data['compensation_amount'] ?? $incident->compensation_amount,
            'resolved_by_id' => $this->workflowUserId(),
            'resolved_at' => $data['resolved_at'] ?? now(),
        ]);

        return $this->success(
            ['incident' => $this->transformIncident($this->baseQuery()->findOrFail($incident->id))],
            'Xu ly su co noi bo thanh cong.'
        );
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $incident = $this->baseQuery()->findOrFail($id);

        if ($incident->status !== IncidentStatus::RESOLVED) {
            throw ValidationException::withMessages([
                'status' => ['Chi dong duoc su co da resolve.'],
            ]);
        }

        $data = $request->validate([
            'note' => ['nullable', 'string'],
        ]);

        $incident->update([
            'status' => IncidentStatus::CLOSED->value,
            'resolution' => $data['note'] ?? $incident->resolution ?? 'Closed',
            'resolved_by_id' => $incident->resolved_by_id ?? $this->workflowUserId(),
            'resolved_at' => $incident->resolved_at ?? now(),
        ]);

        return $this->success(
            ['incident' => $this->transformIncident($this->baseQuery()->findOrFail($incident->id))],
            'Dong su co noi bo thanh cong.'
        );
    }

    public function createMaintenanceTicket(Request $request, int $id): JsonResponse
    {
        $incident = $this->baseQuery()->findOrFail($id);

        if ($incident->incident_type === IncidentType::LOST) {
            throw ValidationException::withMessages([
                'incident_type' => ['Khong the tao phieu sua chua cho item bi mat.'],
            ]);
        }

        $data = $request->validate([
            'maintenance_type' => ['required', Rule::enum(MaintenanceType::class)],
            'reported_date' => ['required', 'date'],
            'expected_return_date' => ['nullable', 'date'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ]);

        $ticket = DB::transaction(function () use ($incident, $data): MaintenanceTicket {
            $inventoryItem = $incident->inventoryItem;

            $ticket = MaintenanceTicket::query()->create([
                'code' => $this->nextCode(MaintenanceTicket::class, 'PBH'),
                'item_id' => $inventoryItem?->item_id ?? $incident->borrowDetail?->equipment_prop_id,
                'inventory_item_id' => $incident->inventory_item_id,
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
                    $data['remarks'] ?? $incident->incident_description
                );
            }

            $incident->update([
                'status' => IncidentStatus::PROCESSING->value,
            ]);

            return $ticket;
        });

        return $this->success([
            'incident' => $this->transformIncident($this->baseQuery()->findOrFail($incident->id)),
            'maintenance_ticket' => $this->transformMaintenanceTicketSummary($ticket->fresh()),
        ], 'Tao phieu bao hanh thanh cong.', Response::HTTP_CREATED);
    }

    public function writeOff(Request $request, int $id): JsonResponse
    {
        $incident = $this->baseQuery()->findOrFail($id);
        $data = $request->validate([
            'note' => ['nullable', 'string'],
        ]);

        $transactionId = DB::transaction(function () use ($incident, $data): ?int {
            $transaction = null;

            if ($incident->inventoryItem !== null) {
                $targetStatus = $incident->incident_type === IncidentType::DAMAGED
                    ? InventoryItemStatus::DISPOSED
                    : InventoryItemStatus::LOST;

                $transactionType = $incident->incident_type === IncidentType::DAMAGED
                    ? InventoryTransactionType::DAMAGED_WRITE_OFF
                    : InventoryTransactionType::LOST_WRITE_OFF;

                $transaction = $this->transitionInventoryItem(
                    $incident->inventoryItem,
                    $targetStatus,
                    $transactionType,
                    InventoryReferenceType::INTERNAL_INCIDENT,
                    $incident->id,
                    $data['note'] ?? $incident->incident_description
                );
            }

            $incident->update([
                'status' => IncidentStatus::CLOSED->value,
                'resolution' => $data['note'] ?? $incident->resolution ?? 'Write-off',
                'resolved_by_id' => $this->workflowUserId(),
                'resolved_at' => $incident->resolved_at ?? now(),
            ]);

            return $transaction?->id;
        });

        return $this->success([
            'incident' => $this->transformIncident($this->baseQuery()->findOrFail($incident->id)),
            'inventory_item' => $incident->inventoryItem ? $this->transformInventoryItemSummary($incident->inventoryItem->fresh()) : null,
            'transaction_id' => $transactionId,
        ], 'Ghi nhan thanh ly su co thanh cong.');
    }

    private function baseQuery()
    {
        return InternalIncident::query()->with([
            'inventoryItem',
            'borrowDetail.borrowSlip',
            'borrowDetail.equipmentProp',
        ]);
    }

    private function transformIncident(InternalIncident $incident): array
    {
        $incident->loadMissing([
            'inventoryItem',
            'borrowDetail.borrowSlip',
            'borrowDetail.equipmentProp',
        ]);

        $tickets = $incident->inventory_item_id === null
            ? collect()
            : MaintenanceTicket::query()
                ->where('inventory_item_id', $incident->inventory_item_id)
                ->latest('id')
                ->limit(3)
                ->get();

        return [
            ...$this->transformInternalIncidentSummary($incident),
            'detail' => [
                'slip_id' => $incident->borrowDetail?->internal_borrow_slip_id,
                'slip_code' => $incident->borrowDetail?->borrowSlip?->code,
                'detail_id' => $incident->internal_borrow_detail_id,
                'item_name' => $incident->borrowDetail?->equipmentProp?->name,
                'sku' => $incident->inventoryItem?->sku,
            ],
            'resolution' => $incident->resolution,
            'maintenance_tickets' => $tickets
                ->map(fn (MaintenanceTicket $ticket) => $this->transformMaintenanceTicketSummary($ticket))
                ->all(),
        ];
    }
}
