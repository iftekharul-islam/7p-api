<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductionCategory;
use App\Models\SpecificationSheet;
use Illuminate\Http\Request;

use function PHPSTORM_META\map;

class SpecificationSheetController extends Controller
{

    public function index(Request $request)
    {
        $specSheets = SpecificationSheet::with('production_category')
            ->searchCriteria($request->get('search_for_1'), $request->get('search_in_1'))
            ->searchCriteria($request->get('search_for_2'), $request->get('search_in_2'))
            ->searchStatus($request->get('status'))
            ->searchMakeSample($request->get('make_sample'))
            ->searchInProductionCategory($request->get('production_category'))
            ->searchInWebImageStatus($request->get('web_image_status'))
            ->where('is_deleted', '0')
            ->paginate($request->get('perPage', 10));
        $specSheets->getCollection()->transform(function ($item) {
            $data = [
                'id' => $item->id,
                'product_description' => $item->product_description,
                'production_category' => $item->production_category->production_category_description,
                'product_sku' => $item->product_sku,
                'web_image_status' => SpecificationSheet::$webImageStatus[$item->web_image_status]
            ];
            return $data;
        });
        return $specSheets;
    }

    //get store function
    public function store(Request $request)
    {
        $specSheet = false;
        $previous_sku = trim($request->get('previous_sku', ''));
        if (!empty($previous_sku)) {
            $specSheet = SpecificationSheet::where('sku', $previous_sku)
                ->first();
            if (!$specSheet) {
                return response()->json([
                    'message' => 'Not a valid product sku is chosen to copy!',
                    'status' => 203,
                    'data' => []
                ], 203);
            } else {
                $newSpecSheet = $specSheet->replicate();
                $newSpecSheet->product_sku = trim($request->get('sku'));
                $newSpecSheet->save();
            }
        } else {
            $specSheet = $this->insertOrUpdateSpec($request);
        }
        if ($specSheet == false) {
            return response()->json([
                'message' => 'Product name cannot be empty',
                'status' => 203,
                'data' => []
            ], 203);
        }
        //session()->flush('proposed_sku');

        return response()->json([
            'message' => 'Spec sheet is created successfully',
            'status' => 201,
            'data' => []
        ], 201);
    }

    public function show($id)
    {
        // redirect to edit page
        return $this->edit($id);
        $spec = SpecificationSheet::find($id);
        if (!$spec) {
            return app()->abort(404);
        }

        return $spec;
    }

    public function edit($id)
    {
        $spec = SpecificationSheet::find($id);
        if (!$spec) {
            return app()->abort(404);
        }

        $production_categories = ProductionCategory::where('is_deleted', 0)
            ->get()
            ->pluck('description_with_code', 'id')
            ->prepend('Select a production category', '0');

        $vendors = Vendor::where('is_deleted', 0)
            ->orderBy('vendor_name')
            ->get()
            ->pluck('vendor_name', 'vendor_name')
            ->prepend('Select a vendor', 0);

        return view('product_specifications.edit')
            ->with('vendors', $vendors)
            ->with('spec', $spec)
            ->with('production_categories', $production_categories);
    }

    public function update(Request $request, $id)
    {
        $spec = SpecificationSheet::find($id);
        if (!$spec) {
            return app()->abort(404);
        }

        if (trim($request->get('previous_sku'))) {

            $specSheet = SpecificationSheet::where('product_sku', $request->get('previous_sku'))
                ->orderBy('id', 'desc')->first();
            if (!$specSheet) {
                return redirect()
                    ->back()
                    ->withErrors([
                        'error' => 'Cannot find pull SKU ' . $request->get('previous_sku') . '<br>Please verify SKU.',
                    ]);
            } else {
                $specSheet = $specSheet->toArray();
            }
            unset($specSheet['id']);
            unset($specSheet['product_sku']);
            unset($specSheet['images']);
            unset($specSheet['product_details_file']);

            SpecificationSheet::where('id', $id)->update($specSheet);

            return redirect()
                ->back()
                ->with('success', $request->get('product_sku') . ' SKU contain update from SKU ' . $request->get('previous_sku'));
        } else {


            $updatedSpecSheet = $this->insertOrUpdateSpec($request, $spec);
            if ($updatedSpecSheet == false) {
                return redirect()
                    ->back()
                    ->withErrors([
                        'error' => 'Product name cannot be empty.',
                    ]);
            }
            //session()->flush('proposed_sku');

            return redirect("/products_specifications/$id")->with('success', 'Spec sheet is updated successfully.');
        }
    }

    public function copyProduct(Request $request, $categoty_id, $product_sku)
    {

        $production_category = ProductionCategory::find($categoty_id);
        $proposed_sku = $this->generateSKU($production_category->production_category_code, null);


        $specSheet = SpecificationSheet::where('product_sku', $product_sku)
            ->first();
        $newSpecSheet = $specSheet->replicate();
        $newSpecSheet->product_sku = trim($proposed_sku); // New product
        $newSpecSheet->save();

        $this->addProduct($newSpecSheet);

        //session()->flush('proposed_sku');
        return redirect('/products_specifications')->with('success', 'New SKU ' . $proposed_sku . ' Created.');
    }

    public function getSteps(Request $request, $id = 1)
    {
        /*
		 * Specification has two steps.
		 * if none of them
		 * exit
		 */
        switch ($id) {
            case 1:
                $production_categories = ProductionCategory::where('is_deleted', 0)
                    ->get()
                    ->pluck('description_with_code', 'id')
                    ->prepend('Select a production category', '0');

                return view('product_specifications.spec_sheet_step_1')->with('production_categories', $production_categories);
            case 2:
                /*
				 * If the proposed sku is not found in the url
				 * and, proposed sku doesn't match the session stored sku
				 * redirect to home
				 */
                if (!$request->has('sku')) {
                    return redirect()
                        ->to('/products_specifications/step')
                        ->withErrors([
                            'error' => 'SKU cannot be generated or modified by user',
                        ]);
                }
                $production_categories = ProductionCategory::where('is_deleted', 0)
                    ->get()
                    ->pluck('description_with_code', 'id')
                    ->prepend('Select a production category', '0');

                $vendors = Vendor::where('is_deleted', 0)
                    ->get()
                    ->pluck('vendor_name', 'vendor_name')
                    ->prepend('Select a vendor', 0);

                return view('product_specifications.spec_sheet_step_2')
                    ->with('vendors', $vendors)
                    ->with('sku', $request->get('sku'))
                    ->with('production_categories', $production_categories)
                    ->with('selected_production_category', $request->get('production_category'));
                break;
            default:
                break;
        }
    }

    public function postSteps(Request $request, $id = 1)
    {
        switch ($id) {
            case 1:
                $production_category = ProductionCategory::find($request->get('production_category'));
                if (!$production_category) {
                    return redirect()
                        ->to('/products_specifications/step')
                        ->withErrors([
                            'error' => 'Not a valid production category',
                        ]);
                }
                $proposed_sku = $this->generateSKU($production_category->production_category_code, $request->get('gift-wrap'));
                //session()->put('proposed_sku', $proposed_sku);

                return redirect()->to(sprintf("products_specifications/step/2?sku=%s&production_category=%d", $proposed_sku, $request->get('production_category')));
            case 2:
                $specSheet = false;
                $previous_sku = trim($request->get('previous_sku', ''));
                if (!empty($previous_sku)) {
                    $specSheet = SpecificationSheet::where('product_sku', $previous_sku)
                        ->first();
                    if (!$specSheet) {
                        return redirect()
                            ->back() #->withInput()
                            ->withErrors([
                                'error' => 'Not a valid product sku is chosen to copy',
                            ]);
                    } else {
                        $newSpecSheet = $specSheet->replicate();
                        $newSpecSheet->product_sku = trim($request->get('product_sku'));
                        $newSpecSheet->save();
                    }
                } else {
                    $specSheet = $this->insertOrUpdateSpec($request);
                }
                if ($specSheet == false) {
                    return redirect()
                        ->back()
                        ->withInput()
                        ->withErrors([
                            'error' => 'Product name cannot be empty.',
                        ]);
                }
                //session()->flush('proposed_sku');

                return redirect('/products_specifications')->with('success', 'Spec sheet is created successfully.');
            default:
                return redirect()
                    ->to('/products_specifications/step')
                    ->withErrors([
                        'error' => 'Invalid request',
                    ]);
        }
    }

    private function insertOrUpdateSpec(Request $request, $specSheet = null)
    {
        $product_name = trim($request->get('product_name'));

        if (empty($product_name)) {
            return false;
        }

        if (is_null($specSheet)) {
            $specSheet = new SpecificationSheet();
            // product sku is one time insert able. only on insertion time.
            $specSheet->product_sku = trim($request->get('sku'));
            $specSheet->status = 0;
        } else {
            $specSheet->status = intval($request->get('status'));
        }
        $specSheet->product_name = $product_name;
        $specSheet->web_image_status = intval(trim($request->get('web_image_status')));
        $specSheet->product_description = trim($request->get('product_description'));
        $specSheet->product_weight = floatval($request->get('product_weight'));
        $specSheet->product_length = floatval($request->get('product_length'));
        $specSheet->product_width = floatval($request->get('product_width'));
        $specSheet->product_height = floatval($request->get('product_height'));
        $specSheet->packaging_type_name = trim($request->get('packaging_type_name'));
        $specSheet->packaging_size = trim($request->get('packaging_size'));
        $specSheet->packaging_weight = floatval($request->get('packaging_weight'));
        $specSheet->total_weight = floatval($request->get('total_weight'));
        $specSheet->production_category_id = intval($request->get('production_category'));
        $specSheet->art_work_location = trim($request->get('art_work_location'));
        $specSheet->production_image_location = trim($request->get('production_image_location'));
        $specSheet->temperature = trim($request->get('temperature'));
        $specSheet->dwell_time = trim($request->get('dwell_time'));
        $specSheet->pressure = trim($request->get('pressure'));
        $specSheet->run_time = trim($request->get('run_time'));
        $specSheet->type = trim($request->get('type'));
        $specSheet->font = trim($request->get('font'));
        $specSheet->variation_name = trim($request->get('variation_name'));
        $specSheet->make_sample = trim($request->get('make_sample'));
        $specSheet->product_general_note = trim($request->get('product_general_note'));

        /* spec table data */
        // $table_data = [];
        // foreach (array_chunk($request->get('spec_table_data'), 10) as $row) {
        //     if (empty($row[0])) {
        //         continue;
        //     }
        //     $table_data[] = $row;


        $specSheet->spec_table_data = json_encode($request->get('spec_table_data'));


        /* Special note segment */
        // $i = 0;
        // $arr = [];
        // foreach ($request->get('special_note') as $note) {
        //     // if special note col is left empty, don't insert
        //     if (empty(trim($note))) {
        //         continue;
        //     }
        //     $arr[] = [
        //         trim($note),
        //         trim($request->get('option_name')[$i]),
        //         trim($request->get('details')[$i]),
        //     ];
        //     ++$i;
        // }

        $specSheet->special_note = json_encode($request->get('special_note'));
        /* special note segment ends */

        $specSheet->product_note = trim($request->get('product_note'));

        $specSheet->cost_of_1 = floatval($request->get('cost_of_1'));
        $specSheet->cost_of_10 = floatval($request->get('cost_of_10'));
        $specSheet->cost_of_100 = floatval($request->get('cost_of_100'));
        $specSheet->cost_of_1000 = floatval($request->get('cost_of_1000'));
        $specSheet->cost_of_10000 = floatval($request->get('cost_of_10000'));

        // $i = 0;
        // $arr = [];
        // $j = 0;

        // foreach ($request->get('parts_name') as $part) {
        //     if (empty(trim($part))) {
        //         continue;
        //     }
        //     $arr[] = [
        //         trim($part),
        //         $request->get('cost_variation')[$j++],
        //         $request->get('cost_variation')[$j++],
        //         $request->get('cost_variation')[$j++],
        //         $request->get('cost_variation')[$j++],
        //         trim($request->get('supplier_name')[$i]),
        //     ];
        //     ++$i;
        // }

        $specSheet->content_cost_info = json_encode($request->get('cost_variation'));

        $specSheet->delivery_cost_variation = json_encode($request->get('delivery_cost_variation'));

        $specSheet->labor_expense_cost_variation = json_encode($request->get('labor_expense_cost_variation'));

        if ($request->hasFile('product_images')) {
            $paths = $this->image_manipulator($request->file('product_images'), $request->get('sku'));
            $specSheet->images = json_encode($paths);
        }

        if ($request->has('delete_product_details_image') && strtolower($request->get('delete_product_details_image', 'no')) == 'yes') {
            $specSheet->product_details_file = json_encode([]); // delete the image already in
        } elseif ($request->hasFile('product_details_file')) {
            $paths = $this->image_manipulator($request->file('product_details_file'), $request->get('sku'));
            $specSheet->product_details_file = json_encode($paths);
        }
        $specSheet->save();

        $this->addProduct($specSheet);

        return $specSheet;
    }

    private function image_manipulator($images, $sku)
    {
        $paths = [];
        foreach ($images as $image) {
            if ($image->isValid()) {
                $destinationPath = 'assets/images/spec_sheet';
                $extension = $image->getClientOriginalExtension();
                $fileName = sprintf("%s-%s.%s", $sku, rand(11111, 99999), $extension);
                $image->move($destinationPath, $fileName);
                $paths[] = sprintf("%s/%s", url($destinationPath), $fileName);
            }
        }
        return $paths;
    }

    private function generateSKU($production_category_code, $is_gift_wrapped = false)
    {
        $sku = sprintf("%s", $production_category_code);

        /*$products_stored = Product::where('product_model', 'LIKE', sprintf("%s%%", $production_category_code))
								  ->count();
		$total = $products_stored ? ++$products_stored : 1;*/
        $products_stored = SpecificationSheet::count();
        $total = 4140 + $products_stored;
        $sku = sprintf("%s%04d", $sku, $total);

        if ($is_gift_wrapped) {
            $sku = sprintf("%s-GIFT", $sku);
        }

        return $sku;
    }

    public function destroy(Request $request, $id)
    {
        $spec = SpecificationSheet::find($id);
        if ($spec) {
            $spec->is_deleted = 1;
            $spec->save();
        }

        return redirect()
            ->to('/products_specifications')
            ->with('success', 'Spec is deleted successfully');
    }

    private function addProduct($specSheet)
    {

        $product = Product::where('product_model', $specSheet->product_sku)->first();

        if (!$product) {
            $product = new Product;
            $product->product_model = $specSheet->product_sku;
        }

        if (strlen($specSheet->product_name) > 0) {
            $product->product_name = $specSheet->product_name;
        }

        if ($specSheet->product_weight > 0 && $product->ship_weight == null) {
            $product->ship_weight = $specSheet->product_weight;
        }

        if ($specSheet->product_width > 0 && $product->width == 0) {
            $product->width = $specSheet->product_width;
        }

        if ($specSheet->product_height > 0 && $product->height == 0) {
            $product->height = $specSheet->product_height;
        }

        if ($product->production_category ==  null) {
            $product->product_production_category = $specSheet->production_category_id;
        }

        $product->save();
    }


    public function searchableFieldsOption()
    {
        $searchable_fields = [];
        foreach (SpecificationSheet::$searchable_fields ?? [] as $key => $value) {
            $searchable_fields[] = [
                'label' => $value,
                'value' => $key,
            ];
        };
        return $searchable_fields;
    }

    public function productionCategoriesOption()
    {
        $production_categories = ProductionCategory::where('is_deleted', '0')
            ->get()->map(function ($item) {
                return [
                    'label' => $item->description_with_code,
                    'value' => $item->id,
                ];
            });
        return $production_categories;
    }

    public function webImageStatusOption()
    {
        $webImageStatus = [];

        foreach (SpecificationSheet::$webImageStatus ?? [] as $key => $value) {
            $webImageStatus[] = [
                'label' => $value,
                'value' => $key,
            ];
        };
        return $webImageStatus;
    }

    public function makeSampleDataOption()
    {
        $make_sample_data = [];
        foreach (array_merge(['all' => 'Select a make sample'], SpecificationSheet::$specSheetSampleDataArray) ?? [] as $key => $value) {
            $make_sample_data[] = [
                'label' => $value,
                'value' => $key,
            ];
        };
        return $make_sample_data;
    }

    public function statusesOption()
    {
        $statuses = [];
        foreach (array_merge(['all' => "Select a status"], SpecificationSheet::$statuses) ?? [] as $key => $value) {
            $statuses[] = [
                'label' => $value,
                'value' => $key,
            ];
        };
        return $statuses;
    }

    public function skus(Request $request)
    {
        $production_category = ProductionCategory::find($request->get('production_category'));
        if (!$production_category) {
            return redirect()
                ->to('/products_specifications/step')
                ->withErrors([
                    'error' => 'Not a valid production category',
                ]);
        }
        $proposed_sku = $this->generateSKU($production_category->production_category_code, $request->get('gift-wrap'));
        return ['sku' => $proposed_sku];
    }
}
