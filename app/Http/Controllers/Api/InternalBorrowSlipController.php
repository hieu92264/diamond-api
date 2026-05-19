<?php

namespace App\Http\Controllers\Api;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\InventoryItemStatus;
use App\Enums\InventoryReferenceType;
use App\Enums\InventoryTransactionType;
use App\Enums\InternalBorrowStatus;
use App\Models\Employee;
use App\Models\InternalBorrowDetail;
use App\Models\InternalBorrowDetailItem;
use App\Models\InternalBorrowSlip;
use App\Models\InternalIncident;
use App\Models\InventoryCondition;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class InternalBorrowSlipController extends WorkflowController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::enum(InternalBorrowStatus::class)],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')],
            'keyword' => ['nullable', 'string', 'max:255'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
        ]);

        $query = InternalBorrowSlip::query()
            ->with(['employee', 'warehouse', 'details']);

        foreach (['employee_id', 'warehouse_id'] as $field) {
            if (! empty($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (! empty($data['keyword'])) {
            $keyword = trim($data['keyword']);
            $query->where(function ($builder) use ($keyword): void {
                $builder
                    ->where('code', 'like', "%{$keyword}%")
                    ->orWhere('employee_name', 'like', "%{$keyword}%");
            });
        }

        if (! empty($data['from_date'])) {
            $query->whereDate('borrow_date', '>=', $data['from_date']);
        }

        if (! empty($data['to_date'])) {
            $query->whereDate('borrow_date', '<=', $data['to_date']);
        }

        return $this->rawSuccess(
            $query->latest('id')
                ->get()
                ->map(fn (InternalBorrowSlip $slip) => $this->transformSlipSummary($slip))
                ->all()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'borrow_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:borrow_date'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ]);

        $employee = Employee::query()->findOrFail($data['employee_id']);

        $slip = InternalBorrowSlip::query()->create([
            'code' => $this->nextCode(InternalBorrowSlip::class, 'PMN'),
            'employee_id' => $employee->id,
            'employee_name' => $employee->full_name,
            'warehouse_id' => $data['warehouse_id'],
            'borrow_date' => $data['borrow_date'],
            'due_date' => $data['due_date'],
            'status' => InternalBorrowStatus::PENDING->value,
            'created_by' => $this->workflowUserId(),
            'purpose' => $data['purpose'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ]);

        return $this->success(
            ['slip' => $this->transformSlipDetail($slip->fresh(['employee', 'warehouse', 'details']))],
            'Tao phieu muon noi bo thanh cong.',
            Response::HTTP_CREATED
        );
    }

    public function show(int $id): JsonResponse
    {
        return $this->rawSuccess(
            $this->transformSlipDetail($this->baseSlipQuery()->findOrFail($id))
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $slip = $this->baseSlipQuery()->findOrFail($id);
        $this->ensureEditableSlip($slip);

        $data = $request->validate([
            'borrow_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ]);

        if (isset($data['borrow_date']) && isset($data['due_date']) && $data['due_date'] < $data['borrow_date']) {
            throw ValidationException::withMessages([
                'due_date' => ['Han tra phai lon hon hoac bang ngay muon.'],
            ]);
        }

        if (isset($data['borrow_date']) xor isset($data['due_date'])) {
            $borrowDate = $data['borrow_date'] ?? $slip->borrow_date?->toDateString();
            $dueDate = $data['due_date'] ?? $slip->due_date?->toDateString();

            if ($dueDate < $borrowDate) {
                throw ValidationException::withMessages([
                    'due_date' => ['Han tra phai lon hon hoac bang ngay muon.'],
                ]);
            }
        }

        $slip->update($data);

        return $this->success(
            ['slip' => $this->transformSlipDetail($this->baseSlipQuery()->findOrFail($slip->id))],
            'Cap nhat phieu muon thanh cong.'
        );
    }

    public function storeDetail(Request $request, int $id): JsonResponse
    {
        $slip = $this->baseSlipQuery()->findOrFail($id);
        $this->ensureEditableSlip($slip);

        $data = $request->validate([
            'equipment_prop_id' => ['required', 'integer', Rule::exists('equipment_props', 'id')],
            'borrowed_quantity' => ['required', 'integer', 'min:1'],
            'remarks' => ['nullable', 'string'],
        ]);

        $detail = InternalBorrowDetail::query()
            ->where('internal_borrow_slip_id', $slip->id)
            ->where('equipment_prop_id', $data['equipment_prop_id'])
            ->first();

        if ($detail !== null) {
            if ($detail->detailItems()->exists()) {
                throw ValidationException::withMessages([
                    'equipment_prop_id' => ['Dong hang da duoc gan item, khong the ghi de.'],
                ]);
            }

            $detail->update([
                'borrowed_quantity' => $data['borrowed_quantity'],
                'remarks' => $data['remarks'] ?? null,
            ]);
        } else {
            $detail = $slip->details()->create([
                'equipment_prop_id' => $data['equipment_prop_id'],
                'borrowed_quantity' => $data['borrowed_quantity'],
                'remarks' => $data['remarks'] ?? null,
            ]);
        }

        return $this->success(
            ['detail' => $this->transformDetail($this->detailQuery()->findOrFail($detail->id))],
            'Them dong hang thanh cong.',
            Response::HTTP_CREATED
        );
    }

    public function updateDetail(Request $request, int $id, int $detailId): JsonResponse
    {
        $slip = $this->baseSlipQuery()->findOrFail($id);
        $this->ensureEditableSlip($slip);

        $detail = $this->detailQuery()
            ->where('internal_borrow_slip_id', $slip->id)
            ->findOrFail($detailId);

        $data = $request->validate([
            'borrowed_quantity' => ['nullable', 'integer', 'min:1'],
            'remarks' => ['nullable', 'string'],
        ]);

        if (isset($data['borrowed_quantity']) && $detail->detailItems()->exists()) {
            throw ValidationException::withMessages([
                'borrowed_quantity' => ['Khong the doi so luong sau khi da gan item.'],
            ]);
        }

        $detail->update($data);

        return $this->success(
            ['detail' => $this->transformDetail($this->detailQuery()->findOrFail($detail->id))],
            'Cap nhat dong hang thanh cong.'
        );
    }

    public function destroyDetail(int $id, int $detailId): JsonResponse
    {
        $slip = $this->baseSlipQuery()->findOrFail($id);
        $this->ensureEditableSlip($slip);

        $detail = InternalBorrowDetail::query()
            ->where('internal_borrow_slip_id', $slip->id)
            ->findOrFail($detailId);

        if ($detail->detailItems()->exists() || $detail->incidents()->exists()) {
            throw ValidationException::withMessages([
                'detail_id' => ['Khong the xoa dong hang da duoc su dung.'],
            ]);
        }

        $detail->delete();

        return $this->success(['deleted' => true], 'Xoa dong hang thanh cong.');
    }

    public function assignItems(Request $request, int $id): JsonResponse
    {
        $slip = $this->baseSlipQuery()->findOrFail($id);
        $this->ensureEditableSlip($slip);

        $data = $request->validate([
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.detail_id' => ['required', 'integer'],
            'assignments.*.inventory_item_ids' => ['required', 'array', 'min:1'],
            'assignments.*.inventory_item_ids.*' => ['required', 'integer', Rule::exists('inventory', 'id')],
            'assignments.*.condition_on_borrow_id' => ['nullable', 'integer', Rule::exists('inventory_conditions', 'id')],
            'assignments.*.remarks' => ['nullable', 'string'],
        ]);

        $result = DB::transaction(function () use ($slip, $data): array {
            $details = $this->detailQuery()
                ->where('internal_borrow_slip_id', $slip->id)
                ->get()
                ->keyBy('id');

            $payload = [];

            foreach ($data['assignments'] as $assignment) {
                /** @var InternalBorrowDetail|null $detail */
                $detail = $details->get($assignment['detail_id']);

                if ($detail === null) {
                    throw ValidationException::withMessages([
                        'assignments' => ['Co dong hang khong thuoc phieu muon hien tai.'],
                    ]);
                }

                if ($detail->detailItems->contains(fn (InternalBorrowDetailItem $item) => $item->borrowed_at !== null)) {
                    throw ValidationException::withMessages([
                        'assignments' => ['Khong the gan lai item cho dong hang da checkout.'],
                    ]);
                }

                $uniqueIds = collect($assignment['inventory_item_ids'])->unique()->values()->all();
                $this->ensureAssignedCount((int) $detail->borrowed_quantity, count($uniqueIds), 'assignments');

                $items = $this->availableInventoryItemsForDetail(
                    $uniqueIds,
                    $slip->warehouse_id,
                    $detail->equipment_prop_id
                );

                $this->ensureAssignedCount((int) $detail->borrowed_quantity, $items->count(), 'assignments');

                $detail->detailItems()->delete();

                foreach ($items as $item) {
                    $detail->detailItems()->create([
                        'inventory_item_id' => $item->id,
                        'condition_on_borrow_id' => $assignment['condition_on_borrow_id'] ?? $item->inventory_condition_id,
                        'remarks' => $assignment['remarks'] ?? null,
                    ]);
                }

                $conditionLabel = null;
                $conditionId = $assignment['condition_on_borrow_id'] ?? $items->first()?->inventory_condition_id;

                if ($conditionId !== null) {
                    $conditionLabel = InventoryCondition::query()->find($conditionId)?->label;
                }

                $detail->update([
                    'condition_on_borrow' => $conditionLabel,
                ]);

                $payload[] = [
                    'detail_id' => $detail->id,
                    'assigned_count' => $detail->detailItems()->count(),
                    'detail_items' => $detail->fresh('detailItems.inventoryItem')->detailItems
                        ->map(fn (InternalBorrowDetailItem $detailItem) => $this->transformInternalBorrowDetailItem($detailItem))
                        ->all(),
                ];
            }

            return $payload;
        });

        return $this->success([
            'slip_id' => $slip->id,
            'details' => $result,
        ], 'Gan item cho phieu muon thanh cong.');
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $slip = $this->baseSlipQuery()->findOrFail($id);

        if (! in_array($slip->status, [InternalBorrowStatus::PENDING, InternalBorrowStatus::REJECTED], true)) {
            throw ValidationException::withMessages([
                'status' => ['Chi duyet duoc phieu dang cho xu ly hoac da bi tu choi.'],
            ]);
        }

        if ($slip->details()->count() === 0) {
            throw ValidationException::withMessages([
                'details' => ['Phieu muon phai co it nhat 1 dong hang truoc khi duyet.'],
            ]);
        }

        $slip->update([
            'status' => InternalBorrowStatus::APPROVED->value,
            'approved_user_id' => $this->workflowUserId(),
            'approved_at' => now(),
            'rejected_user_id' => null,
            'rejected_at' => null,
        ]);

        return $this->success([
            'slip' => $this->transformSlipDetail($this->baseSlipQuery()->findOrFail($slip->id)),
        ], 'Duyet phieu muon thanh cong.');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $slip = $this->baseSlipQuery()->findOrFail($id);
        $this->ensureEditableSlip($slip);

        $data = $request->validate([
            'reason' => ['required', 'string'],
        ]);

        $slip->update([
            'status' => InternalBorrowStatus::REJECTED->value,
            'rejected_user_id' => $this->workflowUserId(),
            'rejected_at' => now(),
            'remarks' => $data['reason'],
        ]);

        return $this->success([
            'slip' => $this->transformSlipDetail($this->baseSlipQuery()->findOrFail($slip->id)),
        ], 'Tu choi phieu muon thanh cong.');
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $slip = $this->baseSlipQuery()->findOrFail($id);

        if (! in_array($slip->status, [
            InternalBorrowStatus::PENDING,
            InternalBorrowStatus::APPROVED,
            InternalBorrowStatus::REJECTED,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => ['Chi huy duoc phieu muon chua checkout.'],
            ]);
        }

        if ($slip->detailItems()->whereNotNull('borrowed_at')->exists()) {
            throw ValidationException::withMessages([
                'status' => ['Khong the huy phieu da checkout item.'],
            ]);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string'],
        ]);

        $slip->update([
            'status' => InternalBorrowStatus::CANCELLED->value,
            'remarks' => $data['reason'] ?? $slip->remarks,
        ]);

        return $this->success([
            'slip' => $this->transformSlipDetail($this->baseSlipQuery()->findOrFail($slip->id)),
        ], 'Huy phieu muon thanh cong.');
    }

    public function checkout(Request $request, int $id): JsonResponse
    {
        $slip = $this->baseSlipQuery()->findOrFail($id);

        if ($slip->status !== InternalBorrowStatus::APPROVED) {
            throw ValidationException::withMessages([
                'status' => ['Chi checkout duoc phieu da duyet.'],
            ]);
        }

        $data = $request->validate([
            'checkout_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        $result = DB::transaction(function () use ($slip, $data): array {
            $slip = $this->baseSlipQuery()->lockForUpdate()->findOrFail($slip->id);
            $transactionIds = [];
            $checkedOutItems = 0;

            foreach ($slip->details as $detail) {
                $assignedCount = $detail->detailItems->count();
                $this->ensureAssignedCount((int) $detail->borrowed_quantity, $assignedCount, 'detail_items');

                foreach ($detail->detailItems as $detailItem) {
                    if ($detailItem->borrowed_at !== null) {
                        continue;
                    }

                    $transaction = $this->transitionInventoryItem(
                        $detailItem->inventoryItem,
                        InventoryItemStatus::RENTED,
                        InventoryTransactionType::INTERNAL_BORROW_OUT,
                        InventoryReferenceType::INTERNAL_BORROW_SLIP,
                        $slip->id,
                        $data['note'] ?? null
                    );

                    $detailItem->update([
                        'borrowed_at' => $data['checkout_at'] ?? now(),
                    ]);

                    $transactionIds[] = $transaction->id;
                    $checkedOutItems++;
                }
            }

            $this->syncInternalBorrowSlipStatus($slip->fresh('details'));

            return [
                'checked_out_items' => $checkedOutItems,
                'transaction_ids' => $transactionIds,
            ];
        });

        return $this->success([
            'slip' => $this->transformSlipDetail($this->baseSlipQuery()->findOrFail($slip->id)),
            ...$result,
        ], 'Checkout phieu muon thanh cong.');
    }

    public function returnItems(Request $request, int $id): JsonResponse
    {
        $slip = $this->baseSlipQuery()->findOrFail($id);

        if (! in_array($slip->status, [
            InternalBorrowStatus::BORROWING,
            InternalBorrowStatus::PARTIALLY_RETURNED,
            InternalBorrowStatus::OVERDUE,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => ['Chi nhan tra cho phieu dang muon.'],
            ]);
        }

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required', 'integer', Rule::exists('inventory', 'id')],
            'items.*.return_action' => ['required', Rule::in(['RETURNED', 'DAMAGED', 'LOST'])],
            'items.*.condition_on_return_id' => ['nullable', 'integer', Rule::exists('inventory_conditions', 'id')],
            'items.*.compensation_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.note' => ['nullable', 'string'],
        ]);

        $result = DB::transaction(function () use ($slip, $data): array {
            $slip = $this->baseSlipQuery()->lockForUpdate()->findOrFail($slip->id);
            $incidentIds = [];
            $transactionIds = [];
            $summary = [
                'returned_items' => 0,
                'damaged_items' => 0,
                'lost_items' => 0,
            ];

            /** @var Collection<int, InternalBorrowDetailItem> $outstanding */
            $outstanding = $slip->detailItems()
                ->with(['inventoryItem', 'borrowDetail'])
                ->whereNull('returned_at')
                ->get()
                ->keyBy('inventory_item_id');

            foreach ($data['items'] as $itemData) {
                /** @var InternalBorrowDetailItem|null $detailItem */
                $detailItem = $outstanding->get($itemData['inventory_item_id']);

                if ($detailItem === null) {
                    throw ValidationException::withMessages([
                        'items' => ['Co item khong thuoc phieu muon hoac da duoc tra truoc do.'],
                    ]);
                }

                $detail = $detailItem->borrowDetail;
                $conditionId = $itemData['condition_on_return_id'] ?? $detailItem->inventoryItem->inventory_condition_id;
                $conditionLabel = InventoryCondition::query()->find($conditionId)?->label;

                $detailItem->update([
                    'condition_on_return_id' => $conditionId,
                    'returned_at' => now(),
                    'remarks' => $itemData['note'] ?? $detailItem->remarks,
                ]);

                $action = $itemData['return_action'];

                if ($action === 'RETURNED') {
                    $detail->increment('returned_quantity');
                    $detail->update(['condition_on_return' => $conditionLabel]);

                    $transaction = $this->transitionInventoryItem(
                        $detailItem->inventoryItem,
                        InventoryItemStatus::AVAILABLE,
                        InventoryTransactionType::INTERNAL_BORROW_RETURN,
                        InventoryReferenceType::INTERNAL_BORROW_SLIP,
                        $slip->id,
                        $itemData['note'] ?? null,
                        $conditionId
                    );

                    $transactionIds[] = $transaction->id;
                    $summary['returned_items']++;
                    continue;
                }

                $incident = InternalIncident::query()->create([
                    'code' => $this->nextCode(InternalIncident::class, 'SCNB'),
                    'internal_borrow_detail_id' => $detail->id,
                    'inventory_item_id' => $detailItem->inventory_item_id,
                    'incident_description' => $itemData['note'] ?? 'Phat sinh khi tra hang.',
                    'incident_type' => $action === 'DAMAGED' ? IncidentType::DAMAGED->value : IncidentType::LOST->value,
                    'status' => IncidentStatus::OPEN->value,
                    'compensation_amount' => $itemData['compensation_amount'] ?? null,
                ]);

                if ($action === 'DAMAGED') {
                    $detail->increment('damaged_quantity');
                    $detail->update(['condition_on_return' => $conditionLabel]);

                    $transaction = $this->transitionInventoryItem(
                        $detailItem->inventoryItem,
                        InventoryItemStatus::MAINTENANCE,
                        InventoryTransactionType::MAINTENANCE_OUT,
                        InventoryReferenceType::INTERNAL_INCIDENT,
                        $incident->id,
                        $itemData['note'] ?? null,
                        $conditionId
                    );

                    $summary['damaged_items']++;
                } else {
                    $detail->increment('lost_quantity');

                    $transaction = $this->transitionInventoryItem(
                        $detailItem->inventoryItem,
                        InventoryItemStatus::LOST,
                        InventoryTransactionType::LOST_WRITE_OFF,
                        InventoryReferenceType::INTERNAL_INCIDENT,
                        $incident->id,
                        $itemData['note'] ?? null,
                        $conditionId
                    );

                    $summary['lost_items']++;
                }

                $incidentIds[] = $incident->id;
                $transactionIds[] = $transaction->id;
            }

            $this->syncInternalBorrowSlipStatus($slip->fresh('details'));

            return [
                ...$summary,
                'incident_ids' => $incidentIds,
                'transaction_ids' => $transactionIds,
            ];
        });

        return $this->success([
            'slip' => $this->transformSlipDetail($this->baseSlipQuery()->findOrFail($slip->id)),
            ...$result,
        ], 'Nhan tra phieu muon thanh cong.');
    }

    private function baseSlipQuery()
    {
        return InternalBorrowSlip::query()->with([
            'employee',
            'warehouse',
            'details.equipmentProp',
            'details.detailItems.inventoryItem',
            'details.detailItems.conditionOnBorrow',
            'details.detailItems.conditionOnReturn',
            'details.incidents.inventoryItem',
        ]);
    }

    private function detailQuery()
    {
        return InternalBorrowDetail::query()->with([
            'equipmentProp',
            'detailItems.inventoryItem',
            'detailItems.conditionOnBorrow',
            'detailItems.conditionOnReturn',
            'incidents.inventoryItem',
        ]);
    }

    private function ensureEditableSlip(InternalBorrowSlip $slip): void
    {
        if (! in_array($slip->status, [
            InternalBorrowStatus::PENDING,
            InternalBorrowStatus::APPROVED,
            InternalBorrowStatus::REJECTED,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => ['Khong the sua phieu muon o trang thai hien tai.'],
            ]);
        }
    }

    private function transformSlipSummary(InternalBorrowSlip $slip): array
    {
        $slip->loadMissing(['employee', 'warehouse', 'details']);

        return [
            'id' => $slip->id,
            'code' => $slip->code,
            'employee_id' => $slip->employee_id,
            'employee_name' => $slip->employee_name,
            'warehouse_id' => $slip->warehouse_id,
            'warehouse_name' => $slip->warehouse?->name,
            'borrow_date' => $slip->borrow_date?->toDateString(),
            'due_date' => $slip->due_date?->toDateString(),
            'return_date' => $slip->return_date?->toDateString(),
            'status' => $slip->status?->value,
            'purpose' => $slip->purpose,
            'remarks' => $slip->remarks,
            'total_details' => $slip->details->count(),
            'borrowed_quantity' => (int) $slip->details->sum('borrowed_quantity'),
            'returned_quantity' => (int) $slip->details->sum('returned_quantity'),
            'lost_quantity' => (int) $slip->details->sum('lost_quantity'),
            'damaged_quantity' => (int) $slip->details->sum('damaged_quantity'),
            'approved_at' => $slip->approved_at?->toISOString(),
            'rejected_at' => $slip->rejected_at?->toISOString(),
            'created_at' => $slip->created_at?->toISOString(),
            'updated_at' => $slip->updated_at?->toISOString(),
        ];
    }

    private function transformSlipDetail(InternalBorrowSlip $slip): array
    {
        $slip->loadMissing([
            'employee',
            'warehouse',
            'details.equipmentProp',
            'details.detailItems.inventoryItem',
            'details.detailItems.conditionOnBorrow',
            'details.detailItems.conditionOnReturn',
            'details.incidents.inventoryItem',
        ]);

        return [
            ...$this->transformSlipSummary($slip),
            'details' => $slip->details
                ->sortBy('id')
                ->values()
                ->map(fn (InternalBorrowDetail $detail) => $this->transformDetail($detail))
                ->all(),
        ];
    }

    private function transformDetail(InternalBorrowDetail $detail): array
    {
        $detail->loadMissing([
            'equipmentProp',
            'detailItems.inventoryItem',
            'detailItems.conditionOnBorrow',
            'detailItems.conditionOnReturn',
            'incidents.inventoryItem',
        ]);

        return [
            'id' => $detail->id,
            'equipment_prop_id' => $detail->equipment_prop_id,
            'item_name' => $detail->equipmentProp?->name,
            'borrowed_quantity' => $detail->borrowed_quantity,
            'returned_quantity' => $detail->returned_quantity,
            'lost_quantity' => $detail->lost_quantity,
            'damaged_quantity' => $detail->damaged_quantity,
            'condition_on_borrow' => $detail->condition_on_borrow,
            'condition_on_return' => $detail->condition_on_return,
            'remarks' => $detail->remarks,
            'detail_items' => $detail->detailItems
                ->sortBy('id')
                ->values()
                ->map(fn (InternalBorrowDetailItem $detailItem) => $this->transformInternalBorrowDetailItem($detailItem))
                ->all(),
            'incidents' => $detail->incidents
                ->sortByDesc('id')
                ->values()
                ->map(fn (InternalIncident $incident) => $this->transformInternalIncidentSummary($incident))
                ->all(),
        ];
    }
}
