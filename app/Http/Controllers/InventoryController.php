<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryAdjustment;
use App\Models\Section;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public static $search_in = [
        'stock_no_unique'        => 'Stock Number',
        'stock_name_discription' => 'Description',
        'child_sku'              => 'Child SKU',
        'wh_bin'                 => 'Bin',
        'qty_on_hand'            => 'Quantity on Hand',
        'last_cost'              => 'Last Cost',
        'value'                  => 'Total Value',
        'total_sale'             => 'Total Sales',
        'sales_30'               => '30 Days of Sales',
        'qty_av'                 => 'Quantity Available',
        // 'until_reorder'          => 'Need to Reorder'
    ];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!$request->has('sort_by') || $request->get('sort_by') == null) {
            $sort_by = 'inventories.stock_no_unique';
        } else {
            $sort_by = $request->get('sort_by');
        }

        $inventories = Inventory::with('qty_user', 'section', 'last_product.vendor', 'purchase_products')
            ->searchSection($request->get('section_ids'))
            ->searchVendor($request->get('vendor_id'))
            ->searchCriteria($request->get('search_for_first'), $request->get('search_in_first'), $request->get('operator_first'))
            ->searchCriteria($request->get('search_for_second'), $request->get('search_in_second'), $request->get('operator_second'))
            ->searchCriteria($request->get('search_for_third'), $request->get('search_in_third'), $request->get('operator_third'))
            ->searchCriteria($request->get('search_for_fourth'), $request->get('search_in_fourth'), $request->get('operator_fourth'))
            ->orderBy($sort_by, $request->get('sorted') ?? 'ASC')
            ->groupBy('inventories.stock_no_unique');

        return $inventories->paginate($request->get('perPage', 10));
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
        $data = $request->only([
            'stock_no_unique',
            'stock_name_discription',
            'section_id',
            'sku_weight',
            're_order_qty',
            'min_reorder',
            'last_cost',
            'upc',
            'wh_bin',
            'warehouse'
        ]);
        $data['stock_no_unique'] = $request->stock_no_unique ?? $this->generateStockNoUnique();
        $data['user_id'] = auth()->user()->id;

        Inventory::create($data);

        return response()->json([
            'message' => 'Stock created successfully!',
            'status' => 201,
            'data' => []
        ], 201);
    }

    private function generateStockNoUnique()
    {
        $stockNoUnique = Inventory::orderBy('id', 'desc')->first();

        return sprintf("1%05d", (($stockNoUnique->id ?? 0) + 1));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $inventory = Inventory::find($id);
        /*
       * Create a configuration file for them when they edit them
       */
        //TODO need to check
        // $file = "/var/www/order.monogramonline.com/Inventories.json";
        $file = "Inventories.json";
        $template = [
            "DROPSHIP" => false,
            "DROPSHIP_SKU" => "",
            "DROPSHIP_COST" => 0
        ];
        $finalData = [];

        if (!file_exists($file)) {
            file_put_contents($file, json_encode(
                [
                    $id => $template
                ],
                JSON_PRETTY_PRINT
            ));
            $finalData = $template;
        } else {
            $data = json_decode(file_get_contents($file), true);

            if (!isset($data[$id])) {
                $data[$id] = $template;

                file_put_contents($file, json_encode(
                    $data,
                    JSON_PRETTY_PRINT
                ));
                $finalData = $data[$id];
            } else {
                $finalData = $data[$id];
            }
        }

        $inventory['dropship'] = $finalData['DROPSHIP'];
        $inventory['dropship_sku'] = $finalData['DROPSHIP_SKU'];
        $inventory['dropship_cost'] = $finalData['DROPSHIP_COST'];

        return $inventory;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Inventory $inventory)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $inventory = Inventory::find($id);

        $data = $request->only([
            'stock_name_discription',
            'section_id',
            'sku_weight',
            're_order_qty',
            'min_reorder',
            'last_cost',
            'upc',
            'wh_bin',
            'warehouse'
        ]);
        $inventory->update($data);
        //TODO need to check
        // $file = "/var/www/order.monogramonline.com/Inventories.json";
        $file = "Inventories.json";
        $data = json_decode(file_get_contents($file), true);

        $data[$id]['DROPSHIP_COST'] = $request->dropship_cost;
        $data[$id]['DROPSHIP_SKU'] = $request->dropship_sku;
        $data[$id]['DROPSHIP'] = $request->dropship;

        file_put_contents($file, json_encode(
            $data,
            JSON_PRETTY_PRINT
        ));

        return response()->json([
            'message' => 'Stock created successfully!',
            'status' => 201,
            'data' => []
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $inventory = Inventory::find($id);
        if ($inventory) {
            $inventory->delete();
            return response()->json([
                'message' => 'Inventory delete successfully!',
                'status' => 201,
                'data' => []
            ], 201);
        }
        return response()->json([
            'message' => "Inventory not found!",
            'status' => 203,
            'data' => []
        ], 203);
    }

    public function updateBinQty(Request $request)
    {
        $inventory = Inventory::find($request->id);
        if (!$inventory) {
            return response()->json([
                'message' => 'Inventory Not Found!',
                'status' => 203
            ], 203);
        }
        if ($request->wh_bin && $inventory->wh_bin != $request->wh_bin) {
            $inventory->wh_bin = $request->wh_bin;
            $inventory->user_id = auth()->user()->id;
            $inventory->save();

            return response()->json([
                'message' => 'Bin Update Successfully!',
                'status' => 201
            ], 201);
        }

        if ($request->qty_on_hand && $inventory->qty_on_hand != $request->qty_on_hand) {
            $result = InventoryAdjustment::adjustInventory(2, $inventory->stock_no_unique, $request->qty_on_hand);
            if ($result) {
                return response()->json([
                    'message' => 'Qty On Hand Update Successfully!',
                    'status' => 201
                ], 201);
            }
        }
        return response()->json([
            'message' => 'Nothing to Update!',
            'status' => 203
        ], 203);
    }
    public function sectionOption()
    {
        $stocks = Section::get();
        $stocks->transform(function ($item) {
            return [
                'value' => $item['id'],
                'label' => $item['section_name'],
                'data' => $item,
            ];
        });
        return $stocks;
    }
}
