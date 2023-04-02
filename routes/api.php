
<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InventoryController;
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

    Route::get('purchased-products', [PurchasedInvProductController::class, 'index']);
    Route::get('purchased-products/{id}', [PurchasedInvProductController::class, 'show']);
    Route::post('purchased-products', [PurchasedInvProductController::class, 'store']);
    Route::post('purchased-products/{id}', [PurchasedInvProductController::class, 'update']);
    Route::post('destroy-purchased-products/{id}', [PurchasedInvProductController::class, 'destroy']);

    Route::get('vendors', [VendorController::class, 'index']);
    Route::get('vendors/{id}', [VendorController::class, 'show']);
    Route::post('vendors', [VendorController::class, 'store']);
    Route::post('vendors/{id}', [VendorController::class, 'update']);
    Route::post('destroy-vendors/{id}', [VendorController::class, 'destroy']);

    Route::get('purchased-orders', [PurchaseController::class, 'index']);
    Route::get('purchased-orders/{id}', [PurchaseController::class, 'show']);
    Route::post('purchased-orders', [PurchaseController::class, 'store']);
    Route::post('purchased-orders/{id}', [PurchaseController::class, 'update']);
    Route::post('destroy-purchased-orders/{id}', [PurchaseController::class, 'destroy']);
    Route::post('receive-purchased-orders', [PurchaseController::class, 'receiveOrders']);

    Route::get('inventories', [InventoryController::class, 'index']);
    Route::get('inventories/{id}', [InventoryController::class, 'show']);
    Route::post('inventories', [InventoryController::class, 'store']);
    Route::post('inventories/{id}', [InventoryController::class, 'update']);
    Route::post('destroy-inventories/{id}', [InventoryController::class, 'destroy']);
    Route::post('update-bin-&-qty', [InventoryController::class, 'updateBinQty']);

    Route::get('role-options', [RoleController::class, 'roleOption']);
    Route::get('section-options', [InventoryController::class, 'sectionOption']);
    Route::get('stock-options', [PurchasedInvProductController::class, 'stockOption']);
    Route::get('vendor-options', [PurchasedInvProductController::class, 'vendorOption']);
    Route::get('product-options/{id}', [PurchasedInvProductController::class, 'productOptionbyVendor']);
});
