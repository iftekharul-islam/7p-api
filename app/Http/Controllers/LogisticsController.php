<?php

namespace App\Http\Controllers;

use App\Models\BatchRoute;
use App\Models\Design;
use App\Models\InventoryUnit;
use App\Models\Option;
use App\Models\Parameter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use library\Helper;

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

        return response()->json($options, 200);
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
                //TODO - need to check error message for body
                if (count($update) > 0) {

                    if (auth()->user()) {
                        $update['user_id'] = auth()->user()->id;
                    } else {
                        $update['user_id'] = 87;
                    }

                    $records = Option::whereIn('child_sku', $skus)
                        ->update($update);
                }

                //TODO - need to check error message for body
                if ($request->has('stocklist')) {

                    foreach ($skus as $sku) {

                        InventoryUnit::where('child_sku', $sku)->delete();

                        foreach ($request->get('stocklist') as $stock_no) {
                            $unit = new InventoryUnit;
                            $unit->child_sku = $sku;
                            $unit->stock_no_unique = $stock_no['value'];
                            $unit->unit_qty = $stock_no['qty'] ?? 0;
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

            return response()->json([
                'message' => 'Child SKUs updated.',
                'status' => 201
            ], 201);

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
            return response()->json([
                'message' => 'No Child SKUs selected',
                'status' => 203
            ], 203);
        }
    }

    public function childSKUOption()
    {
        $parameters = Parameter::where('is_deleted', '0')
            ->get();

        $batch_routes = BatchRoute::where('is_deleted', '0')
            ->orderBy('batch_route_name')
            ->get()
            ->pluck('batch_route_name', 'id');
        return response()->json([
            'parameters' => $parameters,
            'batch_routes' => $batch_routes
        ]);
    }

    public function addChildSKU(Request $request)
    {
        if (!isset($request->child_sku)) {
            return response()->json([
                'message' => 'Child SKU is required',
                'status' => 203
            ], 203);
        }

        $child_sku = Option::where('child_sku', $request->get('child_sku'))->first();

        if ($child_sku) {
            return response()->json([
                'message' => 'Child SKU ' . $request->get('child_sku') . ' already exists',
                'status' => 203
            ], 203);
            return redirect()->back()->withInput()->withErrors('Child SKU ' . $request->get('child_sku') . ' already exists');
        }

        $unique_row_value = Helper::generateUniqueRowId();

        $parameters = Parameter::where('is_deleted', '0')
            ->get();

        if ($parameters->count() == 0) {
            return response()->json([
                'message' => 'No Parameters available.',
                'status' => 203
            ], 203);
        }

        $is_code_field_found = false;
        $code = '';
        $dataToStore = [];
        foreach ($parameters as $parameter) {
            $parameter_value = $parameter->parameter_value;
            $form_field = Helper::textToHTMLFormName($parameter_value);
            if ($form_field == 'code') {
                $is_code_field_found = true;
                $code = $request->get($form_field, '');
            }
            $dataToStore[$parameter_value] = $request->get($form_field, '');
        }
        // check if the code is already existing on database or not
        $option = null;

        $parent_sku = trim($request->get('parent_sku'), '');
        $graphic_sku = trim($request->get('graphic_sku'), '');
        $child_sku = trim($request->get('child_sku'), '');
        $id_catalog = trim($request->get('id_catalog'), '');

        if ($is_code_field_found) {
            $option = Option::where('child_sku', $child_sku)
                ->first();
        }

        if (!$option) {
            $option = new Option();
            $option->unique_row_value = $unique_row_value;
            $option->child_sku = $child_sku;
        }

        $option->parent_sku = $parent_sku;
        $option->graphic_sku = $graphic_sku;
        $option->id_catalog = $id_catalog;
        $option->allow_mixing = intval($request->get('allow_mixing', 1));
        $option->batch_route_id = intval($request->get('batch_route_id', Helper::getDefaultRouteId()));
        $option->parameter_option = json_encode($dataToStore);
        $option->sure3d = intval($request->get('sure3d', 0));
        $option->orientation = 0;

        $option->save();

        return response()->json([
            'message' => 'Child SKU inserted.',
            'status' => 201
        ], 201);

        return redirect()
            ->action('LogisticsController@sku_list', ['search_for_first' => $child_sku, 'search_in_first' => 'child_sku'])
            ->with('success', "Child sku inserted.");
    }

    public function getSKUs(string $id)
    {
        if (!isset($id)) {
            return response()->json([
                'message' => 'Unique Row Value is required',
                'status' => 203
            ], 203);
        }

        $options = Option::where('unique_row_value', $id)
            ->first();

        if (!$options) {
            return response()->json([
                'message' => 'Your input is wrong.',
                'status' => 203
            ], 203);
        }
        $data = [];
        $file = "/var/www/order.monogramonline.com/BypassOption.json";
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
        }
        $options['bypass_option'] = $data[$options['child_sku']] ?? false;

        return response()->json($options, 200);
    }

    public function updateSku(Request $request)
    {
        if (!isset($request->unique_row_value)) {
            return response()->json([
                'message' => 'Unique Row Value is required',
                'status' => 203
            ], 203);
        }

        $unique_row_value = $request->get('unique_row_value');

        $parent_sku = trim($request->get('parent_sku'), '');
        $graphic_sku = trim($request->get('graphic_sku'), '');
        $child_sku = trim($request->get('child_sku'), '');
        $id_catalog = trim($request->get('id_catalog'), '');
        $sure3d = trim($request->get('sure3d'), '');


        $bypassOption = (bool)trim($request->get('bypass_option'), '');

        // TODO - No such file or directory
        // $file = "/var/www/order.monogramonline.com/BypassOption.json";
        // $data = [];
        // if (file_exists($file)) {
        //     $data = json_decode(file_get_contents($file), true);
        // }
        // $data[$child_sku] = $bypassOption;
        // file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));


        if (empty($child_sku)) {
            return response()->json([
                'message' => 'Child SKU or Stock Number is required',
                'status' => 203
            ], 203);
        }

        // todo: if child sku is changed, change on items.child_sku too

        Option::where('unique_row_value', $unique_row_value)
            ->update([
                'id_catalog' => $id_catalog,
                'parent_sku' => $parent_sku,
                'child_sku' => $child_sku,
                'graphic_sku' => $graphic_sku,
                'allow_mixing' => intval($request->get('allow_mixing', 1)),
                'batch_route_id' => intval($request->get('batch_route_id', Helper::getDefaultRouteId())),
                'sure3d' => intval($request->get('sure3d', 0)),
            ]);

        return response()->json([
            'message' => 'Child SKU updated.',
            'status' => 201
        ], 201);
    }

    public function updateSingleSku(Request $request)
    {
        $parameter_option = Option::with('design')
            ->where('unique_row_value', $request->get('unique_row_value'))
            ->first();

        if (!$parameter_option) {
            return response()->json([
                'message' => 'Child SKU not found',
                'status' => 203
            ], 203);
        }

        if (!$parameter_option->design) {
            Design::check($parameter_option->graphic_sku);
        }

        $update_flag = FALSE;

        if ($parameter_option->batch_route_id != trim($request->get('route'))) {

            $route_exists = BatchRoute::find(trim($request->get('route')));

            if ($route_exists) {
                $parameter_option->batch_route_id = trim($request->get('route'));
                $update_flag = TRUE;
            } else {
                return response()->json([
                    'message' => 'Route does not Exist',
                    'status' => 203
                ], 203);
                return 'Route does not Exist';
            }
        }

        if ($parameter_option->allow_mixing != trim($request->get('mix'))) {

            $parameter_option->allow_mixing = trim($request->get('mix'));
            $update_flag = TRUE;
        }

        if ($parameter_option->graphic_sku != trim($request->get('graphic_sku'))) {
            $parameter_option->graphic_sku = trim($request->get('graphic_sku'));
            $parameter_option->save();

            Design::updateGraphicInfo(1, $parameter_option->id);

            $parameter_option = Option::with('design')->where('unique_row_value', $request->get('unique_row_value'))->first();
            $msg = "Updated ";
            $msg .= $parameter_option->design->template ? '' : ' - NoTemplate ';
            $msg .= $parameter_option->design->xml ? '' : ' - NoXML ';
            return response()->json([
                'message' => $msg,
                'status' => 201
            ], 201);

            // return 'Updated - ' . $parameter_option->design->template ? '' : ' - NoTemplate ' .
            //     $parameter_option->design->xml ? '' : ' - NoXML ';
        }

        if ($parameter_option->sure3d != trim($request->get('sure3d'))) {
            $parameter_option->sure3d = trim($request->get('sure3d'));
            $update_flag = TRUE;
        }


        if ($parameter_option->orientation != trim($request->get('orientation'))) {
            $parameter_option->orientation = trim($request->get('orientation'));
            $update_flag = TRUE;
        }

        if ($parameter_option->frame_size != trim($request->get('frame_size'))) {
            $parameter_option->frame_size = trim($request->get('frame_size'));
            $update_flag = TRUE;
        }

        if ($parameter_option->mirror != trim($request->get('mirror'))) {
            $parameter_option->mirror = trim($request->get('mirror'));
            $update_flag = TRUE;
        }

        if ($update_flag) {
            $parameter_option->save();
            return response()->json([
                'message' => 'Updated',
                'status' => 201
            ], 201);
            return 'Updated';
        } else {
            return response()->json([
                'message' => 'No Update',
                'status' => 203
            ], 203);
            return 'No Update';
        }
    }
}
