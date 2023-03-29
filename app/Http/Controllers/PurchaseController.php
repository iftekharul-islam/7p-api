<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\PurchaseProduct;
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

        $open_purchase_ids = PurchaseProduct::where('balance_quantity', '>', 0)
            ->selectRaw('DISTINCT purchase_id')
            ->get()
            ->pluck('purchase_id');

        $purchases = Purchase::query();
        $purchases->with('vendor', 'products');
        $purchases->withCount(['products as total_balance' => function($another_query) {
            $another_query->select(DB::raw('SUM(receive_quantity)'));
        } ]);
        $purchases->withCount(['products as total_products' => function($another_query) {
            $another_query->select(DB::raw('SUM(balance_quantity)'));
        } ]);

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
                $product['receive_quantity'] = $value['quantity'];
                $product['balance_quantity'] = $value['quantity'];
                $product['user_id'] = auth()->user()->id;
                $product['created_at'] = Carbon::now();
                $product['updated_at'] = Carbon::now();
                $products[] = $product;
            }
            PurchaseProduct::insert($products);

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
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // $data = Purchase::find($id);
        // if ($data) {
        //     $data->delete();
        //     return response()->json([
        //         'message' => 'Vendor delete successfully!',
        //         'status' => 201,
        //         'data' => []
        //     ], 201);
        // }
        return response()->json([
            'message' => "Under Maintainence!",
            'status' => 203,
            'data' => []
        ], 203);
    }
}
