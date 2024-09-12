<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_catalog',
        'product_model',
        'manufacture_id',
        'product_upc',
        'product_asin',
        'product_default_cost',
        'product_url',
        'product_name',
        'ship_weight',
        'product_production_category',
        'product_price',
        'product_sale_price',
        'product_wholesale_price',
        'product_thumb',
        'product_description',
        'height',
        'width',
    ];

    public function manufacture()
    {
        return $this->hasOne(Manufacture::class, 'id', 'manufacture_id');
    }

    public static $searchable_fields = [
        'id_catalog'             => 'ID Catalog',
        'product_model'          => 'SKU',
        'product_name'           => 'Name',
        // 'product_sales_category' => 'Sales category',
        // 'product_price'          => 'Price',
        // 'product_sale_price'     => 'Sale price',
        // 'product_ship_weight'    => 'Ship Weight',
        // 'product_note'           => 'Note',
        // 'product_keywords'       => 'Keywords',
        'product_description'    => 'Description',
        // 'product_brand'          => 'Brand',
        // 'product_availability'   => 'Availability',
        // 'product_default_cost'   => 'Default cost',
        // 'product_video'          => 'Video',
        // 'height'                 => 'Height',
        // 'width'                  => 'Width',
        // 'product_headline'       => 'Headline',
        // 'product_caption'        => 'Caption',
        // 'product_label'          => 'Label',
    ];
    public function scopeSearchInOption($query, $search_in, $search_for)
    {

        // search in field means, in which field you want to search
        if ($search_in && in_array($search_in, array_keys(static::$searchable_fields))) {
            if ($search_in == 'id_catalog') {
                return $this->scopeSearchIdCatalog($query, $search_for);
            } elseif ($search_in == 'product_model') {
                return $this->scopeSearchProductModel($query, $search_for);
            } elseif ($search_in == 'product_name') {
                return $this->scopeSearchProductName($query, $search_for);
            } elseif ($search_in == 'product_description') {
                return $this->scopeSearchProductDescription($query, $search_for);
            }
        }

        return;
    }

    public function scopeSearchProductionCategory($query, $production_category)
    {
        if (!$production_category || !is_array($production_category)) {
            return;
        }

        $stripped_values = $this->trimmer($production_category, 'all');

        if (count($stripped_values) == 0 || (count($stripped_values) == 1 && $stripped_values[0] == '0')) {
            return;
        }

        return $query->whereIn('product_production_category', $stripped_values);
        /*if ( !$production_category || $production_category == 'all' ) {
			return;
		}
		return $query->where('product_production_category', intval($production_category));*/
    }

    public function scopeSearchIdCatalog($query, $id_catalog)
    {
        if (!$id_catalog) {
            return;
        }
        $replaced = str_replace(" ", "", $id_catalog);
        $values = explode(",", trim($replaced, ","));

        return $query->where('id_catalog', 'REGEXP', implode("|", $values));
    }

    public function scopeSearchProductModel($query, $product_model)
    {
        if (!$product_model) {
            return;
        }
        $replaced = str_replace(" ", "", $product_model);
        $values = explode(",", trim($replaced, ","));

        return $query->where('product_model', 'REGEXP', implode("|", $values));
    }

    public function scopeSearchProductName($query, $product_name)
    {
        if (!$product_name) {
            return;
        }
        $product_name = trim($product_name);

        return $query->where('product_name', 'LIKE', sprintf("%%%s%%", $product_name));
    }

    public function scopeSearchManufactureName($query, $manufacture_name)
    {
        if (!$manufacture_name) {
            return;
        }
        $manufacture_name = trim($manufacture_name);

        return $query->whereHas('manufacture', function ($q) use ($manufacture_name) {
            $q->where('name', 'LIKE', sprintf("%%%s%%", $manufacture_name));
        });
    }
    public function scopeSearchProductDescription($query, $description)
    {
        $description = trim($description);
        if (empty($description)) {
            return;
        }

        return $query->where('product_description', "LIKE", sprintf("%%%s%%", $description));
    }
}
