<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\PurchasedProduct;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $open_purchase_ids = PurchasedProduct::where('balance_quantity', '>', 0)
            ->selectRaw('DISTINCT purchase_id')
            ->get()
            ->pluck('purchase_id');

        $purchases = Purchase::query();
        $purchases->with('vendor', 'products');
        $purchases->withCount(['products as total_balance' => function ($another_query) {
            $another_query->select(DB::raw('SUM(balance_quantity)'));
        }]);
        $purchases->withCount(['products as total_products' => function ($another_query) {
            $another_query->select(DB::raw('SUM(quantity)'));
        }]);

        if (isset($request->status)) {
            if ($request->status == 1) {
                $purchases->whereIn('po_number', $open_purchase_ids);
            } else {
                $purchases->whereNotIn('po_number', $open_purchase_ids);
            }
        }
        $purchases = $purchases->orderBy('id', 'DESC')->paginate($request->get('perPage', 10));
        return $purchases;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!$request['vendor_id']) {
            return response()->json([
                'message' => "Please Select Vendor First!",
                'status' => 203,
                'data' => []
            ], 203);
        }
        try {
            $data = $request->only([
                'po_number',
                'vendor_id',
                'po_date',
                'payment_method',
                'grand_total',
                'o_status',
                'tracking',
                'notes',
                'user_id'
            ]);

            $data['po_date'] = Carbon::parse($request->po_date)->format('y-m-d');
            $data['po_number'] = $this->generateStockNoUnique();
            $data['user_id'] = auth()->user()->id;

            $purchase = Purchase::create($data);

            $products = [];

            foreach ($request->skus as $key => $value) {
                $product['purchase_id'] = $data['po_number'];
                $product['product_id'] = $value['product_id'];
                $product['stock_no'] = $value['stock_no'];
                $product['vendor_sku'] = $value['vendor_sku'];
                $product['vendor_sku_name'] = $value['vendor_sku_name'];
                $product['quantity'] = $value['quantity'] ?? '';
                $product['price'] = $value['unit_price'];
                $product['sub_total'] = $value['sub_total'];
                $product['eta'] = Carbon::parse($value['eta'])->format('y-m-d');
                $product['receive_quantity'] = 0;
                $product['balance_quantity'] = $value['quantity'];
                $product['user_id'] = auth()->user()->id;
                $product['created_at'] = Carbon::now();
                $product['updated_at'] = Carbon::now();
                $products[] = $product;
            }
            PurchasedProduct::insert($products);

            return response()->json([
                'message' => 'Order created successfully!',
                'status' => 201,
                'data' => []
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 403,
                'data' => []
            ], 403);
        }
    }

    private function generateStockNoUnique()
    {
        $po_number = Purchase::orderBy('id', 'desc')->first();
        return sprintf("%06d", (($po_number->id ?? 0) + 1));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Purchase::with('products', 'vendor')->find($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $data = $request->only([
                'tracking',
                'notes'
            ]);

            $puchase = Purchase::find($id);
            $puchase->update($data);

            PurchasedProduct::where('purchase_id', $puchase->po_number)->delete();

            $products = [];
            foreach ($request->skus as $key => $value) {
                $product['purchase_id'] = $puchase['po_number'];
                $product['product_id'] = $value['product_id'];
                $product['stock_no'] = $value['stock_no'];
                $product['vendor_sku'] = $value['vendor_sku'];
                $product['vendor_sku_name'] = $value['vendor_sku_name'];
                $product['quantity'] = $value['new_quantity'] ?? $value['quantity'];
                $product['price'] = $value['unit_price'];
                $product['sub_total'] = $value['sub_total'];
                $product['eta'] = Carbon::parse($value['eta'])->format('y-m-d');
                $product['receive_quantity'] = $value['receive_quantity'];
                $product['balance_quantity'] = $value['new_balance_quantity'] ?? $value['balance_quantity'];
                $product['user_id'] = auth()->user()->id;
                $product['created_at'] = Carbon::now();
                $product['updated_at'] = Carbon::now();
                $products[] = $product;
            }
            PurchasedProduct::insert($products);

            return response()->json([
                'message' => 'Order Update successfully!',
                'status' => 201,
                'data' => []
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 403,
                'data' => []
            ], 403);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $puchase = Purchase::find($id);
        if ($puchase) {
            PurchasedProduct::where('purchase_id', $puchase->po_number)->delete();
            $puchase->delete();
            return response()->json([
                'message' => 'Purchase delete successfully!',
                'status' => 201,
                'data' => []
            ], 201);
        }
        return response()->json([
            'message' => "Purchase not found!",
            'status' => 203,
            'data' => []
        ], 203);
    }

    public function receiveOrders(Request $request)
    {
        foreach ($request->all() as $key => $value) {
            if ($value['balance_quantity'] < 0) {
                return response()->json([
                    'message' => "Received Quantity is grater Quantity for Stock# " . $value['stock_no'],
                    'status' => 203,
                    'data' => []
                ], 203);
            }
            $purchaseProduct = PurchasedProduct::find($value['id']);
            if ($purchaseProduct) {
                $purchaseProduct->update([
                    'receive_date' => Carbon::parse($value['receive_date']),
                    'receive_quantity' => $purchaseProduct->receive_quantity + $value['new_received'],
                    'balance_quantity' => $value['balance_quantity']
                ]);
            }
        }

        return response()->json([
            'message' => "Received Inventory Successfully!",
            'status' => 201,
            'data' => []
        ], 201);
    }
}
