
<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InventoryAdjustmentController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ManufactureController;
use App\Http\Controllers\ParameterController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProductionCategoryController;
use App\Http\Controllers\PurchasedInvProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RejectionReasonController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPermissionController;
use App\Http\Controllers\VendorController;
use App\Models\InventoryAdjustment;
use App\Models\Parameter;
use App\Models\Section;
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
    Route::post('calculate-ordering', [InventoryController::class, 'calculateOrdering']);

    Route::get('view-adjustments', [InventoryAdjustmentController::class, 'viewAdjustment']);
    Route::get('adjust-inventory', [InventoryAdjustmentController::class, 'adjustInventory']);
    Route::get('production-rejects', [InventoryAdjustmentController::class, 'ProductionRejects']);
    Route::post('update-adjust-inventory', [InventoryAdjustmentController::class, 'updateAdjustInventory']);

    Route::get('sections', [SectionController::class, 'index']);
    Route::get('sections/{id}', [SectionController::class, 'show']);
    Route::post('sections', [SectionController::class, 'store']);
    Route::post('sections/{id}', [SectionController::class, 'update']);
    Route::post('destroy-sections/{id}', [SectionController::class, 'destroy']);

    Route::get('stations', [StationController::class, 'index']);
    Route::get('stations/{id}', [StationController::class, 'show']);
    Route::post('stations', [StationController::class, 'store']);
    Route::post('stations/{id}', [StationController::class, 'update']);

    Route::get('reasons', [RejectionReasonController::class, 'index']);
    Route::get('reasons/{direction}/{id}', [RejectionReasonController::class, 'sortOrder']);
    Route::post('reasons', [RejectionReasonController::class, 'store']);
    Route::post('destroy-reasons/{id}', [RejectionReasonController::class, 'destroy']);

    Route::get('parameters', [ParameterController::class, 'index']);
    Route::get('parameters/{direction}/{id}', [ParameterController::class, 'sortOrder']);
    Route::post('parameters', [ParameterController::class, 'store']);
    Route::post('destroy-parameters/{id}', [ParameterController::class, 'destroy']);

    Route::get('categories', [ProductionCategoryController::class, 'index']);
    Route::get('categories/{id}', [ProductionCategoryController::class, 'show']);
    Route::post('categories', [ProductionCategoryController::class, 'store']);
    Route::post('categories/{id}', [ProductionCategoryController::class, 'update']);
    Route::post('destroy-categories/{id}', [ProductionCategoryController::class, 'destroy']);

    Route::get('manufacture', [ManufactureController::class, 'index']);
    Route::get('manufacture/{id}', [ManufactureController::class, 'show']);
    Route::post('manufacture', [ManufactureController::class, 'store']);
    Route::post('manufacture/{id}', [ManufactureController::class, 'update']);
    Route::post('destroy-manufacture/{id}', [ManufactureController::class, 'destroy']);
    Route::get('manufacture-access/{id}', [ManufactureController::class, 'getAccess']);
    Route::post('manufacture-access', [ManufactureController::class, 'updateAccess']);

    Route::get('template', [TemplateController::class, 'index']);
    Route::get('template/{id}', [TemplateController::class, 'show']);
    Route::post('template', [TemplateController::class, 'store']);
    Route::post('manufacture/{id}', [ManufactureController::class, 'update']);
    Route::post('destroy-template/{id}', [TemplateController::class, 'destroy']);
    Route::get('manufacture-access/{id}', [ManufactureController::class, 'getAccess']);
    Route::post('manufacture-access', [ManufactureController::class, 'updateAccess']);

    Route::get('role-options', [RoleController::class, 'roleOption']);
    Route::get('section-options', [InventoryController::class, 'sectionOption']);
    Route::get('stock-options', [PurchasedInvProductController::class, 'stockOption']);
    Route::get('vendor-options', [PurchasedInvProductController::class, 'vendorOption']);
    Route::get('product-options/{id}', [PurchasedInvProductController::class, 'productOptionbyVendor']);
    Route::get('section-options', [SectionController::class, 'sectionOption']);
});
