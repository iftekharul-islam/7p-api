<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use library\Helper;

class CouponController extends Controller
{
    public function getCouponProducts(Request $request)
    {
        $discountCode = $request->get('term');
        $discountCode = str_replace("coupon_code=", "", $discountCode);
        $helper = new Helper();
        $array = ['code' => $discountCode];
        //dd($discountCode);
        //        $helper->jdbg("discountCode = ",$discountCode);

        // http://dev.monogramonline.com/getcouponproducts?code=g2pkc22xfj2m2

        $discountCodeDetails = $helper->shopify_call("/admin/api/2023-01/discount_codes/lookup.json", $array, 'GET');

        //$helper->jdbg("getCouponProducts = ", $discountCodeDetails);

        if (!empty($discountCodeDetails["response"])) {

            if (isset(json_decode($discountCodeDetails['response'])->errors)) {
                $pHtml = '<ul class="app-discount">';
                $pHtml = $pHtml . '<li>';
                $pHtml = $pHtml . 'Coupon/Term ';
                $pHtml = $pHtml . json_decode($discountCodeDetails['response'])->errors;
                $pHtml = $pHtml . '</li>';
                $pHtml = $pHtml . '</ul>';
                return $pHtml;
            }
            // https://monogramonline.myshopify.com/admin/api/2020-01/price_rules/660298301573.json
            $priceRuleDetails = $helper->shopify_call("/admin/api/2023-01/price_rules/" .
                $discountCodeDetails['response']['discount_code']['price_rule_id'] . ".json", [], 'GET');
            //            dd($discountCodeDetails);
            $entitledCollectionIds = json_decode($priceRuleDetails['response'], true);
            //$helper->jdbg("entitledCollectionIds = ", $entitledCollectionIds);
            #############################
            if (!isset($entitledCollectionIds["price_rule"]["entitled_collection_ids"][0])) {
                $pHtml = '<ul class="app-discount">';
                $pHtml = $pHtml . '</ul>';
                return json_encode($pHtml);
            }
            #############################
            // https://monogramonline.myshopify.com/admin/api/2020-01/collections/184960843909/products.json
            $productsDetails = $helper->shopify_call("/admin/api/2023-01/collections/" . $entitledCollectionIds["price_rule"]["entitled_collection_ids"][0] . "/products.json", [], 'GET');
            $productsCollectionIds = json_decode($productsDetails['response'], true);

            //            dd($productsCollectionIds['products']);

            // "https://monogramonline.myshopify.com/products/".preg_replace('/\W+/', '-', strtolower($item['name']));
            $products = [];
            $pHtml = '<ul class="app-discount">';
            foreach ($productsCollectionIds['products'] as $product) {
                $product_url = "https://monogramonline.com/products/" . $product['handle'];
                $products[$product['id']]["title"] = $product['title'];
                $products[$product['id']]["image_url"] = $product['image']['src'];
                $products[$product['id']]["product_url"] = "https://monogramonline.com/products/" . $product['handle'];

                $pHtml = $pHtml . '<li>';
                $pHtml = $pHtml . '<figure><img src="' . $product['image']['src'] . '" class="product-image"></figure>';
                $pHtml = $pHtml . '<h2 class="product-title">' . $product['title'] . '</h2>';
                $pHtml = $pHtml . '<a href="' . $product_url . '" class="add-to-cart">Select Product</a>';
                $pHtml = $pHtml . '</li>';
            }
            $pHtml = $pHtml . '</ul>';

            return $pHtml;
            //            dd($pHtml);
            //            dd($products, $productsCollectionIds['products']);
        } else {
            $pHtml = '<ul class="app-discount">';
            $pHtml = 'Response Not Found';
            $pHtml = $pHtml . '</ul>';
            return $pHtml;
        }




        //        dd("ssssss",$discountCodeDetails, $priceRuleDetails['response']);
        //        dd($entitledCollectionIds, $entitledCollectionIds["price_rule"]["entitled_collection_ids"][0]);
    }
}
