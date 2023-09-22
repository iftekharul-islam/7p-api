<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\BatchRoute;
use App\Models\Option;
use App\Models\Station;
use App\Models\Template;
use Illuminate\Http\Request;

class BatchRouteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $batch_routes = BatchRoute::with('stations_list', 'template')
            ->where('is_deleted', '0')
            ->searchEmptyStations($request->get('unassigned', 0))
            ->orderBy('batch_code');
        if ($request->q) {
            $batch_routes->where('batch_code', 'like', '%' . $request->q . '%');
        }
        $batch_routes =   $batch_routes->paginate($request->get('perPage', 10));
        return $batch_routes;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'batch_code' => 'required',
        ]);
        $data = $request->only([
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
        ]);

        $data['graphic_dir'] = $request['graphic_dir'] ?? 'Not Set';
        $batch_route = BatchRoute::create($data);
        $batch_route->stations()
            ->attach($request->get('batch_stations'));

        return response()->json([
            'message' => 'Route created successfully!',
            'status' => 201,
            'data' => []
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return BatchRoute::with('stations_list', 'template')
            ->where('is_deleted', '0')
            ->searchEmptyStations(0)
            ->find($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'batch_code' => 'required',
        ]);
        $data = $request->only([
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
        ]);
        $batch_route = BatchRoute::find($id);
        $batch_route->update($data);
        $batch_route->stations()
            ->detach();
        $batch_route->stations()
            ->attach($request['batch_stations']);

        return response()->json([
            'message' => 'Batch Route Update successfully!',
            'status' => 201,
            'data' => []
        ], 201);
    }

    public function batchRouteOptions()
    {
        $batch_route = BatchRoute::where('is_deleted', '0')->orderBy('batch_route_name')->get();
        $batch_route->transform(function ($item) {
            return [
                'value' => $item['id'],
                'label' => $item['batch_route_name']
            ];
        });
        return $batch_route;
    }

    public function statusesOptions()
    {
        $status = [];

        foreach (Batch::getStatusList() as $key => $value) {
            $status[] = [
                'value' => $key,
                'label' => $value
            ];
        }
        return $status;
    }
}
