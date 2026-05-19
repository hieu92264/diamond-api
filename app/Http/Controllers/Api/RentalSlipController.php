<?php

namespace App\Http\Controllers\Api;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\InventoryItemStatus;
use App\Enums\InventoryReferenceType;
use App\Enums\InventoryTransactionType;
use App\Enums\PaymentMethod;
use App\Enums\RentalPaymentType;
use App\Enums\RentalStatus;
use App\Models\Customer;
use App\Models\InventoryCondition;
use App\Models\RentalDetail;
use App\Models\RentalDetailItem;
use App\Models\RentalIncident;
use App\Models\RentalPayment;
use App\Models\RentalSlip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class RentalSlipController extends WorkflowController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::enum(RentalStatus::class)],
            'payment_status' => ['nullable', Rule::in(['UNPAID', 'PARTIALLY_PAID', 'PAID', 'REFUNDED'])],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')],
            'keyword' => ['nullable', 'string', 'max:255'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
        ]);

        $query = RentalSlip::query()
            ->with(['customer', 'warehouse', 'details', 'payments']);

        foreach (['customer_id', 'warehouse_id', 'payment_status'] as $field) {
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
                    ->orWhere('customer_name', 'like', "%{$keyword}%")
                    ->orWhere('customer_phone', 'like', "%{$keyword}%");
            });
        }

        if (! empty($data['from_date'])) {
            $query->whereDate('rental_date', '>=', $data['from_date']);
        }

        if (! empty($data['to_date'])) {
            $query->whereDate('rental_date', '<=', $data['to_date']);
        }

        return $this->rawSuccess(
            $query->latest('id')
                ->get()
                ->map(fn (RentalSlip $slip) => $this->transformSlipSummary($slip))
                ->all()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'rental_date' => ['required', 'date'],
            'start_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:start_date'],
            'total_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'terms_and_conditions' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
        ]);

        $customer = Customer::query()->findOrFail($data['customer_id']);

        $slip = RentalSlip::query()->create([
            'code' => $this->nextCode(RentalSlip::class, 'PTH'),
            'customer_id' => $customer->id,
            'customer_name' => $customer->full_name,
            'customer_phone' => $customer->phone,
            'warehouse_id' => $data['warehouse_id'],
            'rental_date' => $data['rental_date'],
            'start_date' => $data['start_date'],
            'due_date' => $data['due_date'],
            'total_discount_amount' => $data['total_discount_amount'] ?? 0,
            'payment_status' => 'UNPAID',
            'status' => RentalStatus::DRAFT->value,
            'created_by' => $this->workflowUserId(),
            'terms_and_conditions' => $data['terms_and_conditions'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ]);

        $this->syncRentalSlipFinancials($slip);

        return $this->success(
            ['slip' => $this->transformSlipDetail($this->baseSlipQuery()->findOrFail($slip->id))],
            'Tao phieu thue thanh cong.',
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
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'total_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'terms_and_conditions' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
        ]);

        if (isset($data['start_date']) xor isset($data['due_date'])) {
            $startDate = $data['start_date'] ?? $slip->start_date?->toDateString();
            $dueDate = $data['due_date'] ?? $slip->due_date?->toDateString();

            if ($dueDate < $startDate) {
                throw ValidationException::withMessages([
                    'due_date' => ['Han tra phai lon hon hoac bang ngay bat dau.'],
                ]);
            }
        }

        if (isset($data['start_date'], $data['due_date']) && $data['due_date'] < $data['start_date']) {
            throw ValidationException::withMessages([
                'due_date' => ['Han tra phai lon hon hoac bang ngay bat dau.'],
            ]);
        }

        $slip->update($data);
        $this->syncRentalSlipFinancials($slip->fresh());

        return $this->success(
            ['slip' => $this->transformSlipDetail($this->baseSlipQuery()->findOrFail($slip->id))],
            'Cap nhat phieu thue thanh cong.'
        );
    }

    public function storeDetail(Request $request, int $id): JsonResponse
    {
        $slip = $this->baseSlipQuery()->findOrFail($id);
        $this->ensureEditableSlip($slip);

        $data = $request->validate([
            'equipment_prop_id' => ['required', 'integer', Rule::exists('equipment_props', 'id')],
            'rented_quantity' => ['required', 'integer', 'min:1'],
            'rental_unit_price' => ['required', 'numeric', 'min:0'],
            'rental_days' => ['required', 'integer', 'min:1'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
        ]);

        $detail = RentalDetail::query()
            ->where('rental_slip_id', $slip->id)
            ->where('equipment_prop_id', $data['equipment_prop_id'])
            ->first();

        if ($detail !== null) {
            if ($detail->detailItems()->exists()) {
                throw ValidationException::withMessages([
                    'equipment_prop_id' => ['Dong hang da duoc gan item, khong the ghi de.'],
                ]);
            }

            $detail->update([
                'rented_quantity' => $data['rented_quantity'],
                'rental_unit_price' => $data['rental_unit_price'],
                'rental_days' => $data['rental_days'],
                'deposit_amount' => $data['deposit_amount'] ?? 0,
                'remarks' => $data['remarks'] ?? null,
            ]);
        } else {
            $detail = $slip->details()->create([
                'equipment_prop_id' => $data['equipment_prop_id'],
                'rented_quantity' => $data['rented_quantity'],
                'rental_unit_price' => $data['rental_unit_price'],
                'rental_days' => $data['rental_days'],
                'deposit_amount' => $data['deposit_amount'] ?? 0,
                'remarks' => $data['remarks'] ?? null,
            ]);
        }

        $this->syncRentalSlipFinancials($slip->fresh());

        return $this->success(
            ['detail' => $this->transformDetail($this->detailQuery()->findOrFail($detail->id))],
            'Them dong hang thue thanh cong.',
            Response::HTTP_CREATED
        );
    }

    public function updateDetail(Request $request, int $id, int $detailId): JsonResponse
    {
        $slip = $this->baseSlipQuery()->findOrFail($id);
        $this->ensureEditableSlip($slip);

        $detail = $this->detailQuery()
            ->where('rental_slip_id', $slip->id)
            ->findOrFail($detailId);

        $data = $request->validate([
            'rental_unit_price' => ['nullable', 'numeric', 'min:0'],
            'rental_days' => ['nullable', 'integer', 'min:1'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'rented_quantity' => ['nullable', 'integer', 'min:1'],
            'remarks' => ['nullable', 'string'],
        ]);

        if (isset($data['rented_quantity']) && $detail->detailItems()->exists()) {
            throw ValidationException::withMessages([
                'rented_quantity' => ['Khong the doi so luong sau khi da gan item.'],
            ]);
        }

        $detail->update($data);
        $this->syncRentalSlipFinancials($slip->fresh());

        return $this->success(
            ['detail' => $this->transformDetail($this->detailQuery()->findOrFail($detail->id))],
            'Cap nhat dong hang thue thanh cong.'
        );
    }

    public function destroyDetail(int $id, int $detailId): JsonResponse
    {
        $slip = $this->baseSlipQuery()->findOrFail($id);
        $this->ensureEditableSlip($slip);

        $detail = RentalDetail::query()
            ->where('rental_slip_id', $slip->id)
            ->findOrFail($detailId);

        if ($detail->detailItems()->exists() || $detail->incidents()->exists()) {
            throw ValidationException::withMessages([
                'detail_id' => ['Khong the xoa dong hang da duoc su dung.'],
            ]);
        }

        $detail->delete();
        $this->syncRentalSlipFinancials($slip->fresh());

        return $this->success(['deleted' => true], 'Xoa dong hang thue thanh cong.');
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
            'assignments.*.condition_on_rent_id' => ['nullable', 'integer', Rule::exists('inventory_conditions', 'id')],
            'assignments.*.remarks' => ['nullable', 'string'],
        ]);

        $result = DB::transaction(function () use ($slip, $data): array {
            $details = $this->detailQuery()
                ->where('rental_slip_id', $slip->id)
                ->get()
                ->keyBy('id');

            $payload = [];

            foreach ($data['assignments'] as $assignment) {
                /** @var RentalDetail|null $detail */
                $detail = $details->get($assignment['detail_id']);

                if ($detail === null) {
                    throw ValidationException::withMessages([
                        'assignments' => ['Co dong hang khong thuoc phieu thue hien tai.'],
                    ]);
                }

                if ($detail->detailItems->contains(fn (RentalDetailItem $item) => $item->rented_at !== null)) {
                    throw ValidationException::withMessages([
                        'assignments' => ['Khong the gan lai item cho dong hang da checkout.'],
                    ]);
                }

                $uniqueIds = collect($assignment['inventory_item_ids'])->unique()->values()->all();
                $this->ensureAssignedCount((int) $detail->rented_quantity, count($uniqueIds), 'assignments');

                $items = $this->availableInventoryItemsForDetail(
                    $uniqueIds,
                    $slip->warehouse_id,
                    $detail->equipment_prop_id
                );

                $this->ensureAssignedCount((int) $detail->rented_quantity, $items->count(), 'assignments');

                $detail->detailItems()->delete();

                foreach ($items as $item) {
                    $detail->detailItems()->create([
                        'inventory_item_id' => $item->id,
                        'condition_on_rent_id' => $assignment['condition_on_rent_id'] ?? $item->inventory_condition_id,
                        'remarks' => $assignment['remarks'] ?? null,
                    ]);
                }

                $conditionLabel = null;
                $conditionId = $assignment['condition_on_rent_id'] ?? $items->first()?->inventory_condition_id;

                if ($conditionId !== null) {
                    $conditionLabel = InventoryCondition::query()->find($conditionId)?->label;
                }

                $detail->update([
                    'condition_on_rent' => $conditionLabel,
                ]);

                $payload[] = [
                    'detail_id' => $detail->id,
                    'assigned_count' => $detail->detailItems()->count(),
                    'detail_items' => $detail->fresh('detailItems.inventoryItem')->detailItems
                        ->map(fn (RentalDetailItem $detailItem) => $this->transformRentalDetailItem($detailItem))
                        ->all(),
                ];
            }

            return $payload;
        });

        return $this->success([
            'slip_id' => $slip->id,
            'details' => $result,
        ], 'Gan item cho phieu thue thanh cong.');
    }

    public function submit(int $id): JsonResponse
    {
        $slip = $this->baseSlipQuery()->findOrFail($id);

        if ($slip->status !== RentalStatus::DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Chi gui duyet duoc phieu o trang thai nhap.'],
            ]);
        }

        if ($slip->details()->count() === 0) {
            throw ValidationException::withMessages([
                'details' => ['Phieu thue phai co it nhat 1 dong hang.'],
            ]);
        }

        $slip->update([
            'status' => RentalStatus::PENDING_APPROVAL->value,
        ]);

        return $this->success([
            'slip' => $this->transformSlipDetail($this->baseSlipQuery()->findOrFail($slip->id)),
        ], 'Gui duyet phieu thue thanh cong.');
    }

    public function approve(int $id): JsonResponse
    {
        $slip = $this->baseSlipQuery()->findOrFail($id);

        if ($slip->status !== RentalStatus::PENDING_APPROVAL) {
            throw ValidationException::withMessages([
                'status' => ['Chi duyet duoc phieu dang cho duyet.'],
            ]);
        }

        $slip->update([
            'status' => RentalStatus::APPROVED->value,
            'approved_user_id' => $this->workflowUserId(),
            'approved_at' => now(),
        ]);

        return $this->success([
            'slip' => $this->transformSlipDetail($this->baseSlipQuery()->findOrFail($slip->id)),
        ], 'Duyet phieu thue thanh cong.');
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $slip = $this->baseSlipQuery()->findOrFail($id);

        if (! in_array($slip->status, [
            RentalStatus::DRAFT,
            RentalStatus::PENDING_APPROVAL,
            RentalStatus::APPROVED,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => ['Chi huy duoc phieu thue chua checkout.'],
            ]);
        }

        if ($slip->detailItems()->whereNotNull('rented_at')->exists()) {
            throw ValidationException::withMessages([
                'status' => ['Khong the huy phieu da checkout item.'],
            ]);
        }

        if ((float) $slip->paid_amount > 0) {
            throw ValidationException::withMessages([
                'paid_amount' => ['Khong the huy phieu da phat sinh thanh toan.'],
            ]);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string'],
        ]);

        $slip->update([
            'status' => RentalStatus::CANCELLED->value,
            'remarks' => $data['reason'] ?? $slip->remarks,
        ]);

        return $this->success([
            'slip' => $this->transformSlipDetail($this->baseSlipQuery()->findOrFail($slip->id)),
        ], 'Huy phieu thue thanh cong.');
    }

    public function checkout(Request $request, int $id): JsonResponse
    {
        $slip = $this->baseSlipQuery()->findOrFail($id);

        if ($slip->status !== RentalStatus::APPROVED) {
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
                $this->ensureAssignedCount((int) $detail->rented_quantity, $assignedCount, 'detail_items');

                foreach ($detail->detailItems as $detailItem) {
                    if ($detailItem->rented_at !== null) {
                        continue;
                    }

                    $transaction = $this->transitionInventoryItem(
                        $detailItem->inventoryItem,
                        InventoryItemStatus::RENTED,
                        InventoryTransactionType::RENTAL_OUT,
                        InventoryReferenceType::RENTAL_SLIP,
                        $slip->id,
                        $data['note'] ?? null
                    );

                    $detailItem->update([
                        'rented_at' => $data['checkout_at'] ?? now(),
                    ]);

                    $transactionIds[] = $transaction->id;
                    $checkedOutItems++;
                }
            }

            $this->syncRentalSlipStatus($slip->fresh('details'));

            return [
                'checked_out_items' => $checkedOutItems,
                'transaction_ids' => $transactionIds,
            ];
        });

        return $this->success([
            'slip' => $this->transformSlipDetail($this->baseSlipQuery()->findOrFail($slip->id)),
            ...$result,
        ], 'Checkout phieu thue thanh cong.');
    }

    public function returnItems(Request $request, int $id): JsonResponse
    {
        $slip = $this->baseSlipQuery()->findOrFail($id);

        if (! in_array($slip->status, [
            RentalStatus::ACTIVE,
            RentalStatus::PARTIALLY_RETURNED,
            RentalStatus::OVERDUE,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => ['Chi nhan tra cho phieu dang thue.'],
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

            /** @var Collection<int, RentalDetailItem> $outstanding */
            $outstanding = $slip->detailItems()
                ->with(['inventoryItem', 'rentalDetail'])
                ->whereNull('returned_at')
                ->get()
                ->keyBy('inventory_item_id');

            foreach ($data['items'] as $itemData) {
                /** @var RentalDetailItem|null $detailItem */
                $detailItem = $outstanding->get($itemData['inventory_item_id']);

                if ($detailItem === null) {
                    throw ValidationException::withMessages([
                        'items' => ['Co item khong thuoc phieu thue hoac da duoc tra truoc do.'],
                    ]);
                }

                $detail = $detailItem->rentalDetail;
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
                        InventoryTransactionType::RENTAL_RETURN,
                        InventoryReferenceType::RENTAL_SLIP,
                        $slip->id,
                        $itemData['note'] ?? null,
                        $conditionId
                    );

                    $transactionIds[] = $transaction->id;
                    $summary['returned_items']++;
                    continue;
                }

                $incident = RentalIncident::query()->create([
                    'code' => $this->nextCode(RentalIncident::class, 'SCTH'),
                    'rental_detail_id' => $detail->id,
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
                        InventoryReferenceType::RENTAL_INCIDENT,
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
                        InventoryReferenceType::RENTAL_INCIDENT,
                        $incident->id,
                        $itemData['note'] ?? null,
                        $conditionId
                    );

                    $summary['lost_items']++;
                }

                $incidentIds[] = $incident->id;
                $transactionIds[] = $transaction->id;
            }

            $this->syncRentalSlipFinancials($slip->fresh());
            $this->syncRentalSlipStatus($slip->fresh('details'));

            $slip = $slip->fresh();

            return [
                ...$summary,
                'incident_ids' => $incidentIds,
                'transaction_ids' => $transactionIds,
                'total_compensation_amount' => $slip->total_compensation_amount,
                'final_amount' => $slip->final_amount,
                'remaining_amount' => $slip->remaining_amount,
            ];
        });

        return $this->success([
            'slip' => $this->transformSlipDetail($this->baseSlipQuery()->findOrFail($slip->id)),
            ...$result,
        ], 'Nhan tra phieu thue thanh cong.');
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $slip = $this->baseSlipQuery()->findOrFail($id);
        $this->syncRentalSlipFinancials($slip->fresh());
        $slip = $this->baseSlipQuery()->findOrFail($id);

        if ($slip->status !== RentalStatus::RETURNED) {
            throw ValidationException::withMessages([
                'status' => ['Chi dong duoc phieu da tra xong.'],
            ]);
        }

        $hasOpenIncident = $slip->details
            ->flatMap(fn (RentalDetail $detail) => $detail->incidents)
            ->contains(fn (RentalIncident $incident) => in_array($incident->status, [
                IncidentStatus::OPEN,
                IncidentStatus::PROCESSING,
            ], true));

        if ($hasOpenIncident) {
            throw ValidationException::withMessages([
                'incidents' => ['Khong the dong phieu khi su co chua hoan tat.'],
            ]);
        }

        if ((float) $slip->remaining_amount > 0) {
            throw ValidationException::withMessages([
                'remaining_amount' => ['Khong the dong phieu khi con cong no.'],
            ]);
        }

        $data = $request->validate([
            'note' => ['nullable', 'string'],
        ]);

        $slip->update([
            'status' => RentalStatus::CLOSED->value,
            'remarks' => $data['note'] ?? $slip->remarks,
        ]);

        return $this->success([
            'slip' => $this->transformSlipDetail($this->baseSlipQuery()->findOrFail($slip->id)),
        ], 'Dong phieu thue thanh cong.');
    }

    public function payments(int $id): JsonResponse
    {
        $slip = $this->baseSlipQuery()->findOrFail($id);
        $this->syncRentalSlipFinancials($slip->fresh());

        return $this->rawSuccess([
            'payments' => $slip->fresh('payments')->payments
                ->sortByDesc('payment_date')
                ->values()
                ->map(fn (RentalPayment $payment) => $this->transformRentalPaymentSummary($payment))
                ->all(),
            'summary' => [
                'final_amount' => $slip->fresh()->final_amount,
                'paid_amount' => $slip->fresh()->paid_amount,
                'remaining_amount' => $slip->fresh()->remaining_amount,
                'payment_status' => $slip->fresh()->payment_status?->value,
            ],
        ]);
    }

    public function storePayment(Request $request, int $id): JsonResponse
    {
        $slip = $this->baseSlipQuery()->findOrFail($id);

        if (in_array($slip->status, [RentalStatus::CANCELLED, RentalStatus::CLOSED], true)) {
            throw ValidationException::withMessages([
                'status' => ['Khong the ghi nhan thanh toan cho phieu da dong hoac da huy.'],
            ]);
        }

        $data = $request->validate([
            'payment_type' => ['required', Rule::enum(RentalPaymentType::class)],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);

        $payment = $slip->payments()->create([
            'payment_type' => $data['payment_type'],
            'payment_date' => $data['payment_date'],
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'] ?? null,
            'reference_no' => $data['reference_no'] ?? null,
            'received_by' => $this->workflowUserId(),
            'note' => $data['note'] ?? null,
        ]);

        $this->syncRentalSlipFinancials($slip->fresh());

        return $this->success([
            'payment' => $this->transformRentalPaymentSummary($payment->fresh()),
            'summary' => [
                'final_amount' => $slip->fresh()->final_amount,
                'paid_amount' => $slip->fresh()->paid_amount,
                'remaining_amount' => $slip->fresh()->remaining_amount,
                'payment_status' => $slip->fresh()->payment_status?->value,
            ],
        ], 'Ghi nhan thanh toan thanh cong.', Response::HTTP_CREATED);
    }

    private function baseSlipQuery()
    {
        return RentalSlip::query()->with([
            'customer',
            'warehouse',
            'details.equipmentProp',
            'details.detailItems.inventoryItem',
            'details.detailItems.conditionOnRent',
            'details.detailItems.conditionOnReturn',
            'details.incidents.inventoryItem',
            'payments',
        ]);
    }

    private function detailQuery()
    {
        return RentalDetail::query()->with([
            'equipmentProp',
            'detailItems.inventoryItem',
            'detailItems.conditionOnRent',
            'detailItems.conditionOnReturn',
            'incidents.inventoryItem',
        ]);
    }

    private function ensureEditableSlip(RentalSlip $slip): void
    {
        if (! in_array($slip->status, [
            RentalStatus::DRAFT,
            RentalStatus::PENDING_APPROVAL,
            RentalStatus::APPROVED,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => ['Khong the sua phieu thue o trang thai hien tai.'],
            ]);
        }
    }

    private function transformSlipSummary(RentalSlip $slip): array
    {
        $slip->loadMissing(['customer', 'warehouse', 'details', 'payments']);

        return [
            'id' => $slip->id,
            'code' => $slip->code,
            'customer_id' => $slip->customer_id,
            'customer_name' => $slip->customer_name,
            'customer_phone' => $slip->customer_phone,
            'warehouse_id' => $slip->warehouse_id,
            'warehouse_name' => $slip->warehouse?->name,
            'rental_date' => $slip->rental_date?->toDateString(),
            'start_date' => $slip->start_date?->toDateString(),
            'due_date' => $slip->due_date?->toDateString(),
            'return_date' => $slip->return_date?->toDateString(),
            'deposit_amount' => $slip->deposit_amount,
            'total_rental_amount' => $slip->total_rental_amount,
            'total_compensation_amount' => $slip->total_compensation_amount,
            'total_discount_amount' => $slip->total_discount_amount,
            'final_amount' => $slip->final_amount,
            'paid_amount' => $slip->paid_amount,
            'remaining_amount' => $slip->remaining_amount,
            'payment_status' => $slip->payment_status?->value,
            'status' => $slip->status?->value,
            'terms_and_conditions' => $slip->terms_and_conditions,
            'remarks' => $slip->remarks,
            'total_details' => $slip->details->count(),
            'rented_quantity' => (int) $slip->details->sum('rented_quantity'),
            'returned_quantity' => (int) $slip->details->sum('returned_quantity'),
            'lost_quantity' => (int) $slip->details->sum('lost_quantity'),
            'damaged_quantity' => (int) $slip->details->sum('damaged_quantity'),
            'approved_at' => $slip->approved_at?->toISOString(),
            'created_at' => $slip->created_at?->toISOString(),
            'updated_at' => $slip->updated_at?->toISOString(),
        ];
    }

    private function transformSlipDetail(RentalSlip $slip): array
    {
        $slip->loadMissing([
            'customer',
            'warehouse',
            'details.equipmentProp',
            'details.detailItems.inventoryItem',
            'details.detailItems.conditionOnRent',
            'details.detailItems.conditionOnReturn',
            'details.incidents.inventoryItem',
            'payments',
        ]);

        return [
            ...$this->transformSlipSummary($slip),
            'details' => $slip->details
                ->sortBy('id')
                ->values()
                ->map(fn (RentalDetail $detail) => $this->transformDetail($detail))
                ->all(),
            'payments' => $slip->payments
                ->sortByDesc('payment_date')
                ->values()
                ->map(fn (RentalPayment $payment) => $this->transformRentalPaymentSummary($payment))
                ->all(),
        ];
    }

    private function transformDetail(RentalDetail $detail): array
    {
        $detail->loadMissing([
            'equipmentProp',
            'detailItems.inventoryItem',
            'detailItems.conditionOnRent',
            'detailItems.conditionOnReturn',
            'incidents.inventoryItem',
        ]);

        return [
            'id' => $detail->id,
            'equipment_prop_id' => $detail->equipment_prop_id,
            'item_name' => $detail->equipmentProp?->name,
            'rented_quantity' => $detail->rented_quantity,
            'returned_quantity' => $detail->returned_quantity,
            'lost_quantity' => $detail->lost_quantity,
            'damaged_quantity' => $detail->damaged_quantity,
            'rental_unit_price' => $detail->rental_unit_price,
            'rental_days' => $detail->rental_days,
            'line_rental_amount' => $detail->line_rental_amount,
            'deposit_amount' => $detail->deposit_amount,
            'compensation_amount' => $detail->compensation_amount,
            'condition_on_rent' => $detail->condition_on_rent,
            'condition_on_return' => $detail->condition_on_return,
            'remarks' => $detail->remarks,
            'detail_items' => $detail->detailItems
                ->sortBy('id')
                ->values()
                ->map(fn (RentalDetailItem $detailItem) => $this->transformRentalDetailItem($detailItem))
                ->all(),
            'incidents' => $detail->incidents
                ->sortByDesc('id')
                ->values()
                ->map(fn (RentalIncident $incident) => $this->transformRentalIncidentSummary($incident))
                ->all(),
        ];
    }
}
