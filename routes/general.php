<?php

use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('users')->middleware(['auth:api', 'role:ADMIN'])
    ->controller(UserController::class)
    ->group(function () {
        Route::get('/', 'index');
        Route::get('/{id}', 'show');
        Route::post('/create', 'store');
        Route::patch('/update/{id}', 'update');
        Route::delete('/delete/{id}', 'delete');
    });
