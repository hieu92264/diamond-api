<?php

namespace App\Http\Controllers\Api;

use App\Enums\IncidentStatus;
use App\Enums\InventoryItemStatus;
use App\Enums\InventoryReferenceType;
use App\Enums\InventoryTransactionType;
use App\Enums\InternalBorrowStatus;
use App\Enums\PaymentStatus;
use App\Enums\RentalStatus;
use App\Http\Controllers\Controller;
use App\Models\InternalBorrowDetailItem;
use App\Models\InternalBorrowSlip;
use App\Models\InternalIncident;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\MaintenanceTicket;
use App\Models\RentalDetailItem;
use App\Models\RentalIncident;
use App\Models\RentalPayment;
use App\Models\RentalSlip;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

abstract class WorkflowController extends Controller
{
    protected function workflowUserId(): ?int
    {
        return auth('api')->id();
    }

    protected function nextCode(string $modelClass, string $prefix): string
    {
        $latestId = (int) $modelClass::query()->max('id');

        return sprintf('%s-%05d', $prefix, $latestId + 1);
    }

    protected function availableInventoryItemsForDetail(array $inventoryItemIds, int $warehouseId, int $equipmentPropId): Collection
    {
        return InventoryItem::query()
            ->whereIn('id', $inventoryItemIds)
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $equipmentPropId)
            ->where('status', InventoryItemStatus::AVAILABLE->value)
            ->whereHas('inventoryCondition', fn ($query) => $query->where('rentable', true))
            ->lockForUpdate()
            ->get();
    }

    protected function availableInventoryCount(InventoryItem $item): int
    {
        return InventoryItem::query()
            ->where('item_id', $item->item_id)
            ->where('warehouse_id', $item->warehouse_id)
            ->where('status', InventoryItemStatus::AVAILABLE->value)
            ->count();
    }

    protected function transitionInventoryItem(
        InventoryItem $item,
        InventoryItemStatus $status,
        InventoryTransactionType $transactionType,
        InventoryReferenceType $referenceType,
        int $referenceId,
        ?string $note = null,
        ?int $inventoryConditionId = null
    ): InventoryTransaction {
        $before = $this->availableInventoryCount($item);

        $payload = [
            'status' => $status->value,
        ];

        if ($inventoryConditionId !== null) {
            $payload['inventory_condition_id'] = $inventoryConditionId;
        }

        $item->update($payload);
        $item = $item->fresh();

        $after = $this->availableInventoryCount($item);

        return InventoryTransaction::query()->create([
            'equipment_prop_id' => $item->item_id,
            'inventory_item_id' => $item->id,
            'warehouse_id' => $item->warehouse_id,
            'transaction_type' => $transactionType->value,
            'quantity' => 1,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'reference_type' => $referenceType->value,
            'reference_id' => $referenceId,
            'note' => $note,
            'performed_by' => $this->workflowUserId(),
        ]);
    }

    protected function syncInternalBorrowSlipStatus(InternalBorrowSlip $slip): void
    {
        $slip->loadMissing('details');

        $borrowed = (int) $slip->details->sum('borrowed_quantity');
        $completed = (int) $slip->details->sum(fn ($detail) => $detail->returned_quantity + $detail->lost_quantity + $detail->damaged_quantity);

        $status = $slip->status;
        $returnDate = $slip->return_date;

        if ($borrowed > 0 && $completed >= $borrowed) {
            $status = InternalBorrowStatus::RETURNED->value;
            $returnDate = $returnDate ?? today();
        } elseif ($completed > 0) {
            $status = InternalBorrowStatus::PARTIALLY_RETURNED->value;
        } elseif ($slip->due_date !== null && Carbon::parse($slip->due_date)->isPast()) {
            $status = InternalBorrowStatus::OVERDUE->value;
        } else {
            $status = InternalBorrowStatus::BORROWING->value;
        }

        $slip->update([
            'status' => $status,
            'return_date' => $returnDate,
        ]);
    }

    protected function syncRentalSlipFinancials(RentalSlip $slip): void
    {
        $slip->loadMissing(['details.incidents', 'payments']);

        foreach ($slip->details as $detail) {
            $lineRentalAmount = round((float) $detail->rented_quantity * (float) $detail->rental_unit_price * (float) $detail->rental_days, 2);
            $compensationAmount = (float) $detail->incidents->sum(fn (RentalIncident $incident) => (float) ($incident->compensation_amount ?? 0));

            $detail->update([
                'line_rental_amount' => $lineRentalAmount,
                'compensation_amount' => $compensationAmount,
            ]);
        }

        $slip = $slip->fresh(['details', 'payments']);
        $totalRental = (float) $slip->details->sum('line_rental_amount');
        $totalCompensation = (float) $slip->details->sum('compensation_amount');
        $depositAmount = (float) $slip->details->sum('deposit_amount');
        $paidAmount = (float) $slip->payments->sum('amount');
        $discountAmount = (float) $slip->total_discount_amount;
        $finalAmount = max(round($totalRental + $totalCompensation - $discountAmount, 2), 0);
        $remainingAmount = max(round($finalAmount - $paidAmount, 2), 0);

        $paymentStatus = match (true) {
            $finalAmount <= 0 => PaymentStatus::PAID->value,
            $paidAmount <= 0 => PaymentStatus::UNPAID->value,
            $remainingAmount <= 0 => PaymentStatus::PAID->value,
            default => PaymentStatus::PARTIALLY_PAID->value,
        };

        $slip->update([
            'deposit_amount' => $depositAmount,
            'total_rental_amount' => $totalRental,
            'total_compensation_amount' => $totalCompensation,
            'final_amount' => $finalAmount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'payment_status' => $paymentStatus,
        ]);
    }

    protected function syncRentalSlipStatus(RentalSlip $slip): void
    {
        $slip->loadMissing('details');

        $rented = (int) $slip->details->sum('rented_quantity');
        $completed = (int) $slip->details->sum(fn ($detail) => $detail->returned_quantity + $detail->lost_quantity + $detail->damaged_quantity);

        $status = $slip->status;
        $returnDate = $slip->return_date;

        if ($rented > 0 && $completed >= $rented) {
            $status = RentalStatus::RETURNED->value;
            $returnDate = $returnDate ?? today();
        } elseif ($completed > 0) {
            $status = RentalStatus::PARTIALLY_RETURNED->value;
        } elseif ($slip->due_date !== null && Carbon::parse($slip->due_date)->isPast()) {
            $status = RentalStatus::OVERDUE->value;
        } else {
            $status = RentalStatus::ACTIVE->value;
        }

        $slip->update([
            'status' => $status,
            'return_date' => $returnDate,
        ]);
    }

    protected function ensureAssignedCount(int $expectedQuantity, int $assignedCount, string $field = 'inventory_item_ids'): void
    {
        if ($assignedCount !== $expectedQuantity) {
            throw ValidationException::withMessages([
                $field => ["So luong item duoc gan phai bang {$expectedQuantity}."],
            ]);
        }
    }

    protected function transformInventoryItemSummary(InventoryItem $item): array
    {
        $item->loadMissing(['item.itemCategory', 'inventoryCondition', 'warehouse']);

        return [
            'id' => $item->id,
            'sku' => $item->sku,
            'item_id' => $item->item_id,
            'item_name' => $item->item?->name,
            'item_type' => $item->item_type?->value,
            'size' => $item->size,
            'warehouse_id' => $item->warehouse_id,
            'warehouse_name' => $item->warehouse?->name,
            'inventory_condition_id' => $item->inventory_condition_id,
            'inventory_condition_label' => $item->inventoryCondition?->label,
            'status' => $item->status?->value,
        ];
    }

    protected function transformInternalBorrowDetailItem(InternalBorrowDetailItem $detailItem): array
    {
        $detailItem->loadMissing(['inventoryItem.inventoryCondition', 'conditionOnBorrow', 'conditionOnReturn']);

        return [
            'id' => $detailItem->id,
            'inventory_item_id' => $detailItem->inventory_item_id,
            'sku' => $detailItem->inventoryItem?->sku,
            'condition_on_borrow_id' => $detailItem->condition_on_borrow_id,
            'condition_on_return_id' => $detailItem->condition_on_return_id,
            'borrowed_at' => $detailItem->borrowed_at?->toISOString(),
            'returned_at' => $detailItem->returned_at?->toISOString(),
            'remarks' => $detailItem->remarks,
        ];
    }

    protected function transformRentalDetailItem(RentalDetailItem $detailItem): array
    {
        $detailItem->loadMissing(['inventoryItem.inventoryCondition', 'conditionOnRent', 'conditionOnReturn']);

        return [
            'id' => $detailItem->id,
            'inventory_item_id' => $detailItem->inventory_item_id,
            'sku' => $detailItem->inventoryItem?->sku,
            'condition_on_rent_id' => $detailItem->condition_on_rent_id,
            'condition_on_return_id' => $detailItem->condition_on_return_id,
            'rented_at' => $detailItem->rented_at?->toISOString(),
            'returned_at' => $detailItem->returned_at?->toISOString(),
            'remarks' => $detailItem->remarks,
        ];
    }

    protected function transformInternalIncidentSummary(InternalIncident $incident): array
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

    protected function transformRentalIncidentSummary(RentalIncident $incident): array
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

    protected function transformRentalPaymentSummary(RentalPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'payment_type' => $payment->payment_type?->value,
            'payment_date' => $payment->payment_date?->toISOString(),
            'amount' => $payment->amount,
            'payment_method' => $payment->payment_method?->value,
            'reference_no' => $payment->reference_no,
            'note' => $payment->note,
        ];
    }

    protected function transformMaintenanceTicketSummary(MaintenanceTicket $ticket): array
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

    protected function transformInventoryTransactionSummary(InventoryTransaction $transaction): array
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
}
