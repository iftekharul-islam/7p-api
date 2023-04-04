<?php

namespace App\Http\Controllers;

use App\Models\Parameter;
use Illuminate\Http\Request;

class ParameterController extends Controller
{
    public function index(Request $request)
    {
        $reason = Parameter::query();
        if ($request->q) {
            $reason = $reason->where('rejection_message', 'like', '%' . $request->q . '%');
        }
        return $reason->where('is_deleted', '0')->paginate($request->get('perPage', 10));
    }

    public function sortOrder($direction, $id)
    {
        $reason = Parameter::find($id);

        if (!$reason) {
            return response()->json([
                'message' => "Reason not Found",
                'status' => 203,
                'data' => []
            ], 203);
        }

        if ($direction == 'up') {
            $new_order = $reason->sort_order - 1;
        } else if ($direction == 'down') {
            $new_order = $reason->sort_order + 1;
        } else {
            return response()->json([
                'message' => "Sort direction not recognized",
                'status' => 203,
                'data' => []
            ], 203);
        }

        $switch = Parameter::where('sort_order', $new_order)->get();

        if (count($switch) > 1) {
            return response()->json([
                'message' => "Sort Order Error",
                'status' => 203,
                'data' => []
            ], 203);
        }

        if (count($switch) == 1) {
            $switch->first()->sort_order = $reason->sort_order;
            $switch->first()->save();
        }

        $reason->sort_order = $new_order;
        $reason->save();

        return response()->json([
            'message' => "Sort Order",
            'status' => 200,
            'data' => []
        ], 200);
    }

    public function show(string $id)
    {
        return Parameter::find($id);
    }

    public function store(Request $request)
    {
        $request->validate([
            'parameter_value' => 'required',
        ]);

        $data = $request->only([
            'parameter_value',
        ]);
        try {
            Parameter::create($data);
            return response()->json([
                'message' => 'Rejection Reason created successfully!',
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
            'parameter_value' => 'required',
        ]);
        try {
            $station = Parameter::find($id);
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

    public function destroy(string $id)
    {
        $data = Parameter::find($id);
        if ($data) {
            $data->delete();
            return response()->json([
                'message' => 'Parameter delete successfully!',
                'status' => 201,
                'data' => []
            ], 201);
        }
        return response()->json([
            'message' => "Parameter didn't found!",
            'status' => 203,
            'data' => []
        ], 203);
    }
}
