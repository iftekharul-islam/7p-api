<?php

namespace library;

use App\Models\BatchRoute;
use App\Models\Inventory;
use App\Models\Option;
use App\Models\Parameter;
use App\Models\StoreItem;
use Illuminate\Support\Str;

class Helper
{
    public static function getEmptyStation()
    {
        $routes = BatchRoute::with('stations_count')
            ->where('is_deleted', 0)
            ->get();
        $zeroStations = $routes->filter(function ($row) {
            // if the stations count == 0
            return count($row->stations_count) == 0;
        });

        return $zeroStations;
    }
    public static $specSheetSampleDataArray = [
        'Yes'              => 'Yes',
        'No'               => 'No',
        'Redo Sample'      => 'Redo Sample',
        'Complete'         => 'Complete',
        'Sample Approve'   => 'Sample Approve',
        'Graphic Complete' => 'Graphic Complete',
    ];

    public static function getDefaultRouteId()
    {
        return 115;
    }

    private static $state_abbrev = array(
        'alabama' => 'AL',
        'alaska' => 'AK',
        'arizona' => 'AZ',
        'arkansas' => 'AR',
        'california' => 'CA',
        'colorado' => 'CO',
        'connecticut' => 'CT',
        'delaware' => 'DE',
        'florida' => 'FL',
        'georgia' => 'GA',
        'hawaii' => 'HI',
        'idaho' => 'ID',
        'illinois' => 'IL',
        'indiana' => 'IN',
        'iowa' => 'IA',
        'kansas' => 'KS',
        'kentucky' => 'KY',
        'louisiana' => 'LA',
        'maine' => 'ME',
        'maryland' => 'MD',
        'massachusetts' => 'MA',
        'michigan' => 'MI',
        'minnesota' => 'MN',
        'mississippi' => 'MS',
        'missouri' => 'MO',
        'montana' => 'MT',
        'nebraska' => 'NE',
        'nevada' => 'NV',
        'new hampshire' => 'NH',
        'new jersey' => 'NJ',
        'new mexico' => 'NM',
        'new york' => 'NY',
        'north carolina' => 'NC',
        'north dakota' => 'ND',
        'ohio' => 'OH',
        'oklahoma' => 'OK',
        'oregon' => 'OR',
        'pennsylvania' => 'PA',
        'rhode island' => 'RI',
        'south carolina' => 'SC',
        'south dakota' => 'SD',
        'tennessee' => 'TN',
        'texas' => 'TX',
        'utah' => 'UT',
        'vermont' => 'VT',
        'virginia' => 'VA',
        'washington' => 'WA',
        'west virginia' => 'WV',
        'wisconsin' => 'WI',
        'wyoming' => 'WY',
        'british columbia' => 'BC',
        'newfoundland and labrador' => 'NL',
        'prince edward island' => 'PE',
        'nova scotia' => 'NS',
        'new brunswick' => 'NB',
        'quebec' => 'QC',
        'ontario' => 'ON',
        'manitoba' => 'MB',
        'saskatchewan' => 'SK',
        'alberta' => 'AB',
        'yukon' => 'YT',
        'northwest territories' => 'NT',
        'nunavut' => 'NU',
        'district of columbia' => 'DC',
        'virgin islands' => 'VI',
        'guam' => 'GU',
    );
    public static function stateAbbreviation($state)
    {
        if (isset(static::$state_abbrev[strtolower($state)])) {
            return static::$state_abbrev[strtolower($state)];
        } else {
            return $state;
        }
    }


    public static function getChildSku($item, $vendor_sku = null)
    {
        $store_item = StoreItem::where('store_id', $item->store_id)
            ->where('parent_sku', $item->item_code)
            ->searchVendorSku($vendor_sku)
            ->get();

        if ($store_item && count($store_item) == 1 && $store_item->first()->child_sku != '') {
            return $store_item->first()->child_sku;
        }

        // related to parameter options table
        // get the item options from order
        $item_options = json_decode($item->item_option, true);
        // Check is item_options an array
        if (!is_array($item_options)) {
            return $item->item_code;
        }
        // get the keys from that order options
        $item_option_keys = array_map(function ($element) {
            return strtolower(trim(preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $element)));
        }, array_keys($item_options));

        // $store_id = $item->store_id;
        // get the keys available as parameter
        $parameters = Parameter::where('is_deleted', '0')
            ->get()
            ->pluck('parameter_value')
            ->toArray();

        $parameter_to_html_form_name = array_map(function ($element) {
            return Helper::textToHTMLFormName(strtolower($element));
        }, $parameters);

        $parameter_options = Option::where('parent_sku', $item->item_code)
            ->get();

        // get the common in the keys
        $options_in_common = array_intersect($parameter_to_html_form_name, $item_option_keys);

        //generate the new sku
        $child_sku = static::generateChildSKU($options_in_common, $parameter_options, $item);

        return $child_sku;
    }

    private static function generateChildSKU($matches, $parameter_options, $item)
    {
        // parameter options is an array of rows
        $item_options = json_decode($item->item_option, true);

        // 20160515 remove (+ character from child_sku
        $explode_values = [];
        foreach ($item_options as $item_key => $item_value) {
            $explode_values = explode("(", $item_value);
            if (count($explode_values) > 0) {
                $item_options[strtolower(trim($item_key))] = str_replace(['&quot;', '&amp;'], '', $explode_values[0]);
            }
        }

        if (count($matches) > 0) {
            foreach ($parameter_options as $option) {
                if ($option->parameter_option != null) {
                    // item options has replaced space with underscore
                    // parameter options has spaces intact
                    $parameter_option_json_decoded = json_decode(strtolower($option->parameter_option), true);
                    $match_broken = false;
                    foreach ($matches as $match) {
                        // matches are underscored
                        // i,e: form name
                        // convert to text for parameter options
                        // if ( $parameter_option_json_decoded[Helper::htmlFormNameToText($match)] != $item_options[$match] ) {
                        if (!array_key_exists(Helper::htmlFormNameToText($match), $parameter_option_json_decoded) || !array_key_exists($match, $item_options) || ($parameter_option_json_decoded[Helper::htmlFormNameToText($match)] != $item_options[$match])) {
                            $match_broken = true;
                            break;
                        }
                    }
                    // if the inner loop
                    // executes thoroughly
                    // then the match_broken will be false always
                    // break the outer loop
                    // return the value
                    // if the match is not broken.
                    // if all the matches are found
                    // will not
                    if (!$match_broken) {
                        return $option->child_sku;
                        //break;
                    }
                }
            }
        }
        // child sku suggestion
        // no option was found matching
        // suggest a new child sku
        $child_sku_postfix = implode("-", array_map(function ($node) use ($item_options) {
            // replace the spaces with empty string
            // make the string lower
            // and the values from the item options
            return str_replace(" ", "", strtolower($item_options[$node]));
        }, $matches));

        $child_sku = empty($child_sku_postfix) ? $item->item_code : sprintf("%s-%s", $item->item_code, $child_sku_postfix);

        // Replace Please Select
        $child_sku = str_replace("-pleaseselect", "", $child_sku);

        // should have to match the previous check.
        // again check if the child sku is present or not
        return Helper::insertOption($child_sku, $item, $matches, $item_options);
    }

    public static function insertOption($child_sku, $item, $matches = null, $item_options = null)
    {

        $option = Option::where('child_sku', $child_sku)->first();

        if (!$option) {
            //TODO: Xstore_id is missing here
            $option = new Option();
            $option->child_sku = $child_sku;
            $option->unique_row_value = static::generateUniqueRowId();
            $option->id_catalog = $item->item_id;
            $option->parent_sku = $item->item_code;
            $option->graphic_sku = 'NeedGraphicFile';
            $option->allow_mixing = '0';
            $option->batch_route_id = static::getDefaultRouteId();
            $option_array = [];
            // add the found parameters 
            if ($matches != null) {
                foreach ($matches as $match) {
                    $option_array[static::htmlFormNameToText($match)] = $item_options[$match];
                }
            }
            $option->parameter_option = json_encode($option_array);
            $option->save();

            Inventory::saveinventoryUnit($child_sku, "ToBeAssigned", '1');
        }

        return $child_sku;
    }

    public static function generateUniqueRowId()
    {
        return sprintf("%s_%s", strtotime("now"), Str::random(5));
    }

    public static function textToHTMLFormName($text)
    {
        // double underscore is for protection
        // in case a single underscore found on string won't be replaced
        return str_replace(" ", "_", trim($text));
    }

    public static function htmlFormNameToText($text)
    {
        return str_replace("_", " ", $text);
    }
}
