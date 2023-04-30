
<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BatchRouteController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\InventoryAdjustmentController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LogisticsController;
use App\Http\Controllers\ManufactureController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ParameterController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductionCategoryController;
use App\Http\Controllers\PurchasedInvProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RejectionReasonController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SpecificationSheetController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPermissionController;
use App\Http\Controllers\VendorController;
use App\Models\BatchRoute;
use App\Models\EmailTemplate;
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
    Route::post('template/{id}', [TemplateController::class, 'update']);
    Route::post('destroy-template/{id}', [TemplateController::class, 'destroy']);

    Route::get('product', [ProductController::class, 'index']);
    Route::get('product/{id}', [ProductController::class, 'show']);
    Route::post('product', [ProductController::class, 'store']);
    Route::post('product/{id}', [ProductController::class, 'update']);
    Route::post('destroy-product/{id}', [ProductController::class, 'destroy']);

    Route::get('route', [BatchRouteController::class, 'index']);
    Route::get('route/{id}', [BatchRouteController::class, 'show']);
    Route::post('route', [BatchRouteController::class, 'store']);
    Route::post('route/{id}', [BatchRouteController::class, 'update']);

    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{id}', [OrderController::class, 'show']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('order-operator-options', [OrderController::class, 'operatorOption']);
    Route::get('order-search-options', [OrderController::class, 'searchOption']);
    Route::get('order-status-options', [OrderController::class, 'statusOption']);
    Route::get('order-store-options', [OrderController::class, 'storeOption']);
    Route::get('order-ship-options', [OrderController::class, 'shipOption']);
    Route::post('order-product-options', [ProductController::class, 'productOption']);

    Route::get('email-template', [EmailTemplateController::class, 'index']);
    Route::get('email-template/{id}', [EmailTemplateController::class, 'show']);
    Route::post('email-template', [EmailTemplateController::class, 'store']);
    Route::post('email-template/{id}', [EmailTemplateController::class, 'update']);
    Route::post('destroy-email-template/{id}', [EmailTemplateController::class, 'destroy']);

    Route::get('specification-product', [SpecificationSheetController::class, 'index']);
    Route::get('specification-product/{id}', [SpecificationSheetController::class, 'show']);
    Route::post('specification-product', [SpecificationSheetController::class, 'store']);
    Route::post('specification-product/{id}', [SpecificationSheetController::class, 'update']);
    Route::post('destroy-specification-product/{id}', [SpecificationSheetController::class, 'destroy']);

    Route::get('config-child-sku', [LogisticsController::class, 'index']);
    Route::get('update-config-child-sku', [LogisticsController::class, 'updateSKUs']);

    Route::post('send-bulk-email', [EmailTemplateController::class, 'sendBulkEmail']);

    Route::get('role-options', [RoleController::class, 'roleOption']);
    Route::get('section-options', [InventoryController::class, 'sectionOption']);
    Route::get('stock-options', [PurchasedInvProductController::class, 'stockOption']);
    Route::get('vendor-options', [PurchasedInvProductController::class, 'vendorOption']);
    Route::get('product-options/{id}', [PurchasedInvProductController::class, 'productOptionbyVendor']);
    Route::get('section-options', [SectionController::class, 'sectionOption']);
    Route::get('station-options', [StationController::class, 'stationOption']);
    Route::get('template-options', [TemplateController::class, 'templateOption']);
    Route::get('searchable-fields-options', [SpecificationSheetController::class, 'searchableFieldsOption']);
    Route::get('production-categories-options', [SpecificationSheetController::class, 'productionCategoriesOption']);
    Route::get('web-image-status-options', [SpecificationSheetController::class, 'webImageStatusOption']);
    Route::get('make-sample-data-options', [SpecificationSheetController::class, 'makeSampleDataOption']);
    Route::get('statuses-options', [SpecificationSheetController::class, 'statusesOption']);
    Route::get('production-category-options', [ProductionCategoryController::class, 'productionCategoryOption']);
    Route::get('product-searchable-fields-options', [ProductController::class, 'searchableFieldsOption']);
    Route::get('batch-route-options', [BatchRouteController::class, 'batchRouteOptions']);
    Route::get('stock-image-options', [InventoryController::class, 'stockImageOption']);
    Route::get('email-template-options', [EmailTemplateController::class, 'emailTemplateOptions']);
    Route::get('email-template-keywords', [EmailTemplateController::class, 'emailTemplateKeywords']);

    Route::get('skus', [SpecificationSheetController::class, 'skus']);
});
