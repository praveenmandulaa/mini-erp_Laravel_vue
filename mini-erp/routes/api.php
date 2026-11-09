<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\SalesOrderController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\NotificationController;
use App\Models\Supplier;

Route::middleware('auth:sanctum')->group(function () {

    // Authentication
    Route::get('/auth/me', [
        AuthController::class, 'me',
    ]);

    // Products: authenticated users can read
    Route::get('/products', [
        ProductController::class, 'index',
    ]);

    Route::get('/products/{product}', [
        ProductController::class, 'show',
    ]);

    // Products: only admins can modify
    Route::middleware('role:admin')->group(function () {
        Route::post('/products', [
            ProductController::class, 'store',
        ]);

        Route::put('/products/{product}', [
            ProductController::class, 'update',
        ]);

        Route::patch('/products/{product}', [
            ProductController::class, 'update',
        ]);

        Route::delete('/products/{product}', [
            ProductController::class, 'destroy',
        ]);
    });

    // Supplier lookup for the purchase form
    Route::get('/suppliers', function () {
        return response()->json([
            'data' => Supplier::query()
                ->orderBy('name')
                ->get(),
        ]);
    });

    // Purchases
    Route::get('/purchase-orders', [
        PurchaseOrderController::class, 'index',
    ]);

    Route::post('/purchase-orders', [
        PurchaseOrderController::class, 'store',
    ]);

    Route::get('/purchase-orders/{purchaseOrder}', [
        PurchaseOrderController::class, 'show',
    ]);

    Route::post('/purchase-orders/{purchaseOrder}/receive', [
        PurchaseOrderController::class, 'receive',
    ]);

    // Sales
    Route::get('/sales-orders', [
        SalesOrderController::class, 'index',
    ]);

    Route::post('/sales-orders', [
        SalesOrderController::class, 'store',
    ]);

    Route::get('/sales-orders/{salesOrder}', [
        SalesOrderController::class, 'show',
    ]);

    Route::post('/sales-orders/{salesOrder}/confirm', [
        SalesOrderController::class, 'confirm',
    ]);

    // Inventory reporting
    Route::get('/inventory/summary', [
        InventoryController::class, 'summary',
    ]);

    Route::get('/inventory/movements', [
        InventoryController::class, 'movements',
    ]);

    // Notifications
    Route::get('/notifications', [
        NotificationController::class, 'index',
    ]);
});
