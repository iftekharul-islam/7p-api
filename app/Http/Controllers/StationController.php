<?php

namespace App\Http\Controllers;

use App\Models\Station;
use Illuminate\Http\Request;

class StationController extends Controller
{
    public function index(Request $request)
    {
        $station = Station::query()->with('section_info');
        if ($request->q) {
            $station = $station->where('station_name', 'like', '%' . $request->q . '%');
        }
        return $station->orderBy('id', 'desc')->paginate($request->get('perPage', 10));
    }

    public function show(string $id)
    {
        return Station::find($id);
    }

    public function store(Request $request)
    {
        $request->validate([
            'station_name' => 'required',
        ]);

        $data = $request->only([
            'station_name',
            'station_description',
            'section',
            'type',
        ]);
        try {
            Station::create($data);
            return response()->json([
                'message' => 'Station created successfully!',
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

    public function update(Request $request, string $id)
    {
        $request->validate([
            'station_name' => 'required',
        ]);
        try {
            $station = Station::find($id);
            if (!$station) {
                return response()->json([
                    'message' => 'Station not found!',
                    'status' => 203,
                    'data' => []
                ], 203);
            }
            $data = $request->only([
                'station_name',
                'station_description',
                'station_status',
                'section',
                'type',
            ]);
            $station->update($data);
            return response()->json([
                'message' => 'Station update successfully!',
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
}
