<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Models\TemplateOption;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    private $repeated_fields = [
        1 => 'Line item fields are on new lines',
        2 => 'Header - Line item fields - Footer',
        3 => 'M.O.M. - leave all fields empty',
        4 => 'BETA - VendorNet - leave all fields empty',
        5 => 'Line item fields are on the same line',
    ];

    private $delimited_chars = [
        1 => 'TAB',
        2 => 'Comma',
        3 => 'Pipe',
        4 => 'Comma/Unquoted',
        5 => 'Space',
    ];

    private $formats = [
        1  => 'Integer',
        2  => 'Float',
        3  => 'Date, MM/DD/YYYY',
        4  => 'Date, DD/MM/YYYY',
        5  => 'Date, YYYY-MM-DD',
        6  => 'Date, yyyyMMdd',
        7  => 'Date, MM/DD/YYYY + 5 days',
        8  => 'Strip phone #',
        9  => 'Strip prefix',
        10 => 'Text/Excel',
        11 => 'Integer, add 1',
        12 => 'Extract option value',
        13 => 'Extract custom value',
        14 => 'Date, MM/DD/YYYY + X days',
        15 => 'Current Date, MM/DD/YYYY',
        16 => 'To UpperCase',
        17 => 'Date, MM-DD-YY HH:MM',
        18 => 'Date, YYYY-MM-DD HH:MM:SS',
        19 => 'Text',
    ];

    private $option_categories = [
        'FIX'                                       => 'Fixed value',
        'header.currentdate'                        => 'Current date',
        'header.billing.address.firstname'          => 'bill First name',
        'header.billing.address.lastname'           => 'bill Last name',
        'header.ordernumber'                        => 'Order number',
        'header.4plordernumber'                     => '4PL Order number',
        'header.longordernumber'                    => 'Full Order number',
        'header.prefixordernumber'                  => 'D/S prefix & Order number',
        'header.po_num'                             => 'PO number',
        'header.store'                              => 'Store',
        'header.storeType'                          => 'store Type',
        'header.orderdate'                          => 'order Date',
        'header.saletax.amount'                     => 'order Tax',
        'header.saletax.rate'                       => 'Sales Tax Percent',
        'header.shippingamount.amount'              => 'shipping Cost',
        'header.subtotal'                           => 'order Total',
        'header.giftwrap'                           => 'Gift wrap charge',
        'header.custNum'                            => 'Customer Number',
        'header.email'                              => 'email',
        'header.comments'                           => 'Order comments',
        'header.orderCustom'                        => 'Gift Message',
        'header.customData'                         => 'Custom fields',
        'header.orderStatus'                        => 'Order status code',
        'header.billing.email'                      => 'email',
        'header.billing.address.company'            => 'Bill Company name',
        'header.billing.address.Bill_Name'          => 'Bill Name',
        'header.billing.address.line1'              => 'bill Address1',
        'header.billing.address.line2'              => 'bill Address2',
        'header.billing.address.city'               => 'bill City',
        'header.billing.address.state'              => 'bill State',
        'header.billing.address.postalcode'         => 'bill Zip',
        'header.billing.address.country'            => 'bill Country',
        'header.billing.address.scountry'           => 'bill Country (short)',
        'header.billing.address.fcountry'           => 'bill Country (long)',
        'header.billing.address.phonenumber'        => 'bill Phone',
        'header.billing.payment.method'             => 'creditCard',
        'header.billing.payment.amount'             => 'orderTotal',
        'header.shipping.address.company'           => 'Ship Company name',
        'header.shipping.shippingmethod.carrier'    => 'ship - carrier',
        'header.shipping.shippingmethod.methodname' => 'shipMethod name',
        'header.shipping.shippingmethod.code'       => 'shipMethod code',
        'header.shipping.address.firstname'         => 'ship First name',
        'header.shipping.address.lastname'          => 'ship Last name',
        'header.shipping.address.Ship_Name'         => 'Ship Name',
        'header.shipping.address.line1'             => 'ship Address1',
        'header.shipping.address.line2'             => 'ship Address2',
        'header.shipping.address.city'              => 'ship City',
        'header.shipping.address.state'             => 'ship State',
        'header.shipping.address.postalcode'        => 'ship Zip',
        'header.shipping.address.country'           => 'ship Country',
        'header.shipping.address.scountry'          => 'ship Country (short)',
        'header.shipping.address.fcountry'          => 'ship Country (long)',
        'header.shipping.address.phonenumber'       => 'ship Phone',
        'header.numlines'                           => '# of lines',
        'items.itemID'                              => 'Item ID',
        'items.sku'                                 => 'Item SKU',
        'items.upc'                                 => 'Item UPC',
        'items.vendorSku'                           => 'Supplier SKU',
        'items.description'                         => 'Item name',
        'items.unitprice'                           => 'Item unit price',
        'items.itemCost'                            => 'Item unit cost',
        'items.quantity'                            => 'Items Qty',
        'items.itemOptions'                         => 'Item Options',
        'items.shipperOptions'                      => 'Item Shipper Notes',
        'items.lineItem'                            => 'Item Line #',
        'items.lineseq'                             => 'SEQ #, starts at 0',
        'items.nameOptions'                         => 'Item name & options',
        'items.subtotal'                            => 'Line item sub-total',
    ];
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $templates = Template::where('is_deleted', '0')
            ->latest()
            ->get();
        return $templates;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'template_name' => 'required',
        ]);

        $data = $request->only([
            'template_name',
        ]);
        try {
            Template::create($data);
            return response()->json([
                'message' => 'Template created successfully!',
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
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $template = Template::with('options')
            ->find($id);

        if (!$template) {
            return response()->json([
                'message' => "Template didn't found!",
                'status' => 203,
                'data' => []
            ], 203);
        }

        $repeated_fields = [];
        foreach ($this->repeated_fields as $key => $value) {
            $repeated_fields[] = [
                'label' => $value,
                'value' => (string)$key,
            ];
        }

        $delimited_chars = [];
        foreach ($this->delimited_chars as $key => $value) {
            $delimited_chars[] = [
                'label' => $value,
                'value' => (string)$key,
            ];
        }

        $formats = [];
        foreach ($this->formats as $key => $value) {
            $formats[] = [
                'label' => $value,
                'value' => (string)$key,
            ];
        }

        $option_categories = [];
        foreach ($this->option_categories as $key => $value) {
            $option_categories[] = [
                'label' => $value,
                'value' => (string)$key,
            ];
        }

        return [
            'data' => $template,
            'repeatedOption' => $repeated_fields,
            'delimitedOption' => $delimited_chars,
            'formatOption' => $formats,
            'catOption' => $option_categories,
        ];
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $template = Template::find($id);

        if (!$template) {
            return response()->json([
                'message' => "Template didn't found!",
                'status' => 203,
                'data' => []
            ], 203);
        }

        $template->template_name = $request->get('template_name');
        $template->repeated_fields = $request->get('repeated_fields');
        $template->delimited_char = $request->get('delimited_char');
        $template->show_header = $request->get('show_header');
        $template->break_kits = $request->get('break_kits') ? '1' : '0';
        $template->is_active = $request->get('is_active');
        $template->save();

        TemplateOption::where('template_id', $id)->delete();

        $optionData = [];
        foreach ($request->options ?? [] as $option) {
            $optionData[] = [
                'template_id' => $id,
                'line_item_field' => $option['line_item_field'],
                'option_name' => $option['option_name'],
                'option_category' => $option['option_category'],
                'value' => $option['value'] ?? 'Not set',
                'width' => $option['width'],
                'format' => $option['format'],
                'template_order' => $option['template_order'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()

            ];
        }
        if (count($optionData)) {
            TemplateOption::insert($optionData);
        }

        return response()->json([
            'message' => 'Template with template option updated successfully!',
            'status' => 201,
            'data' => []
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $data = Template::find($id);
        if ($data) {
            $data->delete();
            return response()->json([
                'message' => 'Template delete successfully!',
                'status' => 201,
                'data' => []
            ], 201);
        }
        return response()->json([
            'message' => "Template didn't found!",
            'status' => 203,
            'data' => []
        ], 203);
    }

    public function templateOption()
    {
        $template = Template::get();
        $template->transform(function ($item) {
            return [
                'value' => $item['id'],
                'label' => $item['template_name']
            ];
        });
        return $template;
    }
}
