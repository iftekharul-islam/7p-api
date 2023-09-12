<?php

use App\Http\Controllers\CouponController;
use App\Http\Controllers\CustomController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\OrderController;
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
