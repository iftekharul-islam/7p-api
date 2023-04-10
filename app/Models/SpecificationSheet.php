<?php

namespace App\Models;

use App\Models\ProductionCategory;
use Illuminate\Database\Eloquent\Model;

// test
class SpecificationSheet extends Model
{
    protected $table = 'product_specification_sheet';

    public static $statuses = [
        0 => 'Initial',
        1 => 'Need graphic artwork',
        2 => 'Graphic Artwork Approval',
        3 => 'Need production automation',
        4 => 'Need web photo',
        5 => 'Ready to publish',
        6 => 'Published/Live',
        7 => 'Add Product Info',
        8 => 'SKU Production Approval',
    ];

    public static $webImageStatus = [
        'Select web image status',
        'Temporary',
        'Create Web Image',
        'Update Web Image',
        'Web Image Approval',
        'Publish Web image',
        'Complete - Final Image Uploaded',
    ];

    public static $searchable_fields = [
        'name'        => 'Name',
        'sku'         => "SKU",
        'description' => 'Description',
        'note'        => 'Note',
    ];

    public static $specSheetSampleDataArray = [
        'Yes'              => 'Yes',
        'No'               => 'No',
        'Redo Sample'      => 'Redo Sample',
        'Complete'         => 'Complete',
        'Sample Approve'   => 'Sample Approve',
        'Graphic Complete' => 'Graphic Complete',
    ];

    public function production_category()
    {
        return $this->belongsTo(ProductionCategory::class, 'production_category_id', 'id');
    }

    public function scopeSearchCriteria($query, $search_for, $search_in)
    {
        if ($search_in && in_array($search_in, array_keys(static::$searchable_fields))) {
            if ($search_in == 'name') {
                return $this->scopeSearchForName($query, $search_for);
            } elseif ($search_in == 'sku') {
                return $this->scopeSearchForSKU($query, $search_for);
            } elseif ($search_in == 'description') {
                return $this->scopeSearchForDescription($query, $search_for);
            } elseif ($search_in == 'note') {
                return $this->scopeSearchForNote($query, $search_for);
            }
        }

        return;
    }

    public function scopeSearchStatus($query, $status)
    {
        if ($status == 'all' || is_null($status)) {
            return;
        }
        return $query->where('status', $status);
    }

    public static function scopeSearchInWebImageStatus($query, $web_image_status)
    {
        if ($web_image_status == 0) {
            return;
        }

        return $query->where('web_image_status', intval($web_image_status));
    }

    public function scopeSearchInProductionCategory($query, $production_category_id)
    {
        $production_category_id = intval($production_category_id);
        $production_category = ProductionCategory::find($production_category_id);
        if ($production_category) {
            return $query->where('production_category_id', $production_category_id);
        }

        return;
    }

    public function scopeSearchForName($query, $name)
    {
        if (empty($name)) {
            return;
        }

        return $query->where('product_name', 'LIKE', sprintf("%%%s%%", $name));
    }

    public function scopeSearchForSKU($query, $sku)
    {
        if (empty($sku)) {
            return;
        }

        return $query->where('product_sku', 'LIKE', sprintf("%%%s%%", $sku));
    }

    public function scopeSearchForDescription($query, $description)
    {
        if (empty($description)) {
            return;
        }

        return $query->where('product_description', 'LIKE', sprintf("%%%s%%", $description));
    }

    public function scopeSearchForNote($query, $note)
    {
        if (empty($note)) {
            return;
        }

        return $query->where('product_note', 'LIKE', sprintf("%%%s%%", $note));
    }

    public function scopeSearchMakeSample($query, $make_sample = 'all')
    {
        if (!$make_sample || $make_sample == 'all') {
            return;
        }
        $make_sample = trim($make_sample);

        return $query->where('make_sample', $make_sample);
    }
}
