<?php

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
// get('shopify-order/{id}', 'OrderController@shopifyOrderById');
// get('shopify-thumb/{order_id}/{item_id}', 'OrderController@shopifyThumb');
// get('update-shopify-thumb/{order_id}/{item_id}', 'OrderController@updateShopifyThumb');
Route::get('initial_token_generate_request', [OrderController::class, 'initialTokenGenerateRequest']);
Route::get('generate_shopify_token', [OrderController::class, 'generateShopifyToken']);
Route::get('getShopifyorderbyordernumber', [OrderController::class, 'getShopifyOrderByOrderNumber']);
Route::get('synorderbydate', [OrderController::class, 'synOrderByDate']);
// get('synOrderBetweenId', 'OrderController@synOrderBetweenId');
// get('getcouponproducts', 'CouponController@getCouponProducts');