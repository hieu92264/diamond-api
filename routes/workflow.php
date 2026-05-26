<?php

use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\InternalBorrowSlipController;
use App\Http\Controllers\Api\InternalIncidentController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\LoanFormController;
use App\Http\Controllers\Api\MaintenanceTicketController;
use App\Http\Controllers\Api\RentalIncidentController;
use App\Http\Controllers\Api\RentalSlipController;
use App\Http\Controllers\Api\ReturnFormController;
use App\Http\Controllers\Api\StatisticsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function (): void {
    Route::get('statistics', [StatisticsController::class, 'index']);

    Route::prefix('customers')->controller(CustomerController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::get('/{id}', 'show');
    });

    Route::prefix('inventory')->controller(InventoryController::class)->group(function (): void {
        Route::get('/{id}', 'show');
        Route::get('/{id}/timeline', 'timeline');
    });

    Route::get('inventory-transactions', [InventoryController::class, 'transactions']);

    Route::prefix('internal-borrow-slips')->controller(InternalBorrowSlipController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::patch('/{id}', 'update');
        Route::post('/{id}/details', 'storeDetail');
        Route::patch('/{id}/details/{detailId}', 'updateDetail');
        Route::delete('/{id}/details/{detailId}', 'destroyDetail');
        Route::post('/{id}/assign-items', 'assignItems');
        Route::post('/{id}/approve', 'approve');
        Route::post('/{id}/reject', 'reject');
        Route::post('/{id}/cancel', 'cancel');
        Route::post('/{id}/checkout', 'checkout');
        Route::post('/{id}/return', 'returnItems');
    });

    Route::prefix('rental-slips')->controller(RentalSlipController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::patch('/{id}', 'update');
        Route::post('/{id}/details', 'storeDetail');
        Route::patch('/{id}/details/{detailId}', 'updateDetail');
        Route::delete('/{id}/details/{detailId}', 'destroyDetail');
        Route::post('/{id}/assign-items', 'assignItems');
        Route::post('/{id}/submit', 'submit');
        Route::post('/{id}/approve', 'approve');
        Route::post('/{id}/cancel', 'cancel');
        Route::post('/{id}/checkout', 'checkout');
        Route::post('/{id}/return', 'returnItems');
        Route::post('/{id}/close', 'close');
        Route::get('/{id}/payments', 'payments');
        Route::post('/{id}/payments', 'storePayment');
    });

    Route::prefix('loan-forms')->controller(LoanFormController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
        Route::post('/{id}/items', 'addItems');
        Route::post('/{id}/confirm-deposit', 'confirmDeposit');
        Route::post('/{id}/checkout', 'checkout');
        Route::post('/{id}/cancel', 'cancel');
    });

    Route::prefix('loan-form-items')->controller(LoanFormController::class)->group(function (): void {
        Route::patch('/{id}', 'updateItem');
        Route::delete('/{id}', 'destroyItem');
    });

    Route::prefix('return-forms')->controller(ReturnFormController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
        Route::post('/{id}/items', 'addItems');
        Route::post('/{id}/inspect', 'inspect');
        Route::post('/{id}/complete', 'complete');
    });

    Route::prefix('return-form-items')->controller(ReturnFormController::class)->group(function (): void {
        Route::patch('/{id}', 'updateItem');
        Route::delete('/{id}', 'destroyItem');
    });

    Route::prefix('penalty-forms')->controller(BillingController::class)->group(function (): void {
        Route::get('/', 'penaltyIndex');
        Route::post('/', 'penaltyStore');
        Route::get('/{id}', 'penaltyShow');
        Route::patch('/{id}', 'penaltyUpdate');
        Route::delete('/{id}', 'penaltyDestroy');
        Route::post('/{id}/issue', 'penaltyIssue');
        Route::post('/{id}/pay', 'penaltyPay');
    });

    Route::prefix('invoices')->controller(BillingController::class)->group(function (): void {
        Route::get('/', 'invoiceIndex');
        Route::post('/', 'invoiceStore');
        Route::get('/{id}', 'invoiceShow');
        Route::patch('/{id}', 'invoiceUpdate');
        Route::delete('/{id}', 'invoiceDestroy');
        Route::post('/{id}/issue', 'invoiceIssue');
        Route::post('/{id}/pay', 'invoicePay');
    });

    Route::prefix('internal-incidents')->controller(InternalIncidentController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::post('/{id}/resolve', 'resolve');
        Route::post('/{id}/close', 'close');
        Route::post('/{id}/create-maintenance-ticket', 'createMaintenanceTicket');
        Route::post('/{id}/write-off', 'writeOff');
    });

    Route::prefix('rental-incidents')->controller(RentalIncidentController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::post('/{id}/resolve', 'resolve');
        Route::post('/{id}/close', 'close');
        Route::post('/{id}/create-maintenance-ticket', 'createMaintenanceTicket');
        Route::post('/{id}/write-off', 'writeOff');
    });

    Route::prefix('maintenance-tickets')->controller(MaintenanceTicketController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::post('/{id}/start', 'start');
        Route::post('/{id}/complete', 'complete');
        Route::post('/{id}/cancel', 'cancel');
        Route::post('/{id}/return-to-stock', 'returnToStock');
    });
});
