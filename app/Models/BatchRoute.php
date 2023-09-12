<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use library\Helper;

class BatchRoute extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_code',
        'batch_route_name',
        'batch_max_units',
        'nesting',
        'export_template',
        'batch_options',
        'csv_extension',
        'export_dir',
        'graphic_dir',
        'summary_msg_1',
        'summary_msg_2'
    ];

    private function tableColumns()
    {
        $columns = $this->getConnection()
            ->getSchemaBuilder()
            ->getColumnListing($this->getTable());
        $remove_columns = [
            'updated_at',
            'created_at',
            'id',
            'is_deleted',
        ];

        return array_diff($columns, $remove_columns);
    }

    public static function getTableColumns()
    {
        return (new static())->tableColumns();
    }

    public function template()
    {
        return $this->belongsTo(Template::class, 'export_template', 'id');
    }

    public function scopeSearchEmptyStations($query, $search)
    {
        $search = intval($search);
        if (!$search) {
            return;
        }
        $emptyStationRoutes = Helper::getEmptyStation();
        $batches_list = $emptyStationRoutes->pluck('id')
            ->toArray();

        return $query->whereIn('id', $batches_list);
    }

    /*public function itemGroups ()
	{
		return $this->hasMany('App\Product')
					->where('products.is_deleted', 0)
					->select([
						DB::raw('products.id as product_table_id'),
						'products.store_id',
						'products.batch_route_id',
						'products.id_catalog',
						'products.product_model',
						'products.allow_mixing',
					]);
	}*/

    public function itemGroups()
    {
        return $this->hasMany(Option::class)
            ->select([
                //DB::raw('parameter_options.id as product_table_id'),
                //'parameter_options.store_id',
                'parameter_options.batch_route_id',
                'parameter_options.allow_mixing',
                'parameter_options.parent_sku',
                'parameter_options.child_sku',
            ]);
    }

    public function stations()
    {
        return $this->belongsToMany(Station::class, 'batch_route_station', 'batch_route_id', 'station_id')
            ->withTimestamps();
    }

    public function stations_count()
    {
        // count the number of stations
        // counting the route id
        return $this->belongsToMany(Station::class)
            ->selectRaw('COUNT(batch_route_id) as aggregate')
            ->groupBy('batch_route_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'batch_route_id', 'id')
            ->where('is_deleted', 0)
            ->select([
                'id',
                'batch_route_id',
                'id_catalog',
            ]);
    }

    public function stations_list()
    {
        return $this->belongsToMany(Station::class)
            ->select([
                'station_name',
                'station_description',
                'station_id',
                'section'
            ]);
    }

    public function production_stations()
    {
        return $this->belongsToMany(Station::class)->where('type', 'P');
    }

    public function qc_stations()
    {
        return $this->belongsToMany(Station::class)->where('stations.type', 'Q');
    }

    public static function routeThroughStations($route_id, $station_name)
    {

        if (!$route_id) {
            return '';
        }
        $route = BatchRoute::with('stations')->find($route_id);
        $stations = implode(" > ", array_map(function ($elem) use ($station_name) {
            if ($station_name && $station_name == $elem['station_name']) {
                return sprintf("<strong>%s</strong>", $elem['station_name']);
            } else {
                return $elem['station_name'];
            }
        }, $route->stations->toArray()));

        return $stations;
    }

    public static function getLabelStation($route_id)
    {

        if (!$route_id) {
            return '';
        }

        $route = BatchRoute::with('stations')->find($route_id);

        foreach ($route->stations as $station) {
            if ($station->type == 'P' && $station->print_label != '0') {
                return $station->id;
            }
        }

        return '';
    }
}
