<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BorrowRequestController;
use App\Http\Controllers\Api\EquipmentController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('api.auth')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/equipment', [EquipmentController::class, 'index']);

    Route::get('/requests', [BorrowRequestController::class, 'index']);
    Route::get('/requests/{borrowRequest}', [BorrowRequestController::class, 'show']);

    Route::post('/requests', [BorrowRequestController::class, 'store'])
        ->middleware('role:'.User::ROLE_BORROWER);

    Route::middleware('role:'.User::ROLE_APPROVER)->group(function () {
        Route::post('/requests/{borrowRequest}/approve', [BorrowRequestController::class, 'approve']);
        Route::post('/requests/{borrowRequest}/reject', [BorrowRequestController::class, 'reject']);
    });

    Route::middleware('role:'.User::ROLE_STAFF)->group(function () {
        Route::post('/requests/{borrowRequest}/release', [BorrowRequestController::class, 'release']);
        Route::post('/requests/{borrowRequest}/return', [BorrowRequestController::class, 'returnItem']);
    });
});
