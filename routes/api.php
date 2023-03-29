<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PurchasedInvProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPermissionController;
use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{id}', [UserController::class, 'show']);
    Route::post('users', [UserController::class, 'store']);
    Route::post('users/{id}', [UserController::class, 'update']);
    Route::post('user-role', [UserController::class, 'userRole']);
    Route::get('user-permission', [PermissionController::class, 'userPermission']);

    Route::get('roles', [RoleController::class, 'index']);
    Route::post('roles', [RoleController::class, 'store']);
    Route::get('roles/{id}', [RoleController::class, 'show']);
    Route::post('delete-roles/{id}', [RoleController::class, 'delete']);
    Route::post('roles-update/{id}', [RoleController::class, 'update']);
    Route::post('role-permission/{id}', [RoleController::class, 'rolePermission']);

    Route::get('permissions', [PermissionController::class, 'index']);
    Route::post('permissions', [PermissionController::class, 'store']);
    Route::get('permissions/{id}', [PermissionController::class, 'show']);
    Route::post('permissions-update/{id}', [PermissionController::class, 'update']);

    Route::get('products', [PurchasedInvProductController::class, 'index']);
    Route::get('products/{id}', [PurchasedInvProductController::class, 'show']);
    Route::post('products', [PurchasedInvProductController::class, 'store']);
    Route::post('products/{id}', [PurchasedInvProductController::class, 'update']);
    Route::post('destroy-products/{id}', [PurchasedInvProductController::class, 'destroy']);
    Route::post('add-stock-products', [PurchasedInvProductController::class, 'addStock']);

    Route::get('vendors', [VendorController::class, 'index']);
    Route::get('vendors/{id}', [VendorController::class, 'show']);
    Route::post('vendors', [VendorController::class, 'store']);
    Route::post('vendors/{id}', [VendorController::class, 'update']);
    Route::post('destroy-vendors/{id}', [VendorController::class, 'destroy']);

    Route::get('orders', [PurchaseController::class, 'index']);
    Route::get('orders/{id}', [PurchaseController::class, 'show']);
    Route::post('orders', [PurchaseController::class, 'store']);
    Route::post('orders/{id}', [PurchaseController::class, 'update']);
    Route::post('destroy-orders/{id}', [PurchaseController::class, 'destroy']);

    Route::get('role-options', [RoleController::class, 'roleOption']);
    Route::get('stock-options', [PurchasedInvProductController::class, 'stockOption']);
    Route::get('vendor-options', [PurchasedInvProductController::class, 'vendorOption']);
    Route::get('product-options/{id}', [PurchasedInvProductController::class, 'productOptionbyVendor']);
});
