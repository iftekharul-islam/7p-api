
<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackorderController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\BatchRouteController;
use App\Http\Controllers\CsController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\GraphicsController;
use App\Http\Controllers\InventoryAdjustmentController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LogisticsController;
use App\Http\Controllers\ManufactureController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ParameterController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductionCategoryController;
use App\Http\Controllers\PurchasedInvProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\QcControlController;
use App\Http\Controllers\RejectionController;
use App\Http\Controllers\RejectionReasonController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\SpecificationSheetController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\StoreItemController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPermissionController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\WapController;
use App\Models\BatchRoute;
use App\Models\EmailTemplate;
use App\Models\StoreItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\File\File;

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
    Route::post('orders/{id}', [OrderController::class, 'store']);
    Route::get('batched-orders/{id}', [OrderController::class, 'batchedOrder']);
    Route::post('orders-send-email', [NotificationController::class, 'sendMail']);
    Route::post('orders-update-store', [OrderController::class, 'updateStore']);
    Route::post('orders-update_method', [OrderController::class, 'updateMethod']);
    Route::post('orders-update_shipdate', [OrderController::class, 'updateShipDate']);
    Route::post('orders-item-tracking', [ShippingController::class, 'manualShip']);
    Route::get('order-delete-item/{order_id}/{item_id}', [ItemController::class, 'deleteOrderItem']);
    Route::get('order-restore-item/{order_id}/{item_id}', [ItemController::class, 'restoreOrderItem']);

    Route::get('order-operator-options', [OrderController::class, 'operatorOption']);
    Route::get('order-search-options', [OrderController::class, 'searchOption']);
    Route::get('order-status-options', [OrderController::class, 'statusOption']);
    Route::get('order-store-options', [OrderController::class, 'storeOption']);
    Route::get('order-ship-options', [OrderController::class, 'shipOption']);
    Route::post('order-product-options', [ProductController::class, 'productOption']);
    Route::get('order-email-type-options', [OrderController::class, 'emailTypeOption']);
    Route::get('order-shipping-method-options', [OrderController::class, 'shippingMethodOption']);

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

    Route::get('customer-service', [CsController::class, 'index']);
    Route::get('customer-service-action', [CsController::class, 'actionButton']);

    Route::get('items-list', [ItemController::class, 'index']);
    Route::get('graphic-items-list', [ItemController::class, 'indexGraphic']);
    Route::get('item-search-options', [ItemController::class, 'searchOption']);
    Route::get('item-status-options', [ItemController::class, 'statusOption']);
    Route::get('item-store-options', [ItemController::class, 'storeOption']);

    Route::get('config-child-sku', [LogisticsController::class, 'index']);
    Route::get('update-config-child-sku', [LogisticsController::class, 'updateSKUs']);
    Route::get('get-config-child-sku/{id}', [LogisticsController::class, 'getSKUs']);
    Route::post('update-child-sku', [LogisticsController::class, 'updateSku']);

    // Graphics API
    Route::get('preview-batches', [ItemController::class, 'getBatch']);
    Route::post('preview-batches', [ItemController::class, 'postBatch']);
    Route::get('unbatchable-items', [ItemController::class, 'unbatchableItems']);
    Route::post('add-child-sku', [LogisticsController::class, 'addChildSKU']);


    Route::get('print-sublimation', [GraphicsController::class, 'showSublimation']);
    Route::post('sublimation-print', [GraphicsController::class, 'printSublimation']);
    Route::get('print-sublimation-queues', [GraphicsController::class, 'showSublimationQueues']);
    Route::get('print-all', [GraphicsController::class, 'printAllSublimation']);

    Route::get('print-batch-summaries', [GraphicsController::class, 'showBatchSummaries']);
    Route::post('batch-print', [PrintController::class, 'showBatchPrint']);
    Route::get('move-to-production', [GraphicsController::class, 'moveToProduction']);
    Route::get('move-to-qc', [GraphicsController::class, 'moveToQC']);
    Route::get('move-show', [GraphicsController::class, 'ShowBatch']);


    // Graphics API options
    Route::get('batch-search-in-options', [ItemController::class, 'searchInOption']);
    Route::get('batch-store-options', [ItemController::class, 'batchStoreOption']);
    Route::get('print-station-options', [GraphicsController::class, 'stationOption']);

    Route::post('send-bulk-email', [EmailTemplateController::class, 'sendBulkEmail']);

    Route::get('batch-list', [BatchController::class, 'index']);
    Route::get('batch-list/{batch_number}', [BatchController::class, 'show']);
    Route::get('reject_item', [BatchController::class, 'rejectItem']);


    Route::get('move-batches', [ProductController::class, 'moveNextStation']);
    Route::get('rejects', [RejectionController::class, 'index']);
    Route::get('back-orders', [BackorderController::class, 'index']);
    Route::get('back-orders-show', [BackorderController::class, 'show']);

    // Shipping and WAP
    Route::get('must-ship-report', [ReportController::class, 'mustShipReport']);
    Route::get('quality-control', [QcControlController::class, 'index']);
    Route::get('quality-control-list', [QcControlController::class, 'showStation']);
    Route::post('quality-control-order', [QcControlController::class, 'scanIn']);
    Route::post('quality-control-order-data', [QcControlController::class, 'showBatch']);
    Route::post('quality-control-show-order', [QcControlController::class, 'showOrder']);
    Route::post('shipping-qc-order', [QcControlController::class, 'showOrder']);
    Route::get('wap', [WapController::class, 'index']);
    Route::get('wap-details', [WapController::class, 'ShowBin']);
    Route::get('reprint-wap-label', [WapController::class, 'reprintWapLabel']);
    Route::get('reject-wap-item', [RejectionController::class, 'rejectWapItem']);
    Route::get('reject-qc-item', [RejectionController::class, 'rejectQCItem']);

    Route::post('shipping-add-wap', [WapController::class, 'addItems']);
    Route::post('bad-address', [WapController::class, 'badAddress']);
    Route::post('ship-item', [ShippingController::class, 'shipItems']);

    Route::get('shipping', [ShippingController::class, 'index']);
    Route::get('shipping-search-in-options', [ShippingController::class, 'searchInOption']);
    Route::post('ship-order-returned', [ShippingController::class, 'shipmentReturned']);
    Route::get('ship-order-void', [ShippingController::class, 'voidShipment']);

    Route::get('section-reports', [ReportController::class, 'section']);
    Route::get('ship-date-reports', [ReportController::class, 'shipDate']);
    Route::get("order-items-reports", [ReportController::class, 'orderItems']);
    Route::get("sales-summary-reports", [ReportController::class, 'salesSummary']);
    Route::get('report-manufacture-options', [ReportController::class, 'manufactureOption']);
    Route::get('report-store-options', [ReportController::class, 'storeOption']);
    Route::get('report-company-options', [ReportController::class, 'companyOption']);

    Route::get('stores', [StoreController::class, 'index']);
    Route::post('stores', [StoreController::class, 'store']);
    Route::post('stores/{id}', [StoreController::class, 'show']);
    Route::post('stores-update/{id}', [StoreController::class, 'update']);
    Route::post('stores-delete/{id}', [StoreController::class, 'delete']);
    Route::get('stores-items/{id}', [StoreItemController::class, 'index']);
    Route::post('stores-items-add', [StoreItemController::class, 'store']);
    Route::post('stores-items-update', [StoreItemController::class, 'update']);
    Route::post('stores-items-delete/{id}', [StoreItemController::class, 'delete']);
    Route::get('stores-visibility/{id}', [StoreController::class, 'visible']);
    Route::get('stores-change-order/{direction}/{id}', [StoreController::class, 'sortOrder']);
    Route::get('stores-company-options', [StoreController::class, 'companyOption']);
    Route::get('stores-input-options', [StoreController::class, 'inputOption']);
    Route::get('stores-batch-options', [StoreController::class, 'batchOption']);
    Route::get('stores-qc-options', [StoreController::class, 'qcOption']);
    Route::get('stores-confirm-options', [StoreController::class, 'confirmOption']);
    Route::get('stores-must-ship-options', [StoreController::class, 'mustShipStatusOption']);

    Route::post('import-orders-file', [StoreController::class, 'importOrdersFile']);
    Route::post('import-trcking-file', [StoreController::class, 'importTrackingFile']);
    Route::post('import-zakeke-file', [StoreController::class, 'importZakekeFile']);
    Route::get('import-order-store-options', [StoreController::class, 'orderStoreOption']);
    Route::get('import-tracking-store-options', [StoreController::class, 'trackingStoreOption']);

    Route::get('export-data', [StoreController::class, 'exportData']);
    Route::get('export-qb', [StoreController::class, 'qbExport']);
    Route::post('export-qb-csv', [StoreController::class, 'qbCsvExport']);

    Route::get('create-graphics', [GraphicsController::class, 'index']);
    Route::post('graphics-upload-file', [GraphicsController::class, 'uploadFile']);

    Route::get('sent-to-printer', [GraphicsController::class, 'sentToPrinter']);
    Route::post('reprint_graphic', [GraphicsController::class, 'reprintGraphic']);
    Route::get('reprint_bulk', [GraphicsController::class, 'reprintBulk']);
    Route::get('export_batchbulk', [BatchController::class, 'export_bulk']);

    Route::get('printer-options', [GraphicsController::class, 'printerOption']);
    Route::get('destination-options', [RejectionController::class, 'destinationOption']);
    Route::get('graphics-status-options', [GraphicsController::class, 'statusOption']);
    Route::get('role-options', [RoleController::class, 'roleOption']);
    Route::get('section-options', [InventoryController::class, 'sectionOption']);
    Route::get('stock-options', [PurchasedInvProductController::class, 'stockOption']);
    Route::get('vendor-options', [PurchasedInvProductController::class, 'vendorOption']);
    Route::get('product-options/{id}', [PurchasedInvProductController::class, 'productOptionbyVendor']);
    Route::get('station-options', [StationController::class, 'stationOption']);
    Route::get('custom-station-options', [StationController::class, 'customStationOption']);
    Route::get('advance-station-options', [StationController::class, 'advanceStationOption']);
    Route::get('template-options', [TemplateController::class, 'templateOption']);
    Route::get('searchable-fields-options', [SpecificationSheetController::class, 'searchableFieldsOption']);
    Route::get('production-categories-options', [SpecificationSheetController::class, 'productionCategoriesOption']);
    Route::get('web-image-status-options', [SpecificationSheetController::class, 'webImageStatusOption']);
    Route::get('make-sample-data-options', [SpecificationSheetController::class, 'makeSampleDataOption']);
    Route::get('statuses-options', [SpecificationSheetController::class, 'statusesOption']);
    Route::get('production-category-options', [ProductionCategoryController::class, 'productionCategoryOption']);
    Route::get('product-searchable-fields-options', [ProductController::class, 'searchableFieldsOption']);
    Route::get('batch-route-options', [BatchRouteController::class, 'batchRouteOptions']);
    Route::get('batch-status-options', [BatchRouteController::class, 'statusesOptions']);
    Route::get('stock-image-options', [InventoryController::class, 'stockImageOption']);
    Route::get('email-template-options', [EmailTemplateController::class, 'emailTemplateOptions']);
    Route::get('reason-options', [RejectionReasonController::class, 'reasonOption']);

    Route::get('email-template-keywords', [EmailTemplateController::class, 'emailTemplateKeywords']);

    Route::get('skus', [SpecificationSheetController::class, 'skus']);
});


Route::get('test', function () {
    return response()->json([
        'data' => 'http://7p.test/wasatch/staging-2/673216.xml',
        // 'data' => $downloadfile,
        'message' => 'Printed by',
        'status' => 201
    ], 201);
});
