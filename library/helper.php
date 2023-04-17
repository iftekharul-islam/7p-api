<?php

namespace library;

use App\Models\BatchRoute;

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
}
