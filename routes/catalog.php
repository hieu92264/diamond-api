<?php

use App\Http\Controllers\Api\CostumeController;
use App\Http\Controllers\Api\EquipmentPropController;
use App\Http\Controllers\Api\ImageGalleryController;
use App\Http\Controllers\Api\ItemCategoryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function (): void {
    Route::prefix('item-categories')->controller(ItemCategoryController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

    Route::get('categories', [ItemCategoryController::class, 'index']);

    Route::prefix('costumes')->controller(CostumeController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

    Route::prefix('equipment-props')->controller(EquipmentPropController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

    Route::prefix('images-gallery')->controller(ImageGalleryController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::post('/upload', 'upload');
        Route::get('/{id}', 'show');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
        Route::patch('/update/{id}', 'update');
    });

    Route::delete('images/delete/{id}', [ImageGalleryController::class, 'destroy']);
});
