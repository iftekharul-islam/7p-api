<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    use HasFactory;
    protected $fillable = [
        'template_name',
        'show_header',
        'repeated_fields',
        'delimited_char',
        'break_kits',
        'is_active'
    ];

    public function options()
    {
        return $this->hasMany(TemplateOption::class, 'template_id', 'id')
            ->orderBy('template_order', 'asc');
    }

    public function exportable_options()
    {
        return $this->hasMany(TemplateOption::class, 'template_id', 'id')
            ->where('line_item_field', 1)
            ->orderBy('template_order', 'asc');
    }
}
