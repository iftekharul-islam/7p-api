<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryAdjustment;
use App\Models\Item;
use App\Models\Section;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        $total = Inventory::searchSection($request->get('section_ids'))
            ->searchVendor($request->get('vendor_id'))
            ->searchCriteria($request->get('search_for_first'), $request->get('search_in_first'), $request->get('operator_first'))
            ->searchCriteria($request->get('search_for_second'), $request->get('search_in_second'), $request->get('operator_second'))
            ->searchCriteria($request->get('search_for_third'), $request->get('search_in_third'), $request->get('operator_third'))
            ->searchCriteria($request->get('search_for_fourth'), $request->get('search_in_fourth'), $request->get('operator_fourth'))
            ->selectRaw('SUM(CASE WHEN qty_on_hand > 0 THEN qty_on_hand * last_cost ELSE 0 END ) as cost')
            ->first();

        $inventories = Inventory::with('qty_user', 'section', 'last_product.vendor', 'purchase_products', 'inventoryUnitRelation')
            ->searchSection($request->get('section_ids'))
            ->searchVendor($request->get('vendor_id'))
            ->searchCriteria($request->get('search_for_first'), $request->get('search_in_first'), $request->get('operator_first'))
            ->searchCriteria($request->get('search_for_second'), $request->get('search_in_second'), $request->get('operator_second'))
            ->searchCriteria($request->get('search_for_third'), $request->get('search_in_third'), $request->get('operator_third'))
            ->searchCriteria($request->get('search_for_fourth'), $request->get('search_in_fourth'), $request->get('operator_fourth'))
            ->orderBy($sort_by, $request->get('sorted') ?? 'ASC')
            ->groupBy('inventories.stock_no_unique');

        $inventories = $inventories->paginate($request->get('perPage', 10));

        return [
            'total_cost' => $total,
            'inventories' => $inventories
        ];
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
        $stocks = Section::where('is_deleted', '0')->get();
        $stocks->transform(function ($item) {
            return [
                'value' => $item['id'],
                'label' => $item['section_name'],
                'data' => $item,
            ];
        });
        return $stocks;
    }

    public function calculateOrdering(Request $request)
    {
        // return "A";
        $divisor = $request->get('divisor');
        $start = Carbon::parse($request->get('start_date'))->format('Y-m-d');
        $end = Carbon::parse($request->get('end_date'))->format('Y-m-d');

        $items = Item::leftJoin('inventory_unit', 'inventory_unit.child_sku', '=', 'items.child_sku')
            ->where('items.is_deleted', '=', '0')
            ->selectRaw(
                'inventory_unit.stock_no_unique,
                         SUM(CASE WHEN items.item_status  NOT IN (5,6) AND ' .
                    ' items.created_at > "' . $start . ' 00:00:00" AND items.created_at < "' . $end .
                    ' 23:59:59" THEN inventory_unit.unit_qty * items.item_quantity ELSE 0 END ) as total'
            )
            ->groupBy('inventory_unit.stock_no_unique')
            ->get();

        if (!$items) {
            Log::info('calculateOrdering:  Sales Query Failed.');
            return false;
        }

        $inventoryTbl = Inventory::where('is_deleted', '0')->get();

        if (!$inventoryTbl) {
            Log::info('calculateOrdering: Stock # Query Failed.');
            return false;
        }

        foreach ($inventoryTbl as $stock) {

            // switch ($request->get('interval')) {
            //     case 'sales_30':
            //        $total = $stock->sales_30;
            //        break;
            //     case 'sales_90':
            //        $total = $stock->sales_90;
            //        break;
            //     case 'sales_180':
            //        $total = $stock->sales_180;
            //        break;
            // }

            $sales = $items->where('stock_no_unique', $stock->stock_no_unique)->first();

            if ($sales) {
                $total = $sales->total / $divisor;

                if ($total < 5) {
                    $stock->min_reorder = 0;
                } else if ($total < 10) {
                    $stock->min_reorder = round((($total - 1) / 10) + .5) * 10;
                } else if ($total < 50) {
                    $stock->min_reorder = round($total / 10) * 10;
                } else if ($total >= 50) {
                    $stock->min_reorder = round($total / 50) * 50;
                }
            } else {
                $stock->min_reorder = 0;
            }

            $stock->save();
        }

        return response()->json([
            'message' => 'Inventory Ordering Quantities Updated!',
            'status' => 201
        ], 201);
    }

    public function stockImageOption()
    {
        $stocks = Inventory::select(
            DB::raw('CONCAT(stock_no_unique, " - ", stock_name_discription) AS description'),
            'stock_no_unique',
            'warehouse'
        )
            ->where('is_deleted', '0')
            ->orderBy('stock_no_unique')
            ->get();
        $stocks->transform(function ($item) {
            return [
                'value' => $item['stock_no_unique'],
                'label' => $item['description'],
                'image' => $item['warehouse'],
            ];
        });
        return $stocks;
    }
}
