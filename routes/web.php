<?php

use App\Http\Controllers\CouponController;
use App\Http\Controllers\CustomController;
use App\Http\Controllers\GraphicsController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\ZakekeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('graphics/sort', [GraphicsController::class, 'sortFiles']);
Route::get("zakeke/fetch-all/{type}", [ZakekeController::class, "fetchAll"]);
Route::get("ship_station/check", [ZakekeController::class, "shipStationCheckOrder"]);
Route::get('scripts/ship_date', [OrderController::class, 'checkShipDate']);
Route::get('prints/sendbyscript', [NotificationController::class, 'shipNotify']);
Route::get('scripts/getInput', [StoreController::class, 'retrieveData']);
Route::get('graphics/sort', [GraphicsController::class, 'sortFiles']);
Route::get('download_sure3d', [GraphicsController::class, 'downloadSure3d']);
Route::get('tasks/due', [TaskController::class, 'tasksDue']);
Route::get('stock_update', [InventoryController::class, 'updateStock']);
Route::get('auto_batch/{max_units}', [ItemController::class, 'autoBatch']);
Route::get('graphics/auto_print', [GraphicsController::class, 'autoPrint']);
Route::get('graphics/print_wasatch', [GraphicsController::class, 'printWasatch']);
Route::get('screenshot', [ReportController::class, 'screenshot']);
Route::get('downloadSure3d', [ProductController::class, 'downloadSure3d']);

Route::get('getshopifyorder', [OrderController::class, 'getShopifyOrder']);
Route::get('shopify-order/{id}', [OrderController::class, 'shopifyOrderById']);
Route::get('shopify-thumb/{order_id}/{item_id}', [OrderController::class, 'shopifyThumb']);
Route::get('update-shopify-thumb/{order_id}/{item_id}', [OrderController::class, 'updateShopifyThumb']);
Route::get('initial_token_generate_request', [OrderController::class, 'initialTokenGenerateRequest']);
Route::get('generate_shopify_token', [OrderController::class, 'generateShopifyToken']);
Route::get('getShopifyorderbyordernumber', [OrderController::class, 'getShopifyOrderByOrderNumber']);
Route::get('synorderbydate', [OrderController::class, 'synOrderByDate']);
Route::get('synOrderBetweenId', [OrderController::class, 'synOrderBetweenId']);
Route::get('getcouponproducts', [CouponController::class, 'getCouponProducts']);

// Route::get('deleteorderbydate', [OrderController::class, 'synOrderByDate']);

Route::get("import/ship-station", [CustomController::class, 'shipStation']);



Route::post('hook', [OrderController::class, 'hook']);



Route::get('test-api', [ItemController::class, 'test']);
