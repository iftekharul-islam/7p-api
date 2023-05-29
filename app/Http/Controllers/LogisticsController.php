<?php

namespace App\Http\Controllers;

use App\Models\InventoryUnit;
use App\Models\Option;
use Illuminate\Http\Request;

class LogisticsController extends Controller
{
    public function index(Request $request)
    {
        if ($request->get('skus') == null) {
            $options = Option::with('product', 'route.template', 'inventoryunit_relation.inventory', 'design')
                ->leftjoin('inventory_unit', 'inventory_unit.child_sku', '=', 'parameter_options.child_sku')
                ->searchIn($request->get('search_for_first'), $request->get('contains_first'), $request->get('search_in_first'), $request->get('stockno'))
                ->searchIn($request->get('search_for_second'), $request->get('contains_second'), $request->get('search_in_second'), $request->get('stockno'))
                ->searchIn($request->get('search_for_third'), $request->get('contains_third'), $request->get('search_in_third'), $request->get('stockno'))
                ->searchIn($request->get('search_for_fourth'), $request->get('contains_fourth'), $request->get('search_in_fourth'), $request->get('stockno'))
                ->searchRoute($request->get('batch_route_id'))
                ->searchActive($request->get('active'))
                ->searchStatus($request->get('sku_status'))
                ->searchSure3d($request->get('sure3d'))
                ->selectRaw('parameter_options.*, inventory_unit.stock_no_unique');
        } else {
            $options = Option::with('product', 'route.template', 'inventoryunit_relation.inventory', 'design')
                ->leftjoin('inventory_unit', 'inventory_unit.child_sku', '=', 'parameter_options.child_sku')
                ->whereIn('parameter_options.child_sku', $request->get('skus'));
        }
        $options = $options->groupBy('parameter_options.child_sku')
            ->orderBy('parameter_options.parent_sku', 'ASC')
            ->paginate($request->get('perPage', 10));
        return $options;
    }

    public function updateSKUs(Request $request)
    {
        if ($request->has('child_skus')) {
            $skus = array_filter($request->get('child_skus'));

            $skus = array_map('htmlspecialchars_decode', $skus);

            if (count($skus) > 0) {

                $update = array();

                if ($request->has('allow_mixing_update') && $request->get('allow_mixing_update') != '') {
                    $update['allow_mixing'] = $request->get('allow_mixing_update');
                }

                if ($request->has('batch_route_id_update') && $request->get('batch_route_id_update') != 0) {
                    $update['batch_route_id'] = $request->get('batch_route_id_update');
                }

                if ($request->has('graphic_sku_update') && $request->get('graphic_sku_update') != '') {
                    $update['graphic_sku'] = $request->get('graphic_sku_update');
                }

                if ($request->has('sure3d_update') && $request->get('sure3d_update') != '') {
                    $update['sure3d'] = $request->get('sure3d_update');
                }

                if ($request->has('frame_size_update') && $request->get('frame_size_update') != '') {
                    $update['frame_size'] = $request->get('frame_size_update');
                }
                //dd($update, $request->all());
                if (count($update) > 0) {

                    if (auth()->user()) {
                        $update['user_id'] = auth()->user()->id;
                    } else {
                        $update['user_id'] = 87;
                    }

                    $records = Option::whereIn('child_sku', $skus)
                        ->update($update);
                }

                if ($request->has('stocknos')) {

                    foreach ($skus as $sku) {

                        InventoryUnit::where('child_sku', $sku)->delete();

                        foreach ($request->get('stocknos') as $stock_no) {
                            $unit = new InventoryUnit;
                            $unit->child_sku = $sku;
                            $unit->stock_no_unique = $stock_no;
                            $unit->unit_qty = $request->get('QTY_' . $stock_no);
                            if (auth()->user()) {
                                $unit->user_id = auth()->user()->id;
                            } else {
                                $unit->user_id = 87;
                            }
                            $unit->save();
                        }
                    }
                }
            }

            return redirect()->action(
                'LogisticsController@sku_list',
                [
                    'search_in_first' => $request->get('search_in_first'),
                    'contains_first' => $request->get('contains_first'),
                    'search_for_first' => $request->get('search_for_first'),
                    'search_in_second' => $request->get('search_in_second'),
                    'contains_second' => $request->get('contains_second'),
                    'search_for_second' => $request->get('search_for_second'),
                    'search_in_third' => $request->get('search_in_third'),
                    'contains_third' => $request->get('contains_third'),
                    'search_for_third' => $request->get('search_for_third'),
                    'search_in_fourth' => $request->get('search_in_fourth'),
                    'contains_fourth' => $request->get('contains_fourth'),
                    'search_for_fourth' => $request->get('search_for_fourth'),
                    'unassigned' => $request->get('unassigned'),
                    'stockno' => $request->get('stockno'),
                    'batch_route_id' => $request->get('batch_route_id'),
                    'sure3d' => $request->get('sure3d'),
                    'orientation' => $request->get('orientation'),
                ]
            );
        } else {
            return redirect()->action('LogisticsController@sku_list')->withErrors('No Child SKUs selected');
        }
    }
}
