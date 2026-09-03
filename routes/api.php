<?php

use App\Http\Controllers\Api\QrScanController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\TransferController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public — ลูกค้าสแกน QR (ไม่ต้อง login ระบบหลังบ้าน)
|--------------------------------------------------------------------------
*/
Route::prefix('qr')->middleware('throttle:30,1')->group(function () {
    Route::get('{token}', [QrScanController::class, 'show']);
    Route::post('{token}/redeem', [QrScanController::class, 'redeem']);
});

/*
|--------------------------------------------------------------------------
| Backend — ต้อง login (sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // สต๊อก
    Route::prefix('stock')->group(function () {
        Route::get('/', [StockController::class, 'index']);
        Route::get('low', [StockController::class, 'lowStock']);
        Route::get('movements', [StockController::class, 'movements']);
        Route::get('tree/{node}', [StockController::class, 'tree']);
        Route::post('adjust', [StockController::class, 'adjust']);
    });

    // ใบโอนสินค้า
    Route::prefix('transfers')->group(function () {
        Route::get('/', [TransferController::class, 'index']);
        Route::post('/', [TransferController::class, 'store']);
        Route::post('{transfer}/approve', [TransferController::class, 'approve']);
        Route::post('{transfer}/reject',  [TransferController::class, 'reject']);
        Route::post('{transfer}/ship',    [TransferController::class, 'ship']);
        Route::post('{transfer}/receive', [TransferController::class, 'receive']);
    });
});
