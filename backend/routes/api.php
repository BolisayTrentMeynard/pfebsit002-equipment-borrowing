<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BorrowRequestController;
use App\Http\Controllers\Api\CustodyController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\ManualHandlingController;
use App\Http\Controllers\Api\NotifierReceiverController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// Week 15 hardening (DEF-06): ids in the URL are digits only, so "1abc" is a 404, not request 1.
Route::pattern('borrowRequest', '[0-9]+');
Route::pattern('reminderNotification', '[0-9]+');

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Receiver of signed notifier messages: authenticated by the message signature, not by a user token.
Route::post('/notifier/receive', [NotifierReceiverController::class, 'receive'])->middleware('throttle:60,1');

Route::middleware('api.auth')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/equipment', [EquipmentController::class, 'index']);

    Route::get('/requests', [BorrowRequestController::class, 'index']);
    Route::get('/requests/{borrowRequest}', [BorrowRequestController::class, 'show']);

    Route::post('/requests', [BorrowRequestController::class, 'store'])
        ->middleware('role:'.User::ROLE_BORROWER);

    Route::get('/custody', [CustodyController::class, 'index'])
        ->middleware('role:'.User::ROLE_APPROVER.','.User::ROLE_STAFF);

    Route::middleware('role:'.User::ROLE_APPROVER)->group(function () {
        Route::get('/manual-handling', [ManualHandlingController::class, 'index']);
        Route::post('/manual-handling/{reminderNotification}/resolve', [ManualHandlingController::class, 'resolve']);
        Route::post('/requests/{borrowRequest}/approve', [BorrowRequestController::class, 'approve']);
        Route::post('/requests/{borrowRequest}/reject', [BorrowRequestController::class, 'reject']);
    });

    Route::middleware('role:'.User::ROLE_STAFF)->group(function () {
        Route::post('/requests/{borrowRequest}/release', [BorrowRequestController::class, 'release']);
        Route::post('/requests/{borrowRequest}/return', [BorrowRequestController::class, 'returnItem']);
    });
});
