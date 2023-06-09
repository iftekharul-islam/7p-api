<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_category_code',
        'production_category_description',
        'production_category_display_order'
    ];

    public function getDescriptionWithCodeAttribute()
    {
        return sprintf("%s : %s", $this->production_category_code, $this->production_category_description);
    }
}
